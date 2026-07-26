<?php

namespace Tests\Feature;

use App\Models\AttributionEvent;
use App\Models\Customer;
use App\Models\FxRate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductCost;
use App\Models\ProductPair;
use App\Services\Costs\CostSheet;
use App\Services\Costs\ProfitEngine;
use App\Services\Customers\CustomerInsights;
use App\Services\Decisions\VerdictEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The value side of the equation.
 *
 * Profit per order is the number every KEEP and every KILL is compared
 * against. It was a constant in a config file. These tests pin the behaviour
 * that makes it measured instead, and the refusals that keep it honest.
 */
class ProfitAndCustomersTest extends TestCase
{
    use RefreshDatabase;

    private function order(int $id, array $lines, string $at = '2026-07-10 10:00:00', ?string $phone = null): Order
    {
        $revenue = 0.0;
        foreach ($lines as [$pid, $qty, $unit]) {
            $revenue += $qty * $unit;
        }

        $o = Order::create([
            'woo_order_id' => $id,
            'created_at' => $at,
            'status' => 'processing',
            'revenue_ghs' => number_format($revenue, 2, '.', ''),
            'currency' => 'GHS',
            'customer_phone_sha256' => $phone ? hash('sha256', $phone) : null,
            'customer_area' => $phone ? 'East Legon' : null,
            'payload_hash' => hash('sha256', (string) $id),
            'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        foreach ($lines as $i => [$pid, $qty, $unit]) {
            OrderItem::create([
                'order_id' => $o->id,
                'woo_item_id' => $id * 100 + $i,
                'woo_product_id' => $pid,
                'product_name' => 'Product '.$pid,
                'qty' => $qty,
                'unit_price_ghs' => number_format($unit, 2, '.', ''),
                'payload_hash' => hash('sha256', $id.'-'.$i),
            ]);
        }

        return $o;
    }

    private function cost(int $pid, ?float $dealer, float $price = 400.0, float $delivery = 30.0): void
    {
        ProductCost::create([
            'woo_product_id' => $pid,
            'product_name' => 'Product '.$pid,
            'sell_price_ghs' => $price,
            'dealer_cost_ghs' => $dealer,
            'delivery_cost_ghs' => $delivery,
            'is_estimate' => true,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    /* ---------------- the cost sheet ---------------- */

    public function test_the_sheet_leads_with_what_sold_then_what_is_advertised(): void
    {
        /*
         * Seeded only from what had already sold, the sheet came out with three
         * rows on a fifty product shop, and they were the wrong three: a product
         * pulling ad taps and closing none of them is the one that cannot be
         * judged without a cost, because there is no way to tell a bad price
         * from a bad product.
         */
        $this->order(1, [[700, 1, 400.0]]);          // sold

        AttributionEvent::create([                    // advertised, never sold
            'woo_attr_id' => 1, 'created_at' => '2026-07-05 10:00:00', 'updated_at' => '2026-07-05 10:00:00',
            'status' => 'pending', 'product_id' => 800, 'price_ghs' => '500.00',
            'payload_hash' => hash('sha256', 'tap'), 'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        ProductCost::create([                         // catalogue only
            'woo_product_id' => 900, 'product_name' => 'Never Touched',
            'sell_price_ghs' => 300, 'is_estimate' => true, 'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        $dir = sys_get_temp_dir().'/wgh-sheet-order';
        $r = (new CostSheet)->export($dir);

        $this->assertSame(3, $r['rows']);
        $this->assertSame(1, $r['sold']);
        $this->assertSame(1, $r['advertised_only']);
        $this->assertSame(2, $r['needed_now'], 'the catalogue row is not urgent, the other two are');

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($r['path']))));
        $ids = array_map(fn ($l) => (int) str_getcsv($l)[0], array_slice($lines, 1));

        $this->assertSame([700, 800, 900], $ids, 'sold, then advertised, then the rest');
        $this->assertStringContainsString('SOLD 1 unit', $lines[1]);
        $this->assertStringContainsString('no sale yet', $lines[2]);
    }

    public function test_the_extra_guidance_column_does_not_break_the_import(): void
    {
        // The column exists for the human filling the sheet in. Import reads by
        // header name, so it must simply ignore anything it does not know.
        $path = sys_get_temp_dir().'/wgh-costs-extra.csv';
        file_put_contents($path,
            "product_id,product_name,sell_price_ghs,dealer_cost_ghs,delivery_cost_ghs,supplier,confirmed,why_this_matters\n"
            ."100,Blender,400.00,250.00,30.00,Tema Depot,yes,SOLD 4 units. Cost this one first.\n");

        $r = (new CostSheet)->import($path);

        $this->assertSame(1, $r['saved']);
        $this->assertSame(1, $r['complete']);
        $this->assertSame('250.00', (string) ProductCost::first()->dealer_cost_ghs);
    }

    public function test_a_price_change_does_not_restate_last_months_profit(): void
    {
        /*
         * The catalogue pull refreshes the shelf price, because a price change
         * moves the margin without anybody touching the cost. But historical
         * profit measured against TODAY's shelf price restates the past at a
         * price nobody paid, and it lands beside a revenue figure that came
         * from real order lines. The two would disagree on the same row.
         */
        $this->order(1, [[100, 2, 400.0]]);      // charged 400 each
        $this->cost(100, 250.0, 400.0, 30.0);    // shelf 400, cost 280

        // The shop raises the price. The catalogue pull writes it through.
        ProductCost::where('woo_product_id', 100)->update(['sell_price_ghs' => 600.00]);

        $rows = (new ProfitEngine)->productMargins('2026-07-01', '2026-07-28');

        $this->assertSame('800.00', $rows[0]['revenue_ghs']);
        $this->assertSame('120.00', $rows[0]['unit_profit_ghs'], '400 charged less 280 of cost');
        $this->assertSame('240.00', $rows[0]['total_profit_ghs']);
        $this->assertSame(30.0, $rows[0]['margin_percent']);
    }

    /* ---------------- profit ---------------- */

    public function test_a_basket_with_one_uncosted_line_produces_no_profit_figure(): void
    {
        // Half a margin reported as a whole margin is worse than no margin. It
        // looks like knowledge and it is wrong in the flattering direction,
        // which inflates profit per order and keeps bad keywords alive.
        $this->order(1, [[100, 1, 400.0], [999, 1, 300.0]]);
        $this->cost(100, 250.0);
        // Product 999 has no cost at all.

        $p = (new ProfitEngine)->profitPerOrder('2026-07-01', '2026-07-28');

        $this->assertNull($p['profit_per_order_ghs']);
        $this->assertSame('assumed', $p['source']);
        $this->assertSame(1, $p['coverage']['baskets_total']);
        $this->assertSame(0, $p['coverage']['baskets_fully_costed']);
    }

    public function test_a_blank_cost_is_never_read_as_zero(): void
    {
        $path = sys_get_temp_dir().'/wgh-costs-blank.csv';
        file_put_contents($path, "product_id,product_name,sell_price_ghs,dealer_cost_ghs,delivery_cost_ghs,supplier,confirmed\n"
            ."100,Blender,400.00,,,,\n");

        (new CostSheet)->import($path);

        // A zero cost would make the product pure profit and bend every verdict.
        $this->assertNull(ProductCost::first()->dealer_cost_ghs);
        $this->assertNull(ProductCost::first()->unitProfit());
    }

    public function test_profit_is_measured_from_real_baskets_and_real_costs(): void
    {
        $this->order(1, [[100, 2, 400.0]]);          // 800 revenue
        $this->order(2, [[100, 1, 400.0]]);          // 400 revenue
        $this->cost(100, 250.0, 400.0, 30.0);        // unit cost 280, unit profit 120

        $p = (new ProfitEngine)->profitPerOrder('2026-07-01', '2026-07-28');

        // Basket 1: 800 - 560 = 240. Basket 2: 400 - 280 = 120. Mean 180.
        $this->assertSame('180.00', $p['profit_per_order_ghs']);
        $this->assertSame('measured', $p['source']);
    }

    public function test_a_measurable_margin_with_no_fx_rate_says_so_rather_than_blaming_thin_data(): void
    {
        // Two different failures once wore the same message. One means "enter
        // more costs", the other means "record an exchange rate". Sending the
        // owner to do the wrong one wastes an afternoon and changes nothing.
        $this->order(1, [[100, 1, 400.0]]);
        $this->cost(100, 250.0);

        $t = (new ProfitEngine)->judgingThreshold('2026-07-01', '2026-07-28');

        $this->assertSame('assumed', $t['source']);
        $this->assertStringContainsString('exchange rate', $t['explanation']);
        $this->assertStringContainsString('wgh:fx', $t['explanation']);
    }

    public function test_the_measured_margin_becomes_the_judging_threshold(): void
    {
        $this->order(1, [[100, 1, 400.0]]);
        $this->cost(100, 250.0, 400.0, 30.0);   // 120 GHS profit
        FxRate::create(['rate_date' => '2026-07-15', 'ghs_per_usd' => 12.0, 'source' => 'test', 'created_at' => CarbonImmutable::now('UTC')]);

        $t = (new ProfitEngine)->judgingThreshold('2026-07-01', '2026-07-28');

        $this->assertSame('measured', $t['source']);
        $this->assertSame(10.0, round($t['value_usd'], 2));   // 120 / 12
    }

    public function test_a_keyword_changes_side_when_the_real_margin_lands(): void
    {
        // The whole point. At the assumed $8.75 this keyword loses money on
        // every order. At a measured $20 it is the best thing in the account,
        // and not one bid changed.
        $row = [
            'keyword' => 'blender price accra', 'match_type' => 'e', 'campaign' => 'c',
            'spend_usd' => '60.00', 'clicks' => 200, 'carts' => 5, 'taps' => 9,
            'orders' => 5, 'revenue_ghs' => '3000.00', 'days' => 28, 'join_strength' => 'click_id',
        ];   // cost per order = $12

        $assumed = (new VerdictEngine)->judge($row);
        $this->assertSame('fix', $assumed['verdict'], 'at $8.75 profit, $12 an order loses money');

        $measured = (new VerdictEngine([
            'value_usd' => 20.0, 'source' => 'measured', 'explanation' => 'measured',
        ]))->judge($row);

        $this->assertSame('keep', $measured['verdict'], 'at $20 profit, $12 an order clears $8');
        $this->assertSame('measured', $measured['evidence']['profit_per_order_source']);
        $this->assertFalse($measured['evidence']['profit_per_order_is_estimated']);
    }

    public function test_every_verdict_records_whether_the_margin_was_measured_or_guessed(): void
    {
        // A KILL decided against a guess and a KILL decided against a measured
        // margin are not the same claim, and nobody will remember which later.
        $row = ['keyword' => 'k', 'match_type' => 'b', 'campaign' => 'c', 'spend_usd' => '60.00',
            'clicks' => 200, 'carts' => 0, 'taps' => 0, 'orders' => 0, 'revenue_ghs' => '0.00',
            'days' => 28, 'join_strength' => 'none'];

        $this->assertSame('assumed', (new VerdictEngine)->judge($row)['evidence']['profit_per_order_source']);
    }

    public function test_a_dealer_cost_at_or_above_the_selling_price_is_flagged(): void
    {
        $path = sys_get_temp_dir().'/wgh-costs-typo.csv';
        file_put_contents($path, "product_id,product_name,sell_price_ghs,dealer_cost_ghs,delivery_cost_ghs,supplier,confirmed\n"
            ."100,Blender,400.00,450.00,30.00,Acme,\n");

        $r = (new CostSheet)->import($path);

        // Silently accepting it would turn a healthy product into a KILL on a
        // single keystroke.
        $this->assertNotEmpty($r['problems']);
        $this->assertStringContainsString('typo', $r['problems'][0]);
    }

    public function test_the_cost_sheet_round_trips(): void
    {
        $this->order(1, [[100, 1, 400.0], [101, 2, 250.0]]);

        $dir = sys_get_temp_dir().'/wgh-cost-rt';
        $export = (new CostSheet)->export($dir);

        $this->assertFileExists($export['path']);

        $csv = (string) file_get_contents($export['path']);
        $this->assertStringContainsString('dealer_cost_ghs', $csv);
        // The observed price is pre-filled so only the supplier's numbers need typing.
        $this->assertStringContainsString('400.00', $csv);

        file_put_contents($export['path'], str_replace(
            "100,\"Product 100\",400.00,,,,",
            "100,\"Product 100\",400.00,250.00,30.00,Acme,yes",
            $csv
        ));

        $r = (new CostSheet)->import($export['path']);

        $this->assertGreaterThanOrEqual(1, $r['saved']);
        $saved = ProductCost::where('woo_product_id', 100)->first();
        $this->assertSame('250.00', (string) $saved->dealer_cost_ghs);
        $this->assertFalse($saved->is_estimate, 'confirmed=yes must clear the estimate flag');

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    /* ---------------- customers ---------------- */

    private function sale(int $id, string $phone, string $at, float $value, string $area = 'Osu'): void
    {
        AttributionEvent::create([
            'woo_attr_id' => $id,
            'created_at' => $at,
            'updated_at' => $at,
            'status' => 'converted',
            'converted_at' => $at,
            'conv_value_ghs' => number_format($value, 2, '.', ''),
            'cust_phone' => $phone,
            'cust_phone_sha256' => hash('sha256', $phone),
            'cust_area' => $area,
            'cust_name' => 'Buyer '.$id,
            'utm_source' => 'meta',
            'payload_hash' => hash('sha256', (string) $id),
            'synced_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function test_one_sale_seen_from_both_sides_is_counted_once(): void
    {
        /*
         * A WhatsApp sale marked converted in the shop AND written as a
         * WooCommerce order is one sale seen twice: once from the ad side, once
         * from the till. Counting both put GHS 33,789 of lifetime revenue into
         * a single delivery area in a period whose entire turnover was GHS
         * 10,114, and inflated the repeat rate with it.
         */
        $phone = '+233542148020';
        $order = $this->order(77, [[200, 1, 500.0]], '2026-07-01 10:00:00', $phone);

        AttributionEvent::create([
            'woo_attr_id' => 90,
            'created_at' => '2026-07-01 10:00:00',
            'updated_at' => '2026-07-01 10:00:00',
            'status' => 'converted',
            'converted_at' => '2026-07-01 10:00:00',
            'conv_value_ghs' => '500.00',
            'order_id' => $order->woo_order_id,
            'cust_phone' => $phone,
            'cust_phone_sha256' => hash('sha256', $phone),
            'cust_area' => 'Osu',
            'payload_hash' => hash('sha256', 'both-sides'),
            'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        (new CustomerInsights)->rebuild();

        $c = Customer::where('phone_sha256', hash('sha256', $phone))->first();

        $this->assertSame(1, $c->orders_count, 'one sale, not two');
        $this->assertSame('500.00', (string) $c->lifetime_revenue_ghs, 'the money is counted once');
        $this->assertFalse($c->isRepeat(), 'a sale seen from two angles is not a returning customer');
    }

    public function test_two_products_sharing_a_name_get_two_verdicts(): void
    {
        /*
         * Variants share a name and names get edited in WooCommerce. Keyed on
         * the name, one product's verdict overwrote the other's, and a rendered
         * report showed the same blender twice with two different margins and
         * two different verdicts beside them.
         */
        OrderItem::where('woo_product_id', 500)->delete();

        $this->order(1, [[500, 1, 400.0]]);
        $this->order(2, [[501, 1, 400.0]]);

        // Same shelf name, two different products in Woo.
        OrderItem::query()->update(['product_name' => 'Binatone Blender']);

        $this->cost(500, 250.0, 400.0, 30.0);   // healthy
        $this->cost(501, 395.0, 400.0, 30.0);   // loses money on every unit

        $rows = (new ProfitEngine)->productMargins('2026-07-01', '2026-07-28');
        (new VerdictEngine)->judgeProducts($rows);

        $refs = \App\Models\Decision::where('dimension', 'product')->pluck('entity_ref')->all();
        sort($refs);

        $this->assertSame(['Binatone Blender #500', 'Binatone Blender #501'], $refs);
        $this->assertSame(
            ['keep', 'kill'],
            \App\Models\Decision::where('dimension', 'product')->orderBy('entity_ref')->pluck('verdict')->all(),
            'the healthy one and the loss-making one keep their own verdicts'
        );
    }

    public function test_a_cancelled_order_does_not_make_someone_a_customer(): void
    {
        $phone = '+233209998888';
        $good = $this->order(1, [[200, 1, 400.0]], '2026-07-01 10:00:00', $phone);
        $bad = $this->order(2, [[200, 1, 900.0]], '2026-07-09 10:00:00', $phone);
        $bad->forceFill(['status' => 'cancelled'])->save();

        (new CustomerInsights)->rebuild();

        $c = Customer::where('phone_sha256', hash('sha256', $phone))->first();

        $this->assertSame(1, $c->orders_count);
        $this->assertSame('400.00', (string) $c->lifetime_revenue_ghs, 'a cancelled order is not revenue');
        $this->assertFalse($c->isRepeat(), 'a cancelled second order is not a return');
        $this->assertNotNull($good);
    }

    public function test_a_second_order_on_the_same_day_is_not_a_return(): void
    {
        // A forgotten item or a split delivery, not a customer who came back.
        // Counting it as one reported a median reorder gap of zero days, which
        // would send a follow-up message before the customer had left.
        $phone = '+233201234567';
        $this->sale(1, $phone, '2026-07-01 10:00:00', 400);
        $this->sale(2, $phone, '2026-07-01 16:30:00', 200);

        (new CustomerInsights)->rebuild();

        $c = Customer::where('phone_sha256', hash('sha256', $phone))->first();
        $this->assertSame(2, $c->orders_count);
        $this->assertSame(1, $c->order_days_count);
        $this->assertFalse($c->isRepeat());
        $this->assertNull($c->days_to_second_order, 'there is no reorder window to aim at yet');

        $this->assertSame(0.0, (float) (new CustomerInsights)->summary()['repeat_rate']);
    }

    public function test_two_sales_from_one_number_are_one_repeat_customer(): void
    {
        $this->sale(1, '+233542148020', '2026-07-01 10:00:00', 400);
        $this->sale(2, '+233542148020', '2026-07-19 10:00:00', 600);
        $this->sale(3, '+233201111111', '2026-07-05 10:00:00', 300);

        (new CustomerInsights)->rebuild();

        $this->assertSame(2, Customer::count());

        $repeat = Customer::where('phone_sha256', hash('sha256', '+233542148020'))->first();
        $this->assertSame(2, $repeat->orders_count);
        $this->assertSame('1000.00', (string) $repeat->lifetime_revenue_ghs);
        $this->assertSame(18, $repeat->days_to_second_order, 'the gap decides when a follow-up is timely');

        $s = (new CustomerInsights)->summary();
        $this->assertSame(2, $s['buyers']);
        $this->assertSame(1, $s['repeat_buyers']);
        $this->assertSame(50.0, $s['repeat_rate']);
    }

    public function test_the_repeat_rate_travels_with_how_much_of_the_business_it_covers(): void
    {
        // A repeat rate computed on a third of buyers is not a repeat rate.
        $this->sale(1, '+233542148020', '2026-07-01 10:00:00', 400);

        AttributionEvent::create([
            'woo_attr_id' => 2, 'created_at' => '2026-07-02 10:00:00', 'updated_at' => '2026-07-02 10:00:00',
            'status' => 'converted', 'converted_at' => '2026-07-02 10:00:00', 'conv_value_ghs' => '500.00',
            'payload_hash' => hash('sha256', 'anon'), 'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        (new CustomerInsights)->rebuild();
        $s = (new CustomerInsights)->summary();

        $this->assertNotNull($s['identified_share']);
        $this->assertLessThan(100, $s['identified_share']);
    }

    public function test_areas_are_grouped_case_insensitively(): void
    {
        $this->sale(1, '+233201111111', '2026-07-01 10:00:00', 400, 'East Legon');
        $this->sale(2, '+233202222222', '2026-07-02 10:00:00', 600, 'east legon');

        (new CustomerInsights)->rebuild();
        $areas = (new CustomerInsights)->byArea();

        $this->assertCount(1, $areas, '"East Legon" and "east legon" are one place');
        $this->assertSame(2, $areas[0]['buyers']);
        $this->assertSame('1000.00', $areas[0]['revenue_ghs']);
    }

    public function test_bundles_are_found_by_lift_not_by_raw_popularity(): void
    {
        // 200 is in every basket, so it pairs with everything. That is not a
        // finding: bundling on it wastes the offer. 300 and 301 appear together
        // only with each other, which is.
        $this->order(1, [[200, 1, 100.0], [300, 1, 100.0], [301, 1, 100.0]]);
        $this->order(2, [[200, 1, 100.0], [300, 1, 100.0], [301, 1, 100.0]]);
        $this->order(3, [[200, 1, 100.0], [300, 1, 100.0], [301, 1, 100.0]]);
        $this->order(4, [[200, 1, 100.0], [400, 1, 100.0]]);
        $this->order(5, [[200, 1, 100.0], [401, 1, 100.0]]);

        (new CustomerInsights)->rebuildPairs();
        $bundles = (new CustomerInsights)->bundleCandidates();

        $this->assertNotEmpty($bundles);

        $top = $bundles[0];
        $names = [$top['a'], $top['b']];
        sort($names);
        $this->assertSame(['Product 300', 'Product 301'], $names);
        $this->assertGreaterThan(1.2, (float) $top['lift']);
    }

    public function test_a_high_lift_pair_seen_twice_is_not_offered_as_a_bundle(): void
    {
        /*
         * The bug this catches shipped into a rendered report: two pairs at
         * 10.889x lift on two baskets each, ranked ABOVE a pair seen thirty-two
         * times. Lift is a ratio, so rarity alone can produce a huge score, and
         * a bundle discount built on a coincidence costs real margin.
         */
        for ($i = 1; $i <= 12; $i++) {
            $this->order($i, [[200, 1, 100.0], [201, 1, 100.0], [202, 1, 100.0]]);
        }

        // Seen together exactly twice, and nowhere else, so lift is enormous.
        $this->order(13, [[900, 1, 100.0], [901, 1, 100.0]]);
        $this->order(14, [[900, 1, 100.0], [901, 1, 100.0]]);

        (new CustomerInsights)->rebuildPairs();

        // The pair is real and its lift is enormous, so lift alone would rank
        // it first.
        $pair = ProductPair::where('product_a', 900)->where('product_b', 901)->first();
        $this->assertNotNull($pair);
        $this->assertGreaterThan(5, (float) $pair->lift);

        $offered = (new CustomerInsights)->bundleCandidates();

        foreach ($offered as $b) {
            $this->assertGreaterThanOrEqual(3, $b['together'], 'a two-basket coincidence is not a bundle');
        }

        $this->assertSame([], array_values(array_filter(
            $offered,
            fn ($b) => $b['a'] === 'Product 900' || $b['b'] === 'Product 900'
        )), 'seen twice is not evidence, however high the lift');
    }

    public function test_bundles_are_not_reported_from_a_handful_of_baskets(): void
    {
        $this->order(1, [[200, 1, 100.0], [300, 1, 100.0]]);

        $this->assertSame(0, (new CustomerInsights)->rebuildPairs(), 'two products in one basket is anecdote');
    }
}
