<?php

namespace App\Console\Commands;

use App\Services\Customers\CustomerInsights;
use Illuminate\Console\Command;

class Customers extends Command
{
    protected $signature = 'wgh:customers {--rebuild : Recompute from attribution and orders}';

    protected $description = 'Repeat purchase, area demand, and what sells with what';

    public function handle(): int
    {
        $insights = new CustomerInsights;

        if ($this->option('rebuild') || \App\Models\Customer::count() === 0) {
            $n = $insights->rebuild();
            $pairs = $insights->rebuildPairs();
            $this->line("  Rebuilt {$n} customer(s) and {$pairs} product pair(s).");
            $this->newLine();
        }

        $s = $insights->summary();

        $this->info('Customers');
        $this->table(['', ''], [
            ['Buyers identified', $s['buyers']],
            ['Bought more than once', $s['repeat_buyers']],
            ['Repeat rate', $s['repeat_rate'] !== null ? $s['repeat_rate'].'%' : 'not measurable yet'],
            ['Median days to a second order', $s['median_days_to_second_order'] ?? 'no second orders yet'],
            ['Average order', $s['average_order_ghs'] ? 'GHS '.$s['average_order_ghs'] : '-'],
            ['Repeat share of revenue', $s['repeat_share_of_revenue'] !== null ? $s['repeat_share_of_revenue'].'%' : '-'],
            ['Sales we can put a name to', $s['identified_share'] !== null ? $s['identified_share'].'%' : '-'],
        ]);

        // A repeat rate computed on a third of buyers is not a repeat rate.
        if ($s['identified_share'] !== null && $s['identified_share'] < 60) {
            $this->newLine();
            $this->line('  <fg=yellow>Only '.$s['identified_share'].'% of sales carry a phone number</>, so the repeat rate above is');
            $this->line('  measured on part of the business, not all of it. Capture the number when you');
            $this->line('  mark a sale Sold and this sharpens fast.');
        }

        if ($s['repeat_rate'] !== null) {
            $this->newLine();
            // The ecommerce benchmark sits around 25 to 30 percent.
            $this->line($s['repeat_rate'] < 25
                ? '  Below the 25 to 30% typical for ecommerce. The second sale is the cheapest'
                    .' revenue you can make, and it costs nothing in ad spend.'
                : '  At or above the 25 to 30% typical for ecommerce. Worth protecting.');
        }

        $areas = $insights->byArea();

        if ($areas) {
            $this->newLine();
            $this->line('  <options=bold>Where they buy</>');
            $this->table(
                ['Area', 'Buyers', 'Orders', 'Revenue GHS', 'Avg order', 'Repeat'],
                array_map(fn ($a) => [$a['area'], $a['buyers'], $a['orders'], $a['revenue_ghs'], $a['average_order_ghs'], $a['repeat_buyers']],
                    array_slice($areas, 0, 12))
            );
            $this->line('  <fg=cyan>Meta lets you target by area. This is the list.</>');
        }

        $bundles = $insights->bundleCandidates();

        if ($bundles) {
            $this->newLine();
            $this->line('  <options=bold>Worth bundling</>');
            foreach ($bundles as $b) {
                $this->line('   '.$b['a'].'  +  '.$b['b']);
                $this->line('     '.$b['reading']);
            }
            $this->line('  <fg=cyan>A bundle raises order value, which raises profit per order, which makes</>');
            $this->line('  <fg=cyan>every keyword in the account more affordable at once.</>');
        }

        return self::SUCCESS;
    }
}
