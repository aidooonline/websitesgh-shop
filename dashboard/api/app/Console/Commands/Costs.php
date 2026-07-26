<?php

namespace App\Console\Commands;

use App\Models\ProductCost;
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
        {--list : Show what still needs a cost, with the id to use}
        {--quick= : Set many at once, e.g. "36=700:25, 33=75:20" as id=dealer:delivery}
        {--enter : Ask one product at a time. Needs a terminal that accepts typing.}
        {--set= : Set one product by id, with --dealer and --delivery}
        {--dealer= : Dealer cost in GHS, with --set}
        {--delivery= : Delivery cost in GHS, with --set}
        {--supplier= : Supplier name, with --set}
        {--confirmed : Mark the price as quoted by a supplier, with --set}
        {--export : Write the cost sheet to fill in}
        {--import= : Read a filled cost sheet back}
        {--show : Show what margins are known so far}
        {--pull : Refresh the product list from the shop before exporting}
        {--sold-only : Only list products that have already sold}
        {--limit=10 : How many products to walk through with --enter}
        {--dir= : Where to write the sheet. Defaults to storage/app/costs.}';

    protected $description = 'Enter dealer costs, so profit per order stops being a guess';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->list();
        }

        if ($this->option('quick')) {
            return $this->quick((string) $this->option('quick'));
        }

        if ($this->option('enter')) {
            return $this->enter();
        }

        if ($this->option('set')) {
            return $this->set((int) $this->option('set'));
        }

        if ($this->option('import')) {
            return $this->import((string) $this->option('import'));
        }

        if ($this->option('show')) {
            return $this->show();
        }

        return $this->export();
    }

    /*
     * ------------------------------------------------------------------
     * Typing costs in directly.
     *
     * WHY THIS EXISTS ALONGSIDE THE SPREADSHEET
     * The CSV was the right idea and the wrong workflow. It assumed a round
     * trip: download through cPanel File Manager, open in a spreadsheet, fill
     * in, upload back over the original. In practice the sheet was exported and
     * re-imported three times without a single cost being entered, because the
     * middle of that loop happens outside the terminal, often on a phone, and
     * it is the part that does not get done.
     *
     * The spreadsheet stays: it is still the right tool for forty products
     * after a round of supplier calls. This is for the three that matter today,
     * typed where the person already is.
     * ------------------------------------------------------------------
     */

    private function enter(): int
    {
        /*
         * REFUSE TO RUN WHERE NOBODY CAN ANSWER.
         *
         * cPanel's browser terminal does not give PHP an interactive STDIN, and
         * Symfony's question helper answers its own questions with the default
         * the moment it notices. The first run walked ten products, printed
         * "skipped, still unknown" ten times in under a second, and exited
         * reporting success. It looked like the job had been done and nothing
         * had been written. A command that cannot do its work must say so.
         */
        if (! $this->input->isInteractive() || ! stream_isatty(STDIN)) {
            $this->error('This terminal will not let me wait for typing, so every question would answer itself.');
            $this->newLine();
            $this->line('  Use the one-line form instead. It needs no prompts:');
            $this->newLine();
            $this->line('    php artisan wgh:costs --list');
            $this->line('    php artisan wgh:costs --quick="36=700:25, 33=75:20"');
            $this->newLine();
            $this->line('  Each pair is <options=bold>id=dealer_cost:delivery_cost</>. The delivery part is optional.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $rows = $this->needingCost($limit);

        if (! $rows) {
            $this->info('Every product that has sold or been advertised already has a cost. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info('Type the cost. Press ENTER to skip a product, or type q to stop.');
        $this->line('  Blank stays unknown. A zero would make the product look like pure profit.');
        $this->newLine();

        // Delivery is usually the same rider fee across similar products, so
        // the last answer is offered as the default. Typing 25 forty times is
        // how a good idea becomes an abandoned one.
        $lastDelivery = null;
        $saved = 0;

        foreach ($rows as $i => $row) {
            $price = $row->sell_price_ghs !== null ? 'GHS '.number_format((float) $row->sell_price_ghs, 2) : 'price unknown';

            $this->newLine();
            $this->line(sprintf('  <options=bold>%d of %d</>  %s', $i + 1, count($rows), $row->product_name));
            $this->line('  sells for '.$price.'   (product id '.$row->woo_product_id.')');

            $dealer = $this->ask('  What do you pay the supplier');

            if ($dealer !== null && in_array(strtolower(trim($dealer)), ['q', 'quit', 'stop'], true)) {
                break;
            }

            $dealerValue = $this->money((string) $dealer);

            if ($dealerValue === null) {
                $this->line('  <fg=gray>skipped, still unknown</>');

                continue;
            }

            if ($row->sell_price_ghs !== null && $dealerValue >= (float) $row->sell_price_ghs) {
                $this->line('  <fg=yellow>That is at or above the selling price.</> Selling at a loss, or a typo?');

                if (! $this->confirm('  Save it anyway', false)) {
                    continue;
                }
            }

            $delivery = $this->ask('  What does the rider cost'.($lastDelivery !== null ? " [{$lastDelivery}]" : ''));
            $deliveryValue = $this->money((string) $delivery) ?? $lastDelivery;
            $lastDelivery = $deliveryValue ?? $lastDelivery;

            $supplier = $this->ask('  Supplier name (optional)');
            $quoted = $this->confirm('  Did a supplier actually quote you this', false);

            $row->forceFill([
                'dealer_cost_ghs' => $dealerValue,
                'delivery_cost_ghs' => $deliveryValue,
                'supplier' => $supplier ? mb_substr(trim($supplier), 0, 120) : $row->supplier,
                'is_estimate' => ! $quoted,
                'confirmed_at' => $quoted ? CarbonImmutable::now('UTC') : null,
                'updated_at' => CarbonImmutable::now('UTC'),
            ])->save();

            $saved++;

            $profit = $row->unitProfit();
            if ($profit !== null) {
                $this->line(sprintf('  <fg=green>saved.</> Leaves GHS %s a unit, %s%% margin.',
                    number_format($profit, 2), $row->marginPercent()));
            } else {
                $this->line('  <fg=green>saved.</>');
            }
        }

        $this->newLine();
        $this->info($saved.' product(s) costed.');

        return $saved > 0 ? $this->afterCosts() : self::SUCCESS;
    }

    /**
     * What still needs a cost, and the id to type. Nothing interactive.
     */
    private function list(): int
    {
        $rows = $this->needingCost(max(1, (int) $this->option('limit')));

        if (! $rows) {
            $this->info('Every product that has sold or been advertised already has a cost.');

            return self::SUCCESS;
        }

        $this->info('These need a dealer cost, most important first.');
        $this->newLine();

        $this->table(
            ['id', 'Product', 'Sells for'],
            array_map(fn ($r) => [
                $r->woo_product_id,
                mb_substr((string) $r->product_name, 0, 42),
                $r->sell_price_ghs !== null ? 'GHS '.number_format((float) $r->sell_price_ghs, 2) : 'unknown',
            ], $rows)
        );

        $example = array_slice($rows, 0, 2);
        $pairs = implode(', ', array_map(
            fn ($r) => $r->woo_product_id.'='.($r->sell_price_ghs !== null
                ? number_format((float) $r->sell_price_ghs * 0.65, 0, '.', '')
                : '000').':25',
            $example
        ));

        $this->line('  Enter them in one line, as <options=bold>id=dealer_cost:delivery_cost</>:');
        $this->newLine();
        $this->line('    php artisan wgh:costs --quick="'.$pairs.'"');
        $this->newLine();
        $this->line('  (those dealer figures are made up to show the shape, replace them)');
        $this->line('  The :delivery part is optional. Do as many or as few as you know.');

        return self::SUCCESS;
    }

    /**
     * Many costs in one line, for a terminal that cannot prompt.
     *
     * "36=700:25, 33=75" sets two products. Everything is validated before
     * anything is written, so a typo in the fourth pair does not leave three
     * saved and the command half done.
     */
    private function quick(string $raw): int
    {
        $parsed = [];
        $problems = [];

        foreach (preg_split('/[,\n]+/', $raw) ?: [] as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            if (! preg_match('/^(\d+)\s*=\s*([0-9.,]+)\s*(?::\s*([0-9.,]+))?$/', $chunk, $m)) {
                $problems[] = "\"{$chunk}\" is not id=dealer or id=dealer:delivery";

                continue;
            }

            $id = (int) $m[1];
            $dealer = $this->money($m[2]);
            $delivery = isset($m[3]) ? $this->money($m[3]) : null;

            $row = ProductCost::where('woo_product_id', $id)->first();

            if (! $row) {
                $problems[] = "no product with id {$id}. Run php artisan wgh:costs --list to see the real ids";

                continue;
            }

            if ($dealer === null) {
                $problems[] = "product {$id} has no usable dealer cost. A blank stays unknown by design";

                continue;
            }

            if ($row->sell_price_ghs !== null && $dealer >= (float) $row->sell_price_ghs) {
                $problems[] = sprintf(
                    'product %d: %s is at or above the %s selling price. Selling at a loss, or a typo?',
                    $id, number_format($dealer, 2), number_format((float) $row->sell_price_ghs, 2)
                );

                continue;
            }

            $parsed[] = [$row, $dealer, $delivery];
        }

        /*
         * All or nothing. Writing the good pairs and reporting the bad ones
         * would leave the person guessing which half landed, and the natural
         * response is to re-run the whole line, which then complains about
         * costs it has already saved.
         */
        if ($problems) {
            $this->error('Nothing was saved. Fix these and run it again:');
            foreach ($problems as $p) {
                $this->line('  <fg=yellow>*</> '.$p);
            }

            return self::FAILURE;
        }

        if (! $parsed) {
            $this->error('Nothing to set. The format is: --quick="36=700:25, 33=75"');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now('UTC');

        foreach ($parsed as [$row, $dealer, $delivery]) {
            $row->forceFill([
                'dealer_cost_ghs' => $dealer,
                'delivery_cost_ghs' => $delivery ?? $row->delivery_cost_ghs,
                'is_estimate' => true,
                'updated_at' => $now,
            ])->save();

            $profit = $row->unitProfit();

            $this->line(sprintf(
                '  <fg=green>%s</>  %s',
                mb_substr((string) $row->product_name, 0, 40),
                $profit !== null
                    ? 'leaves GHS '.number_format($profit, 2).' a unit, '.$row->marginPercent().'% margin'
                    : 'saved'
            ));
        }

        $this->newLine();
        $this->info(count($parsed).' product(s) costed.');
        $this->line('  All marked as estimates. Add --confirmed with --set once a supplier has quoted you.');

        return $this->afterCosts();
    }

    private function set(int $productId): int
    {
        $row = ProductCost::where('woo_product_id', $productId)->first();

        if (! $row) {
            $this->error("No product with id {$productId}. Run php artisan wgh:costs --export to refresh the catalogue.");

            return self::FAILURE;
        }

        $dealer = $this->money((string) $this->option('dealer'));

        if ($dealer === null) {
            $this->error('--dealer is required, and must be a number. A blank cost stays unknown by design.');

            return self::FAILURE;
        }

        if ($row->sell_price_ghs !== null && $dealer >= (float) $row->sell_price_ghs) {
            $this->line('  <fg=yellow>Warning:</> that is at or above the '
                .number_format((float) $row->sell_price_ghs, 2).' selling price. Saved anyway, but check it.');
        }

        $quoted = (bool) $this->option('confirmed');

        $row->forceFill([
            'dealer_cost_ghs' => $dealer,
            'delivery_cost_ghs' => $this->money((string) $this->option('delivery')) ?? $row->delivery_cost_ghs,
            'supplier' => $this->option('supplier') ? mb_substr((string) $this->option('supplier'), 0, 120) : $row->supplier,
            'is_estimate' => ! $quoted,
            'confirmed_at' => $quoted ? CarbonImmutable::now('UTC') : null,
            'updated_at' => CarbonImmutable::now('UTC'),
        ])->save();

        $profit = $row->unitProfit();

        $this->info($row->product_name.' costed.');
        if ($profit !== null) {
            $this->line(sprintf('  Leaves GHS %s a unit, %s%% margin.', number_format($profit, 2), $row->marginPercent()));
        }

        return $this->afterCosts();
    }

    /**
     * Products that have sold or drawn interest and still have no cost, most
     * important first. The same ranking the spreadsheet uses.
     *
     * @return list<ProductCost>
     */
    private function needingCost(int $limit): array
    {
        $sheet = (new CostSheet)->priorityOrder();
        $out = [];

        foreach ($sheet as $id) {
            $row = ProductCost::where('woo_product_id', $id)->first();

            if ($row && $row->dealer_cost_ghs === null) {
                $out[] = $row;
            }

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Recost the orders and say what the margin now is. Shared by every entry
     * path, so typing a cost and importing a sheet report the same thing.
     */
    private function afterCosts(): int
    {
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

    private function money(string $raw): ?float
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '-') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $raw)) ?? '';

        return $clean === '' ? null : round((float) $clean, 2);
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
