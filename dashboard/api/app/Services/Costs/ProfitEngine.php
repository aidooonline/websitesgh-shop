<?php

namespace App\Services\Costs;

use App\Models\AttributionEvent;
use App\Models\Order;
use App\Models\ProductCost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns the assumed profit per order into a measured one.
 *
 * This is the number every KEEP and every KILL in the system is compared
 * against. It was a constant in a config file. Now it is computed from what was
 * actually in each basket and what those products actually cost.
 *
 * THE HONESTY RULE
 * A basket with any uncosted product produces NO profit figure. Not a partial
 * one, not one that quietly treats the unknown item as free. Half a margin
 * reported as a whole margin is worse than no margin, because it looks like
 * knowledge and it is wrong in the flattering direction: it inflates profit per
 * order, which relaxes every verdict, which keeps keywords alive that should
 * have been cut.
 *
 * Coverage is reported alongside the number, always, so a figure computed from
 * three of forty products is never mistaken for the truth about the business.
 */
class ProfitEngine
{
    /**
     * The measured profit per order, plus how much of the data it rests on.
     *
     * @return array{
     *   profit_per_order_ghs: ?string, profit_per_order_usd: ?string,
     *   assumed_usd: string, source: string, coverage: array<string, mixed>
     * }
     */
    public function profitPerOrder(string $from, string $to, ?float $fxRate = null): array
    {
        $assumed = number_format((float) config('wgh.decisions.profit_per_order_usd'), 2, '.', '');
        $costs = ProductCost::all()->keyBy('woo_product_id');

        $orders = Order::query()
            ->with('items')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get();

        $converted = AttributionEvent::query()
            ->where('status', 'converted')
            ->whereBetween('converted_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get();

        $baskets = $this->baskets($orders, $converted);

        $withProfit = [];
        $missing = [];

        foreach ($baskets as $basket) {
            $profit = 0.0;
            $whole = true;

            foreach ($basket['lines'] as [$productId, $qty, $lineTotal]) {
                $cost = $costs->get($productId);

                if (! $cost || $cost->dealer_cost_ghs === null) {
                    $whole = false;
                    $missing[$productId] = ($missing[$productId] ?? 0) + 1;

                    continue;
                }

                $unitCost = (float) $cost->dealer_cost_ghs + (float) ($cost->delivery_cost_ghs ?? 0);
                $profit += $lineTotal - ($unitCost * $qty);
            }

            // One unknown line and the whole basket is unknown. No partial
            // margins, ever.
            if ($whole && $basket['lines'] !== []) {
                $withProfit[] = round($profit, 2);
            }
        }

        $costedProducts = $costs->filter(fn ($c) => $c->dealer_cost_ghs !== null)->count();

        $coverage = [
            'baskets_total' => count($baskets),
            'baskets_fully_costed' => count($withProfit),
            'products_costed' => $costedProducts,
            'products_total' => max($costs->count(), $costedProducts + count($missing)),
            'products_missing_cost' => count($missing),
            'confirmed_with_supplier' => $costs->filter(fn ($c) => ! $c->is_estimate)->count(),
        ];

        if (! $withProfit) {
            return [
                'profit_per_order_ghs' => null,
                'profit_per_order_usd' => null,
                'assumed_usd' => $assumed,
                'source' => 'assumed',
                'coverage' => $coverage,
            ];
        }

        $mean = array_sum($withProfit) / count($withProfit);
        $usd = $fxRate ? $mean / $fxRate : null;

        // Below a third of baskets the mean is a rumour, not a measurement, so
        // it is offered as evidence but the engine keeps using the assumption.
        $share = count($withProfit) / max(1, count($baskets));
        $source = $share >= 0.34 ? 'measured' : 'measured, thin';

        return [
            'profit_per_order_ghs' => number_format($mean, 2, '.', ''),
            'profit_per_order_usd' => $usd !== null ? number_format($usd, 2, '.', '') : null,
            'assumed_usd' => $assumed,
            'source' => $source,
            'coverage' => $coverage + ['share_costed' => round($share, 3)],
        ];
    }

    /**
     * The figure the verdict engine should actually judge against.
     *
     * Uses the measured number when enough of the data supports it, otherwise
     * the assumption, and always says which.
     *
     * @return array{value_usd: float, source: string, explanation: string}
     */
    public function judgingThreshold(string $from, string $to, ?float $fxRate = null): array
    {
        /*
         * Look the rate up rather than falling back silently.
         *
         * The first version took the rate as an optional argument and, when a
         * caller did not pass one, quietly reverted to the assumption while
         * reporting "too thin to judge on". Two different failures wearing the
         * same message: one means "enter more costs", the other means "record
         * an exchange rate". Telling the owner the wrong one sends him off to
         * do work that changes nothing.
         */
        $fxRate = $fxRate ?? ($this->latestRate($to) ?? null);

        $p = $this->profitPerOrder($from, $to, $fxRate);
        $assumed = (float) $p['assumed_usd'];

        if ($p['profit_per_order_ghs'] !== null && $fxRate === null) {
            return [
                'value_usd' => $assumed,
                'source' => 'assumed',
                'explanation' => 'Profit per order IS measurable at GHS '.$p['profit_per_order_ghs']
                    .' per order, but there is no exchange rate on record, so it cannot be compared '
                    .'against USD ad spend. Run: php artisan wgh:fx 11.85',
            ];
        }

        if ($p['source'] === 'measured' && $p['profit_per_order_usd'] !== null) {
            $measured = (float) $p['profit_per_order_usd'];

            return [
                'value_usd' => $measured,
                'source' => 'measured',
                'explanation' => sprintf(
                    'Measured at $%s per order from %d of %d baskets, using real dealer costs on %d products.',
                    number_format($measured, 2),
                    $p['coverage']['baskets_fully_costed'],
                    $p['coverage']['baskets_total'],
                    $p['coverage']['products_costed']
                ),
            ];
        }

        $why = $p['coverage']['products_costed'] === 0
            ? 'No dealer costs have been entered, so this is the spec estimate and every verdict rests on it.'
            : sprintf(
                'Only %d of %d baskets could be fully costed, which is too thin to judge on, so the estimate still applies.',
                $p['coverage']['baskets_fully_costed'], max(1, $p['coverage']['baskets_total'])
            );

        return [
            'value_usd' => $assumed,
            'source' => 'assumed',
            'explanation' => 'Assumed $'.number_format($assumed, 2).' per order. '.$why,
        ];
    }

    /**
     * Per product: what it sells for, what it costs, what it leaves.
     *
     * @return list<array<string, mixed>>
     */
    public function productMargins(string $from, string $to): array
    {
        $costs = ProductCost::all()->keyBy('woo_product_id');

        $sold = Order::query()
            ->with('items')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get()
            ->flatMap(fn ($o) => $o->items)
            ->groupBy('woo_product_id');

        $rows = [];

        foreach ($sold as $productId => $lines) {
            $cost = $costs->get((int) $productId);
            $units = (int) $lines->sum('qty');
            $revenue = 0.0;

            foreach ($lines as $l) {
                $revenue += (float) $l->unit_price_ghs * (int) $l->qty;
            }

            // Measured against what the customer actually paid over this
            // period, not against today's shelf price. A price change would
            // otherwise restate last month's profit at a price nobody paid, and
            // put it beside a revenue figure that came from real order lines.
            $charged = $units > 0 ? round($revenue / $units, 2) : null;
            $unitProfit = $cost?->unitProfit($charged);

            $rows[] = [
                'product_id' => (int) $productId,
                'name' => (string) $lines->first()->product_name,
                'units' => $units,
                'revenue_ghs' => number_format($revenue, 2, '.', ''),
                'unit_price_ghs' => $units > 0 ? number_format($revenue / $units, 2, '.', '') : null,
                'unit_profit_ghs' => $unitProfit !== null ? number_format($unitProfit, 2, '.', '') : null,
                'margin_percent' => $cost?->marginPercent($charged),
                'total_profit_ghs' => $unitProfit !== null ? number_format($unitProfit * $units, 2, '.', '') : null,
                'cost_known' => $unitProfit !== null,
                'cost_confirmed' => $cost !== null && ! $cost->is_estimate,
            ];
        }

        // Sort by profit where known, otherwise by revenue, so the products
        // worth costing next float to the top of the list.
        usort($rows, function ($a, $b) {
            $ap = $a['total_profit_ghs'] !== null ? (float) $a['total_profit_ghs'] : -1;
            $bp = $b['total_profit_ghs'] !== null ? (float) $b['total_profit_ghs'] : -1;

            return $bp <=> $ap ?: (float) $b['revenue_ghs'] <=> (float) $a['revenue_ghs'];
        });

        return $rows;
    }

    /**
     * Stamp each order with what it cost, so a closed period keeps its margin.
     */
    /**
     * The GHS per USD rate in force on a date, or null.
     */
    private function latestRate(string $on): ?float
    {
        $fx = \App\Models\FxRate::onOrBefore($on);

        return $fx ? (float) $fx->ghs_per_usd : null;
    }

    public function stampOrders(): int
    {
        $costs = ProductCost::all()->keyBy('woo_product_id');
        $n = 0;

        foreach (Order::with('items')->get() as $order) {
            $total = 0.0;
            $whole = true;

            foreach ($order->items as $item) {
                $cost = $costs->get($item->woo_product_id);

                if (! $cost || $cost->dealer_cost_ghs === null) {
                    $whole = false;

                    break;
                }

                $total += ((float) $cost->dealer_cost_ghs + (float) ($cost->delivery_cost_ghs ?? 0)) * (int) $item->qty;
            }

            if (! $whole || $order->items->isEmpty()) {
                continue;
            }

            $order->forceFill([
                'estimated_cost_ghs' => number_format($total, 2, '.', ''),
                'cost_is_estimated' => $costs->filter(fn ($c) => ! $c->is_estimate)->count() === 0,
            ]);

            // dealer_cost_ghs stays owner-entered per order; this only fills the
            // derived estimate, so a hand-entered figure is never overwritten.
            if ($order->dealer_cost_ghs === null) {
                $order->dealer_cost_ghs = number_format($total, 2, '.', '');
                $order->delivery_cost_ghs = $order->delivery_cost_ghs ?? '0.00';
                $order->recomputeProfit();
            }

            $order->save();
            $n++;
        }

        return $n;
    }

    /**
     * Baskets from both sources: WooCommerce orders and WhatsApp sales.
     *
     * A WhatsApp sale never becomes a WooCommerce order, so reading orders
     * alone would measure margin on the rare non-WhatsApp buyer and call it the
     * business.
     *
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, AttributionEvent>  $converted
     * @return list<array{source:string, lines: list<array{0:int,1:int,2:float}>}>
     */
    private function baskets(Collection $orders, Collection $converted): array
    {
        $out = [];

        foreach ($orders as $o) {
            $lines = [];
            foreach ($o->items as $i) {
                $lines[] = [(int) $i->woo_product_id, max(1, (int) $i->qty), (float) $i->unit_price_ghs * max(1, (int) $i->qty)];
            }
            if ($lines) {
                $out[] = ['source' => 'order', 'lines' => $lines];
            }
        }

        foreach ($converted as $e) {
            // Skip anything already counted as a WooCommerce order.
            if ($e->order_id > 0) {
                continue;
            }

            $lines = $this->parseCartItems((string) $e->cart_items);

            if (! $lines && $e->product_id > 0 && (float) $e->conv_value_ghs > 0) {
                $lines = [[(int) $e->product_id, 1, (float) $e->conv_value_ghs]];
            }

            if ($lines) {
                $out[] = ['source' => 'whatsapp', 'lines' => $lines];
            }
        }

        return $out;
    }

    /**
     * "1001:2:640.00,1002:1:180.00" into usable lines.
     *
     * @return list<array{0:int,1:int,2:float}>
     */
    public function parseCartItems(string $raw): array
    {
        $out = [];

        foreach (array_filter(explode(',', $raw)) as $chunk) {
            $parts = explode(':', trim($chunk));

            if (count($parts) < 3) {
                continue;
            }

            $id = (int) $parts[0];

            if ($id <= 0) {
                continue;
            }

            $out[] = [$id, max(1, (int) $parts[1]), (float) $parts[2]];
        }

        return $out;
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
