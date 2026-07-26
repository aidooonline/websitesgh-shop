<?php

namespace App\Console\Commands;

use App\Services\Ads\JoinEngine;
use App\Services\Decisions\MilestoneEvaluator;
use App\Services\Decisions\PatternDetector;
use App\Services\Decisions\VerdictEngine;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class RunEngine extends Command
{
    protected $signature = 'wgh:judge
        {--from= : Period start YYYY-MM-DD. Defaults to 30 days ago.}
        {--to= : Period end YYYY-MM-DD. Defaults to today.}
        {--dry-run : Show the picture without recording any verdict}';

    protected $description = 'Join spend to sales, judge every keyword and channel, evaluate the milestone ladder';

    public function handle(): int
    {
        $to = $this->option('to') ?: CarbonImmutable::now('UTC')->toDateString();
        $from = $this->option('from') ?: CarbonImmutable::parse($to)->subDays(30)->toDateString();

        $join = new JoinEngine;

        try {
            $picture = $join->build($from, $to);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $t = $picture['totals'];

        $this->info("The picture, {$from} to {$to}");
        $this->table(['', ''], [
            ['Ad spend (USD)', $t['spend_usd']],
            ['Clicks', $t['clicks']],
            ['Add to cart', $t['carts']],
            ['WhatsApp taps', $t['taps']],
            ['Confirmed sales', $t['orders']],
            ['Revenue (GHS)', $t['revenue_ghs']],
            ['Unmatched spend (USD)', $t['unmatched_spend_usd']],
        ]);

        if ($picture['fx']['rate'] === null) {
            $this->line('  <fg=yellow>No fx rate recorded.</> USD spend and GHS revenue cannot be compared until');
            $this->line('  there is one. Run: php artisan wgh:fx 11.85');
        } else {
            $this->line("  fx: 1 USD = {$picture['fx']['rate']} GHS, dated {$picture['fx']['date']}");
        }

        // Unmatched spend is money with no story. It goes near the top, not in
        // a footnote, because it is either a tracking gap or a real leak and
        // both are worth more attention than a well-behaved keyword.
        if ($picture['unmatched']) {
            $this->newLine();
            $this->line('  <fg=yellow>Unmatched spend</> ($'.$t['unmatched_spend_usd'].'), which is a finding, not a rounding error:');

            foreach (array_slice($picture['unmatched'], 0, 8) as $u) {
                $this->line(sprintf('   $%-8s %s / %s %s', $u['spend_usd'], $u['platform'], $u['campaign'], $u['keyword'] ? '"'.$u['keyword'].'"' : ''));
                $this->line('             '.$u['likely_cause']);
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->line('Dry run. No verdict was recorded.');

            return self::SUCCESS;
        }

        // Judge against the MEASURED margin where dealer costs make it
        // measurable, and against the assumption otherwise. This one line is
        // the difference between verdicts that reflect the business and
        // verdicts that reflect a number somebody typed into a config file.
        $threshold = (new \App\Services\Costs\ProfitEngine)->judgingThreshold(
            $from, $to, $picture['fx']['rate'] ? (float) $picture['fx']['rate'] : null
        );

        $this->newLine();
        $this->line('  <options=bold>Judging against $'.number_format($threshold['value_usd'], 2)
            .' profit per order</> ('.$threshold['source'].')');
        $this->line('  '.$threshold['explanation']);

        $engine = new VerdictEngine($threshold);
        $keywords = $engine->judgeKeywords($picture['keywords']);
        $channels = $engine->judgeChannels($picture['channels']);
        $products = $engine->judgeProducts(
            (new \App\Services\Costs\ProfitEngine)->productMargins($from, $to)
        );
        $join->updateRegistry($picture['keywords']);

        // Customers and baskets are recomputed here so one command still gives
        // the whole picture. Splitting it across two would mean half the system
        // silently going stale whenever the second one was forgotten.
        $insights = new \App\Services\Customers\CustomerInsights;
        $insights->rebuild();
        $insights->rebuildPairs();

        $this->newLine();
        $this->info('Verdicts');
        $this->table(
            ['Dimension', 'Keep', 'Watch', 'Fix', 'Kill'],
            [
                ['keywords', $keywords['counts']['keep'], $keywords['counts']['watch'], $keywords['counts']['fix'], $keywords['counts']['kill']],
                ['channels', $channels['counts']['keep'], $channels['counts']['watch'], $channels['counts']['fix'], $channels['counts']['kill']],
                ['products', $products['counts']['keep'], $products['counts']['watch'], $products['counts']['fix'], $products['counts']['kill']],
            ]
        );

        $lossMakers = array_values(array_filter($products['verdicts'], fn ($v) => $v['verdict'] === 'kill'));

        if ($lossMakers) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>Products that lose money on every sale</>');
            foreach (array_slice($lossMakers, 0, 5) as $v) {
                $this->line('   '.$v['entity_ref'].' - '.$v['reason']);
            }
        }

        foreach (['kill', 'fix', 'keep'] as $verdict) {
            $set = array_values(array_filter($keywords['verdicts'], fn ($v) => $v['verdict'] === $verdict));

            if (! $set) {
                continue;
            }

            $this->newLine();
            $this->line('  <options=bold>'.strtoupper($verdict).'</>');

            foreach (array_slice($set, 0, 6) as $v) {
                $this->line('   '.$v['entity_ref']);
                $this->line('     '.$v['reason']);
                $this->line('     <fg=cyan>'.$v['action'].'</>');
            }

            if (count($set) > 6) {
                $this->line('   ...and '.(count($set) - 6).' more, all recorded in the decisions log.');
            }
        }

        $patterns = (new PatternDetector)->detect($keywords['verdicts']);

        if ($patterns) {
            $this->newLine();
            $this->info('Patterns');

            foreach ($patterns as $p) {
                $this->line('  '.$p['observation']);
            }
        }

        $ladder = (new MilestoneEvaluator)->evaluate();

        $this->newLine();
        $this->info('The offline conversion loop');
        $this->line('  confirmed sales in 30 days .... '.$ladder['facts']['conversions_30d']);
        $this->line('  uploadable (has a click id) ... '.$ladder['facts']['uploadable']);
        $this->line('  match rate (click id + phone) . '.(int) round($ladder['facts']['match_rate'] * 100).'%');
        $this->line('  waiting to be exported ........ '.$ladder['facts']['unexported_conversions']);

        if ($ladder['next_gate']) {
            $this->line('  next gate ..................... '.$ladder['next_gate']['label'].'  ('.$ladder['next_gate']['progress'].')');
        }

        foreach ($ladder['newly_reached'] as $g) {
            $this->newLine();
            $this->line('  <fg=green;options=bold>GATE REACHED: '.$g['label'].'</>');
            $this->line('  '.$g['decision']);
        }

        foreach ($ladder['active_guardrails'] as $g) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>'.$g['label'].'</>');
            $this->line('  '.$g['decision']);
        }

        $this->newLine();
        $this->line('Every verdict above is recorded with the numbers behind it. Nothing was changed in any ad account.');

        return self::SUCCESS;
    }
}
