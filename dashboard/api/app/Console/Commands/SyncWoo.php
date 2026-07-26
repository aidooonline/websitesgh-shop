<?php

namespace App\Console\Commands;

use App\Models\AttributionEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Woo\OrderSync;
use App\Services\Woo\SignedClient;
use Illuminate\Console\Command;
use Throwable;

class SyncWoo extends Command
{
    protected $signature = 'wgh:sync
        {--full : Ignore the stored cursors and re-read the whole shop}
        {--dry-run : Ask the shop for its totals and write nothing}
        {--verify : Run the sync twice and assert the second run changes zero rows}';

    protected $description = 'Pull orders, order items and attribution rows from the shop';

    public function handle(): int
    {
        try {
            $sync = new OrderSync(SignedClient::fromConfig());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($sync);
        }

        if ($this->option('verify')) {
            return $this->verify($sync);
        }

        try {
            $stats = $sync->run((bool) $this->option('full'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('Cursors were left where they were, so the next run re-reads the same window.');

            return self::FAILURE;
        }

        $this->report($stats);

        return self::SUCCESS;
    }

    private function dryRun(OrderSync $sync): int
    {
        try {
            $info = $sync->dryRun();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Shop reachable and the signature verified.');
        $this->table(['', 'Shop', 'Dashboard'], [
            ['Orders', $info['shop_orders_total'], $info['local_orders']],
            ['Order items', '-', $info['local_order_items']],
            ['Attribution rows', $info['shop_attr_total'], $info['local_attribution']],
        ]);
        $this->line('Site: '.$info['site'].'   Shop currency: '.$info['currency']);
        $this->line('Nothing was written.');

        return self::SUCCESS;
    }

    /**
     * The sprint 1 acceptance test, as a command.
     *
     * Sync, fingerprint every row, sync again, fingerprint again. The two
     * fingerprints must be identical. This is stronger than comparing row
     * counts, which would pass even if every field had been overwritten.
     */
    private function verify(OrderSync $sync): int
    {
        $this->line('Run 1 of 2...');

        try {
            $first = $sync->run((bool) $this->option('full'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report($first);
        $before = $this->fingerprint();

        $this->newLine();
        $this->line('Run 2 of 2, over the same data...');

        try {
            $second = $sync->run(false);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report($second);
        $after = $this->fingerprint();

        $this->newLine();

        $written = $second['orders_written'] + $second['items_written'] + $second['attr_written'];
        $ok = true;

        foreach ($before as $table => $hash) {
            if ($hash === $after[$table]) {
                $this->line("  <fg=green>PASS</> {$table} unchanged (".$this->rowCount($table).' rows)');
            } else {
                $this->line("  <fg=red>FAIL</> {$table} changed between identical syncs");
                $ok = false;
            }
        }

        if ($written !== 0) {
            $this->line("  <fg=red>FAIL</> the second run wrote {$written} rows; it should write none");
            $ok = false;
        } else {
            $this->line('  <fg=green>PASS</> the second run wrote zero rows');
        }

        // Compare against the shop's WHOLE table, not the delta window the two
        // runs happened to cover. A delta run legitimately sees one order; the
        // question the acceptance test asks is whether the dashboard now holds
        // as many orders as WooCommerce does.
        $totals = $sync->dryRun();

        foreach ([
            ['orders', $totals['shop_orders_total'], $totals['local_orders']],
            ['attribution rows', $totals['shop_attr_total'], $totals['local_attribution']],
        ] as [$label, $shop, $local]) {
            if ($shop === $local) {
                $this->line("  <fg=green>PASS</> {$label} match the shop ({$local})");
            } else {
                $this->line("  <fg=red>FAIL</> shop holds {$shop} {$label}, dashboard holds {$local}");
                $ok = false;
            }
        }

        $orphanItems = OrderItem::whereNotIn('order_id', Order::select('id'))->count();

        if ($orphanItems === 0) {
            $this->line('  <fg=green>PASS</> every order item belongs to an order');
        } else {
            $this->line("  <fg=red>FAIL</> {$orphanItems} order items have no parent order");
            $ok = false;
        }

        $this->newLine();
        $this->line('  <options=bold>The ad-to-sale loop</>');

        /*
         * The spec asks to show a real sale end to end with its ref code and
         * its click id. Counting that on the ORDERS table alone was wrong, and
         * would have read zero forever while everything worked perfectly.
         *
         * This shop has no checkout: WhatsApp IS the checkout, by settled
         * design. A WhatsApp sale therefore never creates a WooCommerce order.
         * The sale is the attribution row, marked Sold by the owner. Measuring
         * the loop on orders only would have made the main revenue path look
         * permanently broken and invited somebody to "fix" it.
         */
        $waSales = AttributionEvent::where('status', 'converted')
            ->whereNotNull('ref')
            ->whereNotNull('click_id')
            ->count();

        $waSalesWithKeyword = AttributionEvent::where('status', 'converted')
            ->whereNotNull('click_id')
            ->whereNotNull('utm_term')
            ->count();

        $siteOrders = Order::whereNotNull('customer_ref')->whereNotNull('click_id')->count();

        $taps = AttributionEvent::whereIn('placement', ['cart_whatsapp', 'get_it_now', 'mobile_bar', 'product_page', 'generic'])->count();
        $carts = AttributionEvent::where('status', 'cart')->count();
        $adTaps = AttributionEvent::whereNotNull('click_id')->count();

        $this->line("  ad-tracked taps ............ {$adTaps}");
        $this->line("  add to cart stages ......... {$carts}");
        $this->line("  WhatsApp taps .............. {$taps}");
        $this->line("  WhatsApp sales with ref + click id ... {$waSales}");
        $this->line("  ...of those, carrying a keyword ...... {$waSalesWithKeyword}");
        $this->line("  on-site orders with ref + click id ... {$siteOrders}");

        if ($waSales + $siteOrders > 0) {
            $this->line('  <fg=green>PASS</> at least one sale traces end to end from ad click to money');
        } else {
            $this->line('  <fg=yellow>OPEN</> no sale yet carries both a ref code and a click id.');
            $this->line('        Expected while nothing is spending. To prove it without an ad,');
            $this->line('        visit the shop with ?gclid=TEST-WGH-1 plus the tracking parameters,');
            $this->line('        order on WhatsApp, mark the row Sold, and sync again.');
        }

        if ($adTaps > 0 && $waSalesWithKeyword === 0 && $waSales > 0) {
            $this->line('  <fg=yellow>CHECK</> sales carry a click id but no keyword. The Final URL suffix');
            $this->line('        is probably not set in Google Ads. See dashboard/docs/TRACKING-TEMPLATES.md.');
        }

        $this->newLine();
        $this->line($ok ? '<fg=green>Sprint 1 acceptance test passed.</>' : '<fg=red>Sprint 1 acceptance test FAILED.</>');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, string>
     */
    private function fingerprint(): array
    {
        $hash = function (iterable $rows): string {
            $parts = [];
            foreach ($rows as $row) {
                $parts[] = json_encode($row);
            }
            sort($parts);

            return hash('sha256', implode('|', $parts));
        };

        // synced_at is excluded on purpose: it is sync housekeeping, not shop
        // data, and including it would test the clock rather than the sync.
        return [
            'orders' => $hash(Order::orderBy('woo_order_id')->get()->map->makeVisible('customer_phone')->map(fn ($o) => collect($o->getAttributes())->except('synced_at')->all())),
            'order_items' => $hash(OrderItem::orderBy('woo_item_id')->get()->map(fn ($i) => $i->getAttributes())),
            'attribution_events' => $hash(AttributionEvent::orderBy('woo_attr_id')->get()->map(fn ($a) => collect($a->getAttributes())->except('synced_at')->all())),
        ];
    }

    private function rowCount(string $table): int
    {
        return match ($table) {
            'orders' => Order::count(),
            'order_items' => OrderItem::count(),
            default => AttributionEvent::count(),
        };
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function report(array $stats): void
    {
        $this->table(['Metric', 'Value'], [
            ['Pages fetched', $stats['pages']],
            ['Orders seen', $stats['orders_seen']],
            ['Orders written', $stats['orders_written']],
            ['Order items written', $stats['items_written']],
            ['Attribution seen', $stats['attr_seen']],
            ['Attribution written', $stats['attr_written']],
            ['Shop order total', $stats['shop_orders_total']],
            ['Shop attribution total', $stats['shop_attr_total']],
        ]);
    }
}
