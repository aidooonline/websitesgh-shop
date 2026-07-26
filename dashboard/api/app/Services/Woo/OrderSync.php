<?php

namespace App\Services\Woo;

use App\Models\AttributionEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SyncRun;
use App\Models\SyncState;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The WooCommerce connector.
 *
 * Four properties, in the order they matter:
 *
 * 1. IDEMPOTENT. woo_order_id, woo_item_id and woo_attr_id are unique. Every
 *    write is guarded by a content hash, so a second run over unchanged data
 *    performs zero writes, not a no-op UPDATE. The acceptance test is literal:
 *    run it twice, count the changed rows, expect zero.
 *
 * 2. TRANSACTIONAL PER PAGE. A page of records commits or it does not. A drop
 *    mid-run leaves the database consistent and the cursor untouched.
 *
 * 3. CURSOR ADVANCES ONLY ON FULL SUCCESS. If page nine of twelve fails, the
 *    cursor stays where it was and the next run re-pulls from there. Re-pulling
 *    is free because of property 1. Advancing early is how deltas lose rows.
 *
 * 4. INCLUSIVE, OVERLAPPED CURSOR. The shop returns rows at or after the
 *    cursor and the cursor is rewound a couple of minutes on each run. An
 *    exclusive cursor loses every row written in the same second as the last
 *    row of a page. Overlap costs nothing here and closes that gap for good.
 */
class OrderSync
{
    private int $pageSize;

    private int $maxPages;

    private int $overlap;

    public function __construct(private readonly SignedClient $client)
    {
        $this->pageSize = max(1, min(500, (int) config('wgh.shop.page_size', 200)));
        $this->maxPages = max(1, (int) config('wgh.shop.max_pages', 200));
        $this->overlap = max(0, (int) config('wgh.shop.cursor_overlap', 120));
    }

    /**
     * Ask the shop for its totals without writing anything.
     *
     * This is the fastest way to tell a broken sync from an empty shop, which
     * is a distinction that has wasted time on every project that skipped it.
     *
     * @return array<string, mixed>
     */
    public function dryRun(): array
    {
        $payload = $this->client->fetch(['limit' => 1]);

        return [
            'site' => $payload['site'] ?? '',
            'generated_at' => $payload['generated_at'] ?? '',
            'currency' => $payload['currency'] ?? '',
            'shop_orders_total' => (int) ($payload['orders']['total'] ?? 0),
            'shop_attr_total' => (int) ($payload['attribution']['total'] ?? 0),
            'local_orders' => Order::count(),
            'local_order_items' => OrderItem::count(),
            'local_attribution' => AttributionEvent::count(),
        ];
    }

