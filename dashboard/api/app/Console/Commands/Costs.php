<?php

namespace App\Console\Commands;

use App\Services\Costs\CostSheet;
use App\Services\Costs\ProfitEngine;
use App\Services\Woo\CatalogueSync;
use App\Services\Woo\SignedClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Dealer costs in and out, as a spreadsheet.
 *
 * This is the command that unblocks the rest of the system. Profit per order is
 * the figure every KEEP and every KILL is compared against, and until costs are
 * entered it is a constant in a config file.
 */
class Costs extends Command
{
    protected $signature = 'wgh:costs
        {--export : Write the cost sheet to fill in}
        {--import= : Read a filled cost sheet back}
        {--show : Show what margins are known so far}
        {--pull : Refresh the product list from the shop before exporting}
        {--sold-only : Only list products that have already sold}
        {--dir= : Where to write the sheet. Defaults to storage/app/costs.}';

    protected $description = 'Enter dealer costs, so profit per order stops being a guess';

    public function handle(): int
    {
        if ($this->option('import')) {
            return $this->import((string) $this->option('import'));
        }

        if ($this->option('show')) {
            return $this->show();
        }

        return $this->export();
    }

    private function export(): int
    {
        $dir = $this->option('dir') ?: storage_path('app/costs');

        /*
         * Pull the catalogue first, unless told not to.
         *
         * Seeding only from what has already sold produced a three-row sheet on
         * a fifty-product shop, which is the wrong three rows: the products
         * worth costing FIRST are the ones ad money is being spent on, and a
         * product cannot be sold profitably before anyone knows what it costs.
         */
        if (! $this->option('sold-only')) {
            try {
                $c = (new CatalogueSync(app(SignedClient::class)))->run();
                $this->line("  Catalogue: {$c['seen']} product(s) on the shop, {$c['created']} new here, "
                    ."{$c['price_updated']} price or name change(s).");
                $this->newLine();
            } catch (Throwable $e) {
                $this->line('  <fg=yellow>Could not reach the shop for the product list.</> '.$e->getMessage());
                $this->line('  Falling back to products that have already sold.');
                $this->newLine();
            }
        }

        try {
            $r = (new CostSheet)->export($dir);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Cost sheet written, {$r['rows']} products.");
        $this->line('  '.$r['path']);
        $this->newLine();
        $this->line('  Sorted so the rows that change a decision are at the top:');
        $this->line("    {$r['sold']} that have already sold");
        $this->line("    {$r['messaged_only']} people messaged about but never bought");
        $this->line("    {$r['basketed_only']} put in a basket and nothing more");
        $this->newLine();
        $this->line('Download it through cPanel > File Manager, open it in a spreadsheet, and fill');
        $this->line('in two columns: <options=bold>dealer_cost_ghs</> (what you pay the supplier) and');
        $this->line('<options=bold>delivery_cost_ghs</> (what the rider costs for that item).');
        $this->newLine();
        $this->line('Put <options=bold>yes</> in the confirmed column once a supplier has actually quoted you.');
        $this->line('Leave anything you do not know BLANK. A blank is treated as unknown; a zero');
        $this->line('would make the product look like pure profit and quietly bend every verdict.');
        $this->newLine();
        $this->line('Then upload it back and run:');
        $this->line('  php artisan wgh:costs --import='.$r['path']);

        if ($r['already_costed'] > 0) {
            $this->newLine();
            $this->line("  {$r['already_costed']} product(s) already carry a cost and are pre-filled.");
        }

        if ($r['needed_now'] > 0) {
            $this->newLine();
            $this->line("  <fg=yellow>{$r['needed_now']} product(s) are selling or being advertised with no cost on file.</>");
            $this->line('  Those are the only rows that change a verdict today. The rest can wait.');
        }

        return self::SUCCESS;
    }

    private function import(string $path): int
    {
        try {
            $r = (new CostSheet)->import($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Read {$r['saved']} rows.");
        $this->table(['', ''], [
            ['Products with a usable cost', $r['complete']],
            ['Still blank', $r['blank']],
            ['Confirmed by a supplier', $r['confirmed']],
        ]);

        foreach ($r['problems'] as $p) {
            $this->line('  <fg=yellow>check</> '.$p);
        }

        $stamped = (new ProfitEngine)->stampOrders();
        $this->line("  {$stamped} order(s) re-costed.");

        $to = CarbonImmutable::now('UTC')->toDateString();
        $from = CarbonImmutable::parse($to)->subDays(30)->toDateString();
        $t = (new ProfitEngine)->judgingThreshold($from, $to);

        $this->newLine();
        $this->line('  <options=bold>Profit per order the engine will now judge by</>');
        $this->line('  $'.number_format($t['value_usd'], 2).'  ('.$t['source'].')');
        $this->line('  '.$t['explanation']);

        if ($t['source'] === 'measured') {
            $this->newLine();
            $this->line('  <fg=green>This is now measured rather than assumed.</> Re-run php artisan wgh:judge');
            $this->line('  and the verdicts will move: keywords change side as the real margin lands.');
        }

        return self::SUCCESS;
    }

    private function show(): int
    {
        $to = CarbonImmutable::now('UTC')->toDateString();
        $from = CarbonImmutable::parse($to)->subDays(90)->toDateString();

        $engine = new ProfitEngine;
        $p = $engine->profitPerOrder($from, $to);
        $c = $p['coverage'];

        $this->info('Cost coverage');
        $this->table(['', ''], [
            ['Products with a dealer cost', $c['products_costed'].' of '.$c['products_total']],
            ['Confirmed by a supplier', $c['confirmed_with_supplier']],
            ['Baskets fully costed', $c['baskets_fully_costed'].' of '.$c['baskets_total']],
            ['Measured profit per order', $p['profit_per_order_ghs'] ? 'GHS '.$p['profit_per_order_ghs'] : 'not yet measurable'],
            ['Assumed profit per order', '$'.$p['assumed_usd']],
        ]);

        $margins = $engine->productMargins($from, $to);

        if ($margins) {
            $this->newLine();
            $this->line('  <options=bold>Product margins</>');
            $this->table(
                ['Product', 'Units', 'Revenue GHS', 'Margin', 'Profit GHS'],
                array_map(fn ($m) => [
                    mb_substr($m['name'], 0, 34),
                    $m['units'],
                    $m['revenue_ghs'],
                    $m['margin_percent'] !== null ? $m['margin_percent'].'%' : 'cost unknown',
                    $m['total_profit_ghs'] ?? '-',
                ], array_slice($margins, 0, 15))
            );
        }

        if ($c['products_costed'] === 0) {
            $this->newLine();
            $this->line('  <fg=yellow>No costs entered yet.</> Every verdict in the system currently rests on');
            $this->line('  an assumed $'.$p['assumed_usd'].' per order. Start with: php artisan wgh:costs --export');
        }

        return self::SUCCESS;
    }
}
