<?php

namespace App\Services\Customers;

use App\Models\AttributionEvent;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductPair;
use App\Services\Costs\ProfitEngine;
use Carbon\CarbonImmutable;

/**
 * The buyer, who until now did not exist in this system.
 *
 * Forty-seven distinct customers were already identified by hashed phone and
 * sixteen delivery areas were already stored. No line of analysis code touched
 * either. That meant no repeat purchase rate, no idea which parts of Accra
 * actually buy, and no view of the cheapest revenue in the business: the second
 * sale to somebody who has already bought once.
 *
 * WHY THIS MATTERS MORE THAN ANOTHER KEYWORD REPORT
 * Google Search can absorb about $9.55 a month here. Cost per order is nearly
 * as squeezed as it will get. Order value and repeat rate have no such ceiling,
 * and both feed profit per order, which is the figure every KEEP and KILL
 * verdict is measured against. Raise it and every keyword in the account gets
 * more affordable on the same day.
 *
 * IDENTITY IS THE HASH, NEVER THE NUMBER
 * The raw phone stays on the shop. This database only ever needs to know that
 * two orders came from the same person, and a hash answers that while holding
 * nothing worth stealing.
 */
class CustomerInsights
{
    /**
     * Rebuild the customer table from attribution and orders.
     */
    public function rebuild(): int
    {
        $now = CarbonImmutable::now('UTC');

        $events = AttributionEvent::whereNotNull('cust_phone_sha256')->orderBy('created_at')->get();
        // A cancelled order is not revenue and its buyer is not a buyer. Left in
        // and it would inflate lifetime value, the repeat rate and every area
        // total at once, all in the flattering direction.
        $orders = Order::whereNotNull('customer_phone_sha256')
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])
            ->orderBy('created_at')
            ->get();

        /** @var array<string, array<string, mixed>> $people */
        $people = [];

        $touch = function (string $hash) use (&$people) {
            if (! isset($people[$hash])) {
                $people[$hash] = [
                    'name' => null, 'area' => null,
                    'first_seen' => null, 'last_seen' => null,
                    'orders' => [], 'taps' => 0, 'revenue' => 0.0,
                    'first_source' => null, 'first_campaign' => null,
                ];
            }
        };

        foreach ($events as $e) {
            $hash = (string) $e->cust_phone_sha256;
            $touch($hash);
            $p = &$people[$hash];

            $p['name'] = $p['name'] ?: $e->cust_name;
            $p['area'] = $p['area'] ?: $e->cust_area;
            $p['first_seen'] = $p['first_seen'] ?: $e->created_at;
            $p['last_seen'] = $e->created_at;
            $p['first_source'] = $p['first_source'] ?: $e->utm_source;
            $p['first_campaign'] = $p['first_campaign'] ?: $e->utm_campaign;

            if ($e->status === 'converted') {
                /*
                 * ONE SALE, ONE ROW.
                 *
                 * A converted attribution event that carries an order_id is the
                 * SAME sale as the WooCommerce order below, seen from the ad
                 * side. Counting both put GHS 33,789 of lifetime revenue into
                 * one delivery area in a period whose entire turnover was GHS
                 * 10,114, and inflated the repeat rate along with it. The
                 * profit engine already refuses this double count; the customer
                 * table has to refuse it the same way.
                 */
                if ((int) $e->order_id > 0) {
                    unset($p);

                    continue;
                }

                $p['orders'][] = ['at' => $e->converted_at ?: $e->created_at, 'value' => (float) $e->conv_value_ghs];
                $p['revenue'] += (float) $e->conv_value_ghs;
            } elseif ($e->status !== 'cart') {
                $p['taps']++;
            }
            unset($p);
        }

        foreach ($orders as $o) {
            $hash = (string) $o->customer_phone_sha256;
            $touch($hash);
            $p = &$people[$hash];

            $p['name'] = $p['name'] ?: $o->customer_name;
            $p['area'] = $p['area'] ?: $o->customer_area;
            $p['first_seen'] = $p['first_seen'] ?: $o->created_at;
            $p['last_seen'] = $o->created_at;
            $p['orders'][] = ['at' => $o->created_at, 'value' => (float) $o->revenue_ghs];
            $p['revenue'] += (float) $o->revenue_ghs;
            unset($p);
        }

        foreach ($people as $hash => $p) {
            $dates = array_values(array_filter(array_column($p['orders'], 'at')));
            usort($dates, fn ($a, $b) => $a <=> $b);

            $count = count($p['orders']);

            /*
             * A SECOND ORDER ON THE SAME DAY IS NOT A RETURN.
             *
             * It is one shopping trip that got written down twice: a forgotten
             * item, a split delivery, a second message about the same basket.
             * Treating it as a repeat purchase reported a median reorder gap of
             * ZERO days, which told the owner to chase a follow-up message at a
             * moment when the customer has not left yet.
             *
             * Distinct calendar days is the honest unit. It is also the one a
             * win-back campaign can actually be scheduled against.
             */
            $days = array_values(array_unique(array_map(
                fn ($d) => CarbonImmutable::parse($d)->toDateString(),
                $dates
            )));
            sort($days);

            $returned = count($days) >= 2;

            $gap = $returned
                ? (int) CarbonImmutable::parse($days[0])->diffInDays(CarbonImmutable::parse($days[1]))
                : null;

            Customer::updateOrCreate(
                ['phone_sha256' => $hash],
                [
                    'display_name' => $p['name'] ?: null,
                    'area' => $p['area'] ?: null,
                    'first_seen_at' => $p['first_seen'],
                    'last_seen_at' => $p['last_seen'],
                    'first_order_at' => $dates[0] ?? null,
                    'last_order_at' => $dates ? end($dates) : null,
                    'orders_count' => $count,
                    'order_days_count' => count($days),
                    'taps_count' => $p['taps'],
                    'lifetime_revenue_ghs' => number_format($p['revenue'], 2, '.', ''),
                    'average_order_ghs' => number_format($count > 0 ? $p['revenue'] / $count : 0, 2, '.', ''),
                    'days_to_second_order' => $gap,
                    'first_source' => $p['first_source'] ?: null,
                    'first_campaign' => $p['first_campaign'] ?: null,
                    'computed_at' => $now,
                ]
            );
        }

        return count($people);
    }

    /**
     * The numbers worth putting in front of the owner.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $all = Customer::where('orders_count', '>', 0)->get();
        $buyers = $all->count();
        // Came back on a different day, not simply ordered twice. See rebuild().
        $repeat = $all->filter(fn ($c) => $c->order_days_count > 1);

        $gaps = $repeat->pluck('days_to_second_order')->filter(fn ($d) => $d !== null)->sort()->values();
        $medianGap = $gaps->isNotEmpty() ? (int) $gaps[(int) floor($gaps->count() / 2)] : null;

        $revenue = (float) $all->sum(fn ($c) => (float) $c->lifetime_revenue_ghs);
        $repeatRevenue = (float) $repeat->sum(fn ($c) => (float) $c->lifetime_revenue_ghs);

        return [
            'buyers' => $buyers,
            'repeat_buyers' => $repeat->count(),
            // The benchmark for ecommerce is roughly 25 to 30 percent. Below it,
            // the cheapest revenue in the business is being left on the table.
            'repeat_rate' => $buyers > 0 ? round($repeat->count() / $buyers * 100, 1) : null,
            'median_days_to_second_order' => $medianGap,
            'average_order_ghs' => $buyers > 0 ? number_format($revenue / max(1, (int) $all->sum('orders_count')), 2, '.', '') : null,
            'repeat_share_of_revenue' => $revenue > 0 ? round($repeatRevenue / $revenue * 100, 1) : null,
            'identified_share' => $this->identifiedShare(),
        ];
    }

    /**
     * What fraction of confirmed sales we can even name.
     *
     * A repeat rate computed on a third of buyers is not a repeat rate, so this
     * travels with it everywhere.
     */
    public function identifiedShare(): ?float
    {
        // A converted event carrying an order_id is the same sale as the order,
        // so only the WhatsApp-only conversions are added to the order count.
        $whatsappOnly = AttributionEvent::where('status', 'converted')
            ->where(fn ($q) => $q->whereNull('order_id')->orWhere('order_id', 0));

        $sales = (clone $whatsappOnly)->count()
            + Order::whereNotIn('status', ['cancelled', 'failed'])->count();

        if ($sales === 0) {
            return null;
        }

        $named = (clone $whatsappOnly)->whereNotNull('cust_phone_sha256')->count()
            + Order::whereNotIn('status', ['cancelled', 'failed'])->whereNotNull('customer_phone_sha256')->count();

        return round(min(1.0, $named / $sales) * 100, 1);
    }

    /**
     * Which areas of Accra buy, and what they spend.
     *
     * Direct input to Meta targeting, which is the primary paid channel, and to
     * deciding where a delivery run is worth making.
     *
     * @return list<array<string, mixed>>
     */
    public function byArea(): array
    {
        return Customer::query()
            ->whereNotNull('area')
            ->where('orders_count', '>', 0)
            ->get()
            ->groupBy(fn ($c) => trim(mb_strtolower($c->area)))
            ->map(function ($group, $area) {
                $revenue = (float) $group->sum(fn ($c) => (float) $c->lifetime_revenue_ghs);
                $orders = (int) $group->sum('orders_count');

                return [
                    'area' => ucwords($area),
                    'buyers' => $group->count(),
                    'orders' => $orders,
                    'revenue_ghs' => number_format($revenue, 2, '.', ''),
                    'average_order_ghs' => number_format($orders > 0 ? $revenue / $orders : 0, 2, '.', ''),
                    'repeat_buyers' => $group->filter(fn ($c) => $c->order_days_count > 1)->count(),
                ];
            })
            ->sortByDesc(fn ($a) => (float) $a['revenue_ghs'])
            ->values()
            ->all();
    }

    /**
     * What sells with what, by lift rather than raw co-occurrence.
     *
     * Two popular products land in the same basket often simply because both
     * are popular. That is not a finding, and bundling on it wastes the offer.
     * Lift above 1 means they appear together MORE than their individual
     * popularity predicts, which is the only version worth acting on.
     */
    public function rebuildPairs(): int
    {
        $engine = new ProfitEngine;
        $baskets = [];

        foreach (Order::with('items')->get() as $o) {
            $ids = $o->items->pluck('woo_product_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
            if (count($ids) >= 2) {
                $baskets[] = ['ids' => $ids, 'value' => (float) $o->revenue_ghs,
                    'names' => $o->items->pluck('product_name', 'woo_product_id')->all()];
            }
        }

        foreach (AttributionEvent::whereNotNull('cart_items')->get() as $e) {
            // Same rule as everywhere else: a converted event carrying an
            // order_id is the order above, so counting its basket again would
            // double the support behind every pair in it.
            if ((int) $e->order_id > 0) {
                continue;
            }

            $lines = $engine->parseCartItems((string) $e->cart_items);
            $ids = array_values(array_unique(array_column($lines, 0)));

            if (count($ids) >= 2) {
                $baskets[] = ['ids' => $ids, 'value' => (float) $e->price_ghs, 'names' => []];
            }
        }

        if (count($baskets) < 3) {
            return 0;   // Below this, "what sells together" is anecdote.
        }

        $total = count($baskets);
        $single = [];
        $pairs = [];
        $names = [];

        foreach ($baskets as $b) {
            foreach ($b['names'] as $id => $n) {
                $names[(int) $id] = $n;
            }
            foreach ($b['ids'] as $id) {
                $single[$id] = ($single[$id] ?? 0) + 1;
            }
            $sorted = $b['ids'];
            sort($sorted);
            for ($i = 0; $i < count($sorted); $i++) {
                for ($j = $i + 1; $j < count($sorted); $j++) {
                    $k = $sorted[$i].':'.$sorted[$j];
                    $pairs[$k] = ($pairs[$k] ?? ['n' => 0, 'value' => 0.0]);
                    $pairs[$k]['n']++;
                    $pairs[$k]['value'] += $b['value'];
                }
            }
        }

        $now = CarbonImmutable::now('UTC');
        ProductPair::query()->delete();
        $written = 0;

        foreach ($pairs as $key => $p) {
            [$a, $b] = array_map('intval', explode(':', $key));

            $pA = $single[$a] / $total;
            $pB = $single[$b] / $total;
            $pAB = $p['n'] / $total;
            $lift = ($pA * $pB) > 0 ? $pAB / ($pA * $pB) : 0;

            ProductPair::create([
                'product_a' => $a,
                'product_b' => $b,
                'name_a' => mb_substr($names[$a] ?? ('#'.$a), 0, 191),
                'name_b' => mb_substr($names[$b] ?? ('#'.$b), 0, 191),
                'baskets_together' => $p['n'],
                'baskets_a' => $single[$a],
                'baskets_b' => $single[$b],
                'lift' => round($lift, 3),
                'combined_revenue_ghs' => number_format($p['value'], 2, '.', ''),
                'computed_at' => $now,
            ]);
            $written++;
        }

        return $written;
    }

    /**
     * Pairs worth building a bundle around.
     *
     * @return list<array<string, mixed>>
     */
    public function bundleCandidates(int $limit = 8): array
    {
        /*
         * SUPPORT BEFORE LIFT.
         *
         * Lift is a ratio, so a pair seen twice in a hundred baskets can score
         * 10.9x purely because both products are otherwise rare. The report
         * printed exactly that: two different pairs at 10.889x on two baskets
         * each, ranked above a pair seen thirty-two times. Building a bundle on
         * a coincidence costs real margin on the discount.
         *
         * Three baskets is the floor, and the count travels with every pair so
         * the strength of the evidence is visible beside the claim.
         */
        return ProductPair::query()
            ->where('lift', '>', 1.2)
            ->where('baskets_together', '>=', 3)
            ->orderByDesc('baskets_together')
            ->orderByDesc('lift')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'a' => $p->name_a,
                'b' => $p->name_b,
                'together' => $p->baskets_together,
                'lift' => (string) $p->lift,
                'revenue_ghs' => (string) $p->combined_revenue_ghs,
                'reading' => sprintf(
                    'Bought together %d times, %sx more often than their individual popularity predicts.',
                    $p->baskets_together, $p->lift
                ),
            ])
            ->all();
    }
}