    /**
     * Run a full sync.
     *
     * @param  bool  $full  Ignore the stored cursors and re-read everything.
     * @return array<string, mixed>
     */
    public function run(bool $full = false): array
    {
        $ordersState = SyncState::forStream('orders');
        $attrState = SyncState::forStream('attribution');

        $run = SyncRun::create([
            'started_at' => CarbonImmutable::now('UTC'),
            'status' => 'running',
        ]);

        $ordersCursor = $full ? null : $this->rewind($ordersState->cursor_at);
        $attrCursor = $full ? null : $this->rewind($attrState->cursor_at);
        $ordersOffset = 0;
        $attrOffset = 0;

        $stats = [
            'pages' => 0,
            'orders_seen' => 0,
            'orders_written' => 0,
            'items_written' => 0,
            'attr_seen' => 0,
            'attr_written' => 0,
            'shop_orders_total' => 0,
            'shop_attr_total' => 0,
        ];

        // Only ever moved into the state rows at the very end, on full success.
        $nextOrdersCursor = $ordersState->cursor_at;
        $nextAttrCursor = $attrState->cursor_at;

        $now = CarbonImmutable::now('UTC');
        $ordersState->forceFill(['last_run_at' => $now])->save();
        $attrState->forceFill(['last_run_at' => $now])->save();

        try {
            $moreOrders = true;
            $moreAttr = true;

            while (($moreOrders || $moreAttr) && $stats['pages'] < $this->maxPages) {
                // One stream usually finishes paging before the other. Drop
                // the finished one from the request rather than re-reading
                // rows only to throw them away.
                $streams = [];
                $query = ['limit' => $this->pageSize];

                if ($moreOrders) {
                    $streams[] = 'orders';
                    $query['orders_offset'] = $ordersOffset;
                    if ($ordersCursor) {
                        $query['orders_since'] = $ordersCursor;
                    }
                }

                if ($moreAttr) {
                    $streams[] = 'attribution';
                    $query['attr_offset'] = $attrOffset;
                    if ($attrCursor) {
                        $query['attr_since'] = $attrCursor;
                    }
                }

                $query['streams'] = implode(',', $streams);

                $payload = $this->client->fetch($query);
                $stats['pages']++;

                // A skipped stream reports a total of zero, which is not the
                // same as the shop holding zero rows. Keep the last real one.
                if ($moreOrders) {
                    $stats['shop_orders_total'] = (int) ($payload['orders']['total'] ?? 0);
                }
                if ($moreAttr) {
                    $stats['shop_attr_total'] = (int) ($payload['attribution']['total'] ?? 0);
                }

                $orderRows = (array) ($payload['orders']['rows'] ?? []);
                $attrRows = (array) ($payload['attribution']['rows'] ?? []);

                // One transaction per page. Either the whole page lands or
                // none of it does, and the cursor has not moved either way.
                DB::transaction(function () use ($orderRows, $attrRows, &$stats) {
                    foreach ($orderRows as $row) {
                        $stats['orders_seen']++;
                        $result = $this->upsertOrder($row);
                        $stats['orders_written'] += $result['order'];
                        $stats['items_written'] += $result['items'];
                    }

                    foreach ($attrRows as $row) {
                        $stats['attr_seen']++;
                        $stats['attr_written'] += $this->upsertAttribution($row);
                    }
                });

                // The since-cursor is held FIXED for the whole run and paging
                // is done with an offset. Moving the timestamp between pages
                // is what stalls a sync when a full page shares one second.
                if ($moreOrders) {
                    $cursor = (string) ($payload['orders']['next_cursor'] ?? '');
                    if ($cursor !== '') {
                        $nextOrdersCursor = CarbonImmutable::parse($cursor, 'UTC');
                    }
                    $ordersOffset = (int) ($payload['orders']['next_offset'] ?? $ordersOffset + count($orderRows));
                    $moreOrders = (bool) ($payload['orders']['has_more'] ?? false);
                }

                if ($moreAttr) {
                    $cursor = (string) ($payload['attribution']['next_cursor'] ?? '');
                    if ($cursor !== '') {
                        $nextAttrCursor = CarbonImmutable::parse($cursor, 'UTC');
                    }
                    $attrOffset = (int) ($payload['attribution']['next_offset'] ?? $attrOffset + count($attrRows));
                    $moreAttr = (bool) ($payload['attribution']['has_more'] ?? false);
                }

                if ($orderRows === [] && $attrRows === []) {
                    break;
                }
            }

            if ($stats['pages'] >= $this->maxPages && ($moreOrders || $moreAttr)) {
                throw new \RuntimeException(
                    "Stopped at the {$this->maxPages} page ceiling with more data waiting. "
                    .'Raise WGH_SYNC_MAX_PAGES, or check the shop is advancing its cursor.'
                );
            }

            // Full success. Only now does the cursor move.
            $done = CarbonImmutable::now('UTC');
            $ordersState->forceFill([
                'cursor_at' => $nextOrdersCursor,
                'last_success_at' => $done,
                'last_status' => 'ok',
                'last_error' => null,
                'rows_seen' => $ordersState->rows_seen + $stats['orders_seen'],
                'rows_written' => $ordersState->rows_written + $stats['orders_written'],
            ])->save();

            $attrState->forceFill([
                'cursor_at' => $nextAttrCursor,
                'last_success_at' => $done,
                'last_status' => 'ok',
                'last_error' => null,
                'rows_seen' => $attrState->rows_seen + $stats['attr_seen'],
                'rows_written' => $attrState->rows_written + $stats['attr_written'],
            ])->save();

            $run->forceFill($stats + [
                'finished_at' => $done,
                'status' => 'ok',
            ])->save();

            Log::channel('sync')->info('wgh:sync ok', $stats);

            return $stats + ['status' => 'ok', 'run_id' => $run->id];
        } catch (Throwable $e) {
            $failed = CarbonImmutable::now('UTC');

            // Cursors are left exactly where they were. The next run re-pulls
            // the same window, which is safe because every write is guarded.
            foreach ([$ordersState, $attrState] as $state) {
                $state->forceFill([
                    'last_status' => 'failed',
                    'last_error' => $e->getMessage(),
                ])->save();
            }

            $run->forceFill($stats + [
                'finished_at' => $failed,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ])->save();

            Log::channel('sync')->error('wgh:sync failed: '.$e->getMessage(), $stats);

            throw $e;
        }
    }

    /**
     * Rewind a stored cursor by the overlap window.
     */
    private function rewind(?DateTimeInterface $cursor): ?string
    {
        if (! $cursor) {
            return null;
        }

        // Eloquent's datetime cast hands back Illuminate\Support\Carbon, not
        // CarbonImmutable, so this takes the interface rather than one class.
        return CarbonImmutable::instance($cursor)
            ->setTimezone('UTC')
            ->subSeconds($this->overlap)
            ->format('Y-m-d H:i:s');
    }

