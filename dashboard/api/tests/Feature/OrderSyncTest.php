<?php

namespace Tests\Feature;

use App\Models\AttributionEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SyncState;
use App\Services\Woo\OrderSync;
use App\Services\Woo\SignedClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Sprint 1's contract, expressed as tests.
 *
 * The failure modes named in the spec are each pinned by a test here, so a
 * later sprint cannot quietly reintroduce one. The end-to-end acceptance test
 * against the live shop is `php artisan wgh:sync --verify`; this file is what
 * runs without a shop in front of it.
 */
class OrderSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The payload the fake shop currently returns.
     *
     * Http::fake() MERGES stubs rather than replacing them, so calling it a
     * second time in one test leaves the first stub matching. Everything goes
     * through one closure reading this property instead, which is the only way
     * a two-stage test (sync, change something, sync again) is honest.
     *
     * @var array<string, mixed>
     */
    private array $shop = [];

    private int $shopStatus = 200;

    private function shopReturns(array $payload, int $status = 200): void
    {
        $this->shop = $payload;
        $this->shopStatus = $status;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->payload();
        $this->shopStatus = 200;

        Http::fake(fn () => Http::response($this->shop, $this->shopStatus));
    }

    private function payload(array $orders = [], array $attr = [], bool $moreOrders = false, bool $moreAttr = false): array
    {
        return [
            'ok' => true,
            'schema' => 1,
            'generated_at' => '2026-07-26T10:00:00Z',
            'site' => 'https://shop.websitesgh.com/',
            'currency' => 'GHS',
            'orders' => [
                'rows' => $orders,
                'has_more' => $moreOrders,
                'next_offset' => count($orders),
                'next_cursor' => $orders ? end($orders)['modified_at'] : '',
                'total' => count($orders),
            ],
            'attribution' => [
                'rows' => $attr,
                'has_more' => $moreAttr,
                'next_offset' => count($attr),
                'next_cursor' => $attr ? end($attr)['updated_at'] : '',
                'total' => count($attr),
            ],
        ];
    }

    private function order(array $overrides = []): array
    {
        return array_merge([
            'woo_order_id' => 5001,
            'created_at' => '2026-07-20 09:15:00',
            'modified_at' => '2026-07-20 09:25:00',
            'status' => 'processing',
            'revenue_ghs' => 640.00,
            'currency' => 'GHS',
            'customer_ref' => 'WG-4F7K',
            'click_id' => 'CjwKCAjw000123',
            'click_type' => 'gclid',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'Search-Blenders-Accra',
            'placement' => 'product_page',
            'utm_term' => 'blender price accra',
            'utm_content' => '712345678',
            'match_type' => 'E',
            'campaign_id' => '22334455',
            'adgroup_id' => '99887766',
            'creative_id' => '712345678',
            'target_id' => 'kwd-31415926',
            'network' => 'g',
            'device' => 'm',
            'customer_name' => 'Ama Mensah',
            'customer_phone' => '0542148020',
            'customer_area' => 'East Legon',
            'items' => [
                ['woo_item_id' => 90011, 'woo_product_id' => 1001, 'product_name' => 'Binatone Blender BLG-450', 'qty' => 2, 'unit_price_ghs' => 320.00],
            ],
        ], $overrides);
    }

    private function attr(array $overrides = []): array
    {
        return array_merge([
            'woo_attr_id' => 77,
            'created_at' => '2026-07-20 09:10:00',
            'updated_at' => '2026-07-20 09:10:00',
            'click_id' => 'CjwKCAjw000123',
            'click_type' => 'gclid',
            'product_id' => 1001,
            'product_name' => 'Binatone Blender BLG-450',
            'price_ghs' => 320.00,
            'placement' => 'product_page',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'Search-Blenders-Accra',
            'utm_term' => 'blender price accra',
            'match_type' => 'E',
            'campaign_id' => '22334455',
            'adgroup_id' => '99887766',
            'creative_id' => '712345678',
            'network' => 'g',
            'device' => 'm',
            'cart_items' => '1001:2:640.00,1002:1:180.00',
            'status' => 'pending',
            'converted_at' => '',
            'conv_value_ghs' => 0,
            'order_id' => 0,
            'exported' => false,
            'ref' => 'WG-4F7K',
            'cust_name' => 'Ama Mensah',
            'cust_phone' => '0542148020',
            'cust_area' => 'East Legon',
        ], $overrides);
    }

    private function sync(): OrderSync
    {
        return new OrderSync(new SignedClient(
            baseUrl: 'https://shop.test',
            path: '/wp-json/wghs/v1/export',
            secret: 'test-secret',
        ));
    }

    public function test_a_second_sync_over_unchanged_data_writes_nothing(): void
    {
        $this->shopReturns($this->payload([$this->order()], [$this->attr()]));

        $first = $this->sync()->run();
        $second = $this->sync()->run();

        $this->assertSame(1, $first['orders_written']);
        $this->assertSame(1, $first['items_written']);
        $this->assertSame(1, $first['attr_written']);

        $this->assertSame(0, $second['orders_written'], 're-running the sync must not rewrite an unchanged order');
        $this->assertSame(0, $second['items_written']);
        $this->assertSame(0, $second['attr_written']);

        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItem::count());
        $this->assertSame(1, AttributionEvent::count());
    }

    public function test_a_changed_attribution_row_is_updated_in_place(): void
    {
        $this->shopReturns($this->payload([], [$this->attr()]));
        $this->sync()->run();

        $this->assertSame('pending', AttributionEvent::first()->status);

        // The owner marks the WhatsApp sale as converted.
        $this->shopReturns($this->payload([], [$this->attr([
            'updated_at' => '2026-07-21 11:00:00',
            'status' => 'converted',
            'converted_at' => '2026-07-21 11:00:00',
            'conv_value_ghs' => 640.00,
            'order_id' => 5001,
        ])]));

        $stats = $this->sync()->run();

        $this->assertSame(1, $stats['attr_written']);
        $this->assertSame(1, AttributionEvent::count(), 'an update must not create a second row');
        $this->assertSame('converted', AttributionEvent::first()->status);
        $this->assertSame('640.00', AttributionEvent::first()->conv_value_ghs);
    }

    public function test_the_cursor_does_not_move_when_a_run_fails(): void
    {
        $this->shopReturns($this->payload([$this->order()], [$this->attr()]));
        $this->sync()->run();

        $before = SyncState::forStream('orders')->cursor_at;
        $this->assertNotNull($before);

        $this->shopReturns(['error' => 'gateway down'], 502);

        try {
            $this->sync()->run();
            $this->fail('a failing sync should throw');
        } catch (RuntimeException) {
            // expected
        }

        $state = SyncState::forStream('orders')->fresh();

        $this->assertEquals($before, $state->cursor_at, 'a failed run must leave the cursor where it was');
        $this->assertSame('failed', $state->last_status);
    }

    public function test_two_lines_of_the_same_product_stay_two_rows(): void
    {
        $this->shopReturns($this->payload([$this->order(['items' => [
            ['woo_item_id' => 90011, 'woo_product_id' => 1001, 'product_name' => 'Binatone Blender BLG-450', 'qty' => 1, 'unit_price_ghs' => 320.00],
            ['woo_item_id' => 90012, 'woo_product_id' => 1001, 'product_name' => 'Binatone Blender BLG-450', 'qty' => 1, 'unit_price_ghs' => 320.00],
        ]])]));

        $this->sync()->run();

        $this->assertSame(2, OrderItem::count(), 'keying items on the product id would collapse these and halve the basket');
    }

    public function test_a_removed_order_line_disappears_from_the_dashboard(): void
    {
        $this->shopReturns($this->payload([$this->order(['items' => [
            ['woo_item_id' => 90011, 'woo_product_id' => 1001, 'product_name' => 'Blender', 'qty' => 1, 'unit_price_ghs' => 320.00],
            ['woo_item_id' => 90012, 'woo_product_id' => 1002, 'product_name' => 'Kettle', 'qty' => 1, 'unit_price_ghs' => 180.00],
        ]])]));
        $this->sync()->run();
        $this->assertSame(2, OrderItem::count());

        $this->shopReturns($this->payload([$this->order([
            'modified_at' => '2026-07-20 10:00:00',
            'revenue_ghs' => 320.00,
            'items' => [
                ['woo_item_id' => 90011, 'woo_product_id' => 1001, 'product_name' => 'Blender', 'qty' => 1, 'unit_price_ghs' => 320.00],
            ],
        ])]));
        $this->sync()->run();

        $this->assertSame(1, OrderItem::count(), 'a line removed in WooCommerce must stop counting here');
    }

    public function test_phones_are_normalised_to_e164_and_hashed(): void
    {
        $this->shopReturns($this->payload([$this->order(['customer_phone' => '0542148020'])]));
        $this->sync()->run();

        $order = Order::first()->makeVisible('customer_phone');

        $this->assertSame('+233542148020', $order->customer_phone);
        $this->assertSame(hash('sha256', '+233542148020'), $order->customer_phone_sha256);
    }

    public function test_a_local_and_an_international_form_of_one_number_hash_the_same(): void
    {
        $this->shopReturns($this->payload([
            $this->order(['woo_order_id' => 1, 'customer_phone' => '0542148020']),
            $this->order(['woo_order_id' => 2, 'customer_phone' => '233542148020', 'items' => []]),
        ]));

        $this->sync()->run();

        $hashes = Order::pluck('customer_phone_sha256')->unique();

        $this->assertCount(1, $hashes, 'two spellings of one number must not produce two hashes, or the match rate halves');
    }

    public function test_timestamps_are_read_as_utc(): void
    {
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('Africa/Accra');

        $this->shopReturns($this->payload([$this->order(['created_at' => '2026-07-20 23:45:00'])]));
        $this->sync()->run();

        $this->assertSame('2026-07-20 23:45:00', Order::first()->created_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_profit_stays_null_until_both_costs_are_known(): void
    {
        $this->shopReturns($this->payload([$this->order()]));
        $this->sync()->run();

        $order = Order::first();
        $this->assertNull($order->profit_ghs);

        $order->dealer_cost_ghs = 400.00;
        $this->assertNull($order->recomputeProfit(), 'a half-costed order must not report a profit');

        $order->delivery_cost_ghs = 40.00;
        $this->assertSame('200.00', $order->recomputeProfit());
    }

    public function test_a_bad_secret_gives_a_message_that_names_the_two_likely_causes(): void
    {
        $this->shopReturns(['code' => 'wghs_bad_signature'], 401);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/clock/');

        $this->sync()->run();
    }

public function test_keyword_level_campaign_detail_survives_the_sync(): void
    {
        $this->shopReturns($this->payload([$this->order()], [$this->attr()]));
        $this->sync()->run();

        $event = AttributionEvent::first();

        // Without these there is no exact join in sprint 2, and a gclid can
        // never be resolved back to its keyword from outside Google.
        $this->assertSame('blender price accra', $event->utm_term);
        $this->assertSame('22334455', $event->campaign_id);
        $this->assertSame('99887766', $event->adgroup_id);
        $this->assertSame('712345678', $event->creative_id);
        $this->assertSame('g', $event->network);
        $this->assertSame('m', $event->device);

        $order = Order::first();
        $this->assertSame('blender price accra', $order->utm_term);
        $this->assertSame('22334455', $order->campaign_id);
    }

    public function test_match_type_is_lowercased_so_one_bid_is_not_counted_as_two(): void
    {
        $this->shopReturns($this->payload([$this->order()], [$this->attr()]));
        $this->sync()->run();

        // Google's reports vary the casing. Grouping on a mix of 'E' and 'e'
        // would split one exact-match keyword into two rows that each look
        // half as profitable as the real thing.
        $this->assertSame('e', AttributionEvent::first()->match_type);
        $this->assertSame('e', Order::first()->match_type);
    }

    public function test_a_cart_tap_carries_its_basket(): void
    {
        $this->shopReturns($this->payload([], [$this->attr([
            'placement' => 'cart_whatsapp',
            'product_id' => 1001,
            'price_ghs' => 820.00,
            'cart_items' => '1001:2:640.00,1002:1:180.00',
        ])]));
        $this->sync()->run();

        $event = AttributionEvent::first();

        // The shop is cart first, so this IS the main order path. A blank
        // basket here means revenue can never be attributed to a product.
        $this->assertSame('1001:2:640.00,1002:1:180.00', $event->cart_items);
        $this->assertSame('820.00', $event->price_ghs);
        $this->assertSame(1001, $event->product_id);
    }

    public function test_campaign_detail_is_null_not_empty_string_when_absent(): void
    {
        $this->shopReturns($this->payload([], [$this->attr([
            'utm_term' => '', 'match_type' => '', 'campaign_id' => '',
            'adgroup_id' => '', 'creative_id' => '', 'network' => '', 'device' => '',
        ])]));
        $this->sync()->run();

        $event = AttributionEvent::first();

        // Null means "no ad data". An empty string would group as its own
        // keyword and quietly invent a campaign called nothing.
        $this->assertNull($event->utm_term);
        $this->assertNull($event->match_type);
        $this->assertNull($event->campaign_id);
    }

    public function test_the_canonical_string_ignores_query_argument_order(): void
    {
        $a = SignedClient::canonical('GET', '/wghs/v1/export', ['limit' => 25, 'attr_offset' => 0], '1000', 'n');
        $b = SignedClient::canonical('GET', '/wghs/v1/export', ['attr_offset' => 0, 'limit' => 25], '1000', 'n');

        $this->assertSame($a, $b);
    }

    public function test_the_canonical_string_changes_when_an_argument_changes(): void
    {
        $a = SignedClient::canonical('GET', '/wghs/v1/export', ['limit' => 25], '1000', 'n');
        $b = SignedClient::canonical('GET', '/wghs/v1/export', ['limit' => 26], '1000', 'n');

        $this->assertNotSame($a, $b);
    }

    /* ---------------- the catalogue and its costs ---------------- */

    private function catalogue(array $rows): array
    {
        $empty = ['rows' => [], 'has_more' => false, 'next_cursor' => '', 'next_offset' => 0, 'total' => 0];

        return [
            'ok' => true, 'schema' => 2, 'generated_at' => '2026-07-26T10:00:00Z',
            'site' => 'https://shop.example', 'currency' => 'GHS',
            'orders' => $empty, 'attribution' => $empty,
            'products' => ['rows' => $rows, 'has_more' => false, 'next_cursor' => '',
                'next_offset' => count($rows), 'total' => count($rows)],
        ];
    }

    public function test_a_cost_entered_in_wordpress_reaches_the_dashboard(): void
    {
        /*
         * Three terminal-based ways to enter a dealer cost were built and all
         * three failed on contact with the live server. The owner lives in
         * wp-admin, so the cost is entered there, stored as product meta beside
         * the price it is measured against, and rides in with the catalogue.
         */
        $this->shopReturns($this->catalogue([[
            'product_id' => 36, 'sku' => 'WGH-MW', 'name' => '20L Microwave Oven',
            'price' => '950.00', 'regular' => '950.00', 'stock' => 4, 'in_stock' => 1,
            'dealer_cost' => '700.00', 'delivery_cost' => '25.00',
            'supplier' => 'Tema Depot', 'cost_quoted' => 1,
        ]]));

        (new \App\Services\Woo\CatalogueSync(\App\Services\Woo\SignedClient::fromConfig()))->run();

        $c = \App\Models\ProductCost::where('woo_product_id', 36)->first();

        $this->assertSame('700.00', (string) $c->dealer_cost_ghs);
        $this->assertSame('25.00', (string) $c->delivery_cost_ghs);
        $this->assertSame('Tema Depot', $c->supplier);
        $this->assertFalse($c->is_estimate, 'the quoted box was ticked');
        $this->assertSame(225.0, $c->unitProfit());
    }

    public function test_a_blank_cost_on_the_shop_never_arrives_as_zero(): void
    {
        // A zero dealer cost makes a product look like pure profit and bends
        // every verdict that touches it in the flattering direction.
        $this->shopReturns($this->catalogue([[
            'product_id' => 36, 'name' => 'Microwave', 'price' => '950.00',
            'dealer_cost' => '', 'delivery_cost' => '', 'supplier' => '', 'cost_quoted' => 0,
        ]]));

        (new \App\Services\Woo\CatalogueSync(\App\Services\Woo\SignedClient::fromConfig()))->run();

        $c = \App\Models\ProductCost::where('woo_product_id', 36)->first();

        $this->assertNull($c->dealer_cost_ghs);
        $this->assertNull($c->unitProfit(), 'unknown stays unknown');
    }

    public function test_a_blank_on_the_shop_does_not_wipe_a_cost_typed_here(): void
    {
        // Costs entered with wgh:costs --quick before the wp-admin screen
        // existed must survive the first sync that follows it.
        \App\Models\ProductCost::create([
            'woo_product_id' => 36, 'product_name' => 'Microwave', 'sell_price_ghs' => '950.00',
            'dealer_cost_ghs' => '640.00', 'delivery_cost_ghs' => '25.00',
            'is_estimate' => true, 'updated_at' => \Carbon\CarbonImmutable::now('UTC'),
        ]);

        $this->shopReturns($this->catalogue([[
            'product_id' => 36, 'name' => 'Microwave', 'price' => '950.00',
            'dealer_cost' => '', 'delivery_cost' => '', 'supplier' => '', 'cost_quoted' => 0,
        ]]));

        (new \App\Services\Woo\CatalogueSync(\App\Services\Woo\SignedClient::fromConfig()))->run();

        $this->assertSame('640.00', (string) \App\Models\ProductCost::where('woo_product_id', 36)->first()->dealer_cost_ghs);
    }

    public function test_a_cost_on_the_shop_wins_over_one_typed_here(): void
    {
        // One place to change a cost, not two that can disagree.
        \App\Models\ProductCost::create([
            'woo_product_id' => 36, 'product_name' => 'Microwave', 'sell_price_ghs' => '950.00',
            'dealer_cost_ghs' => '640.00', 'is_estimate' => true, 'updated_at' => \Carbon\CarbonImmutable::now('UTC'),
        ]);

        $this->shopReturns($this->catalogue([[
            'product_id' => 36, 'name' => 'Microwave', 'price' => '950.00',
            'dealer_cost' => '705.50', 'delivery_cost' => '30.00', 'supplier' => '', 'cost_quoted' => 0,
        ]]));

        (new \App\Services\Woo\CatalogueSync(\App\Services\Woo\SignedClient::fromConfig()))->run();

        $this->assertSame('705.50', (string) \App\Models\ProductCost::where('woo_product_id', 36)->first()->dealer_cost_ghs);
    }
}