    /**
     * Upsert one order and its items.
     *
     * @param  array<string, mixed>  $row
     * @return array{order: int, items: int} How many rows were actually written.
     */
    private function upsertOrder(array $row): array
    {
        $wooId = (int) ($row['woo_order_id'] ?? 0);

        if ($wooId <= 0) {
            return ['order' => 0, 'items' => 0];
        }

        $phone = $this->normalisePhone((string) ($row['customer_phone'] ?? ''));

        $attributes = $this->campaignFields($row) + [
            'woo_order_id' => $wooId,
            'created_at' => $this->utc($row['created_at'] ?? null),
            'woo_modified_at' => $this->utc($row['modified_at'] ?? null),
            'status' => (string) ($row['status'] ?? 'unknown'),
            'revenue_ghs' => $this->money($row['revenue_ghs'] ?? 0),
            'currency' => (string) ($row['currency'] ?: 'GHS'),
            'customer_ref' => $this->nullIfBlank($row['customer_ref'] ?? null),
            'click_id' => $this->nullIfBlank($row['click_id'] ?? null),
            'click_type' => $this->nullIfBlank($row['click_type'] ?? null),
            'utm_source' => $this->nullIfBlank($row['utm_source'] ?? null),
            'utm_medium' => $this->nullIfBlank($row['utm_medium'] ?? null),
            'utm_campaign' => $this->nullIfBlank($row['utm_campaign'] ?? null),
            'placement' => $this->nullIfBlank($row['placement'] ?? null),
            'customer_name' => $this->nullIfBlank($row['customer_name'] ?? null),
            'customer_phone' => $phone,
            // Hashed here, on the server, from a normalised E.164 number. The
            // raw phone is never exported; only this hash goes to Google.
            'customer_phone_sha256' => $phone ? hash('sha256', $phone) : null,
            'customer_area' => $this->nullIfBlank($row['customer_area'] ?? null),
        ];

        $items = array_values(array_filter(
            (array) ($row['items'] ?? []),
            fn ($i) => (int) ($i['woo_item_id'] ?? 0) > 0
        ));

        $hash = $this->hash($attributes + ['items' => $items]);

        $existing = Order::where('woo_order_id', $wooId)->first();

        if ($existing && $existing->payload_hash === $hash) {
            // Byte-identical to what is already stored. Writing would only
            // move synced_at, which would make "changes zero rows" a lie.
            return ['order' => 0, 'items' => 0];
        }

        $order = Order::updateOrCreate(
            ['woo_order_id' => $wooId],
            $attributes + [
                'payload_hash' => $hash,
                'synced_at' => CarbonImmutable::now('UTC'),
            ]
        );

        $written = 0;
        $keptItemIds = [];

        foreach ($items as $item) {
            $wooItemId = (int) $item['woo_item_id'];
            $keptItemIds[] = $wooItemId;

            $itemAttributes = [
                'order_id' => $order->id,
                'woo_item_id' => $wooItemId,
                'woo_product_id' => (int) ($item['woo_product_id'] ?? 0),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'qty' => max(1, (int) ($item['qty'] ?? 1)),
                'unit_price_ghs' => $this->money($item['unit_price_ghs'] ?? 0),
            ];

            $itemHash = $this->hash($itemAttributes);
            $existingItem = OrderItem::where('woo_item_id', $wooItemId)->first();

            if ($existingItem && $existingItem->payload_hash === $itemHash) {
                continue;
            }

            OrderItem::updateOrCreate(
                ['woo_item_id' => $wooItemId],
                $itemAttributes + ['payload_hash' => $itemHash]
            );
            $written++;
        }

        // A line removed from the order in WooCommerce must disappear here too,
        // or the basket total stops reconciling with the order total.
        $orphans = OrderItem::where('order_id', $order->id)
            ->when($keptItemIds !== [], fn ($q) => $q->whereNotIn('woo_item_id', $keptItemIds))
            ->delete();

        return ['order' => 1, 'items' => $written + (int) $orphans];
    }

    /**
     * Upsert one attribution row.
     *
     * @param  array<string, mixed>  $row
     * @return int 1 if a row was written, 0 if it was already identical.
     */
    private function upsertAttribution(array $row): int
    {
        $wooId = (int) ($row['woo_attr_id'] ?? 0);

        if ($wooId <= 0) {
            return 0;
        }

        $phone = $this->normalisePhone((string) ($row['cust_phone'] ?? ''));

        $attributes = $this->campaignFields($row) + [
            'woo_attr_id' => $wooId,
            'cart_items' => $this->nullIfBlank($row['cart_items'] ?? null),
            'created_at' => $this->utc($row['created_at'] ?? null),
            'updated_at' => $this->utc($row['updated_at'] ?? null),
            'click_id' => $this->nullIfBlank($row['click_id'] ?? null),
            'click_type' => $this->nullIfBlank($row['click_type'] ?? null),
            'product_id' => (int) ($row['product_id'] ?? 0),
            'product_name' => $this->nullIfBlank($row['product_name'] ?? null),
            'price_ghs' => $this->money($row['price_ghs'] ?? 0),
            'placement' => $this->nullIfBlank($row['placement'] ?? null),
            'utm_source' => $this->nullIfBlank($row['utm_source'] ?? null),
            'utm_medium' => $this->nullIfBlank($row['utm_medium'] ?? null),
            'utm_campaign' => $this->nullIfBlank($row['utm_campaign'] ?? null),
            'status' => (string) ($row['status'] ?? 'pending'),
            'converted_at' => $this->utc($row['converted_at'] ?? null),
            'conv_value_ghs' => $this->money($row['conv_value_ghs'] ?? 0),
            'order_id' => (int) ($row['order_id'] ?? 0),
            'exported' => (bool) ($row['exported'] ?? false),
            // A shop running an older theme sends nothing here. It is read as
            // 'human' rather than dropped, so an upgrade does not silently
            // empty the funnel. See migration 000600.
            'visitor' => in_array($row['visitor'] ?? '', ['human', 'bot', 'staff'], true)
                ? (string) $row['visitor']
                : 'human',
            'ref' => $this->nullIfBlank($row['ref'] ?? null),
            'cust_name' => $this->nullIfBlank($row['cust_name'] ?? null),
            'cust_phone' => $phone,
            'cust_phone_sha256' => $phone ? hash('sha256', $phone) : null,
            'cust_area' => $this->nullIfBlank($row['cust_area'] ?? null),
        ];

        $hash = $this->hash($attributes);
        $existing = AttributionEvent::where('woo_attr_id', $wooId)->first();

        if ($existing && $existing->payload_hash === $hash) {
            return 0;
        }

        AttributionEvent::updateOrCreate(
            ['woo_attr_id' => $wooId],
            $attributes + [
                'payload_hash' => $hash,
                'synced_at' => CarbonImmutable::now('UTC'),
            ]
        );

        return 1;
    }

    /* ------------------------------------------------------------------ */

    /**
     * A stable hash of a payload. Keys are sorted so a reordered JSON object
     * is not read as a changed record.
     *
     * @param  array<string, mixed>  $data
     */
    private function hash(array $data): string
    {
        $normalise = function ($value) use (&$normalise) {
            if ($value instanceof CarbonImmutable) {
                return $value->format('Y-m-d H:i:s');
            }
            if (is_array($value)) {
                ksort($value);

                return array_map($normalise, $value);
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return $value === null ? null : (string) $value;
        };

        $data = $normalise($data);

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function utc(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '' || str_starts_with($value, '0000')) {
            return null;
        }

        // The shop sends UTC, always. Parsing without naming the zone would
        // read it in the dashboard's zone and shift every timestamp.
        return CarbonImmutable::parse($value, 'UTC');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * The keyword-level campaign columns, shared by orders and attribution.
     *
     * These come from ValueTrack on Google and from the platform macros on
     * Meta and TikTok. They are captured at click time because there is no
     * second chance: a gclid cannot be resolved back to its keyword from
     * outside Google's own reports. Sprint 2's exact join depends on them.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, ?string>
     */
    private function campaignFields(array $row): array
    {
        $keys = [
            'utm_term', 'utm_content', 'utm_id', 'match_type', 'campaign_id',
            'adgroup_id', 'creative_id', 'target_id', 'network', 'device', 'ad_placement',
        ];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->nullIfBlank($row[$key] ?? null);
        }

        // Google sends the match type as a single letter and casing varies by
        // report. Lowercase once here so a later GROUP BY does not split 'E'
        // and 'e' into two match types that look like two different bids.
        if ($out['match_type'] !== null) {
            $out['match_type'] = strtolower($out['match_type']);
        }

        return $out;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalise a Ghanaian phone number to E.164.
     *
     * Google's Enhanced Conversions hash must be taken over an E.164 number.
     * Hashing "0542148020" and "+233542148020" gives two different hashes for
     * one person, and a low match rate is invisible until it has already cost
     * weeks of bidding performance.
     */
    private function normalisePhone(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+')) {
            return $digits;
        }
        if (str_starts_with($digits, '233')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+233'.substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '+233'.$digits;
        }

        return $digits;
    }
}
