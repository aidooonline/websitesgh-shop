<?php

namespace App\Services\Agent;

use App\Models\Decision;
use App\Models\Milestone;
use App\Services\Ads\JoinEngine;
use App\Services\Costs\ProfitEngine;
use App\Services\Customers\CustomerInsights;
use App\Services\Decisions\MilestoneEvaluator;
use App\Services\Decisions\VerdictEngine;
use Carbon\CarbonImmutable;

/**
 * Assembles the whole consolidated picture into one payload.
 *
 * This is deliberately the SAME payload sprint 6 will hand to the Claude API.
 * The manual loop and the automatic one differ only in how the payload
 * travels, so building the manual path first is not a detour: it is sprint 6
 * with the network call replaced by a person carrying a file. When an API key
 * is added later, nothing here changes.
 *
 * COMPACT ON PURPOSE
 * Tokens cost money and signal beats bulk. The pack carries the numbers that
 * change a decision and nothing else: no row-by-row dumps, no vanity metrics,
 * and keyword detail capped at the entries that actually matter.
 */
class BriefingPack
{
    /** Keywords included in full. Beyond this, the tail is summarised. */
    private const KEYWORD_CAP = 40;

    /** Products carried in full. The tail earns too little to change a decision. */
    private const PRODUCT_CAP = 20;

    /**
     * @return array<string, mixed>
     */
    public function build(string $from, string $to): array
    {
        $picture = (new JoinEngine)->build($from, $to);
        $ladder = (new MilestoneEvaluator)->evaluate();

        $keywords = $picture['keywords'];
        $shown = array_slice($keywords, 0, self::KEYWORD_CAP);
        $tail = array_slice($keywords, self::KEYWORD_CAP);

        $verdicts = $this->latestVerdicts();
        $patterns = $this->latestPatterns();

        $totals = $picture['totals'];

        /*
         * THE VALUE SIDE.
         *
         * Everything above this line answers "what did it cost". None of it
         * answers "what was it worth", and the second question has no ceiling
         * while the first is nearly settled: Google Search can absorb about
         * $9.55 a month here whatever we do. Profit per order is a multiplier on
         * every keyword at once, so it belongs in the pack beside the spend.
         */
        $rate = isset($picture['fx']['rate']) ? (float) $picture['fx']['rate'] : null;
        $profit = new ProfitEngine;
        $margin = $profit->profitPerOrder($from, $to, $rate);
        $threshold = $profit->judgingThreshold($from, $to, $rate);
        $people = new CustomerInsights;

        return [
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'period' => $picture['period'],
            'currency' => [
                'spend' => 'USD',
                'sales' => 'GHS',
                'fx' => $picture['fx'],
            ],
            'totals' => $totals,
            'derived' => $this->derived($totals, $picture['fx']['rate']),
            'keywords' => array_map(fn ($k) => $this->keywordLine($k, $verdicts), $shown),
            'keywords_omitted' => count($tail) > 0 ? [
                'count' => count($tail),
                'combined_spend_usd' => $this->sumField($tail, 'spend_usd'),
                'combined_orders' => array_sum(array_column($tail, 'orders')),
            ] : null,
            'channels' => array_map(fn ($c) => $this->channelLine($c, $verdicts), $picture['channels']),
            'unmatched_spend' => $picture['unmatched'],
            'products' => $this->products($profit->productMargins($from, $to), $verdicts),
            'margin' => $margin,
            'customers' => $people->summary(),
            'areas' => $people->byArea(),
            'bundles' => $people->bundleCandidates(),
            'patterns' => $patterns,
            'loop' => $ladder['facts'],
            'milestones' => [
                'reached' => Milestone::whereNotNull('reached_at')->where('is_guardrail', false)
                    ->orderBy('sort_order')->pluck('gate_label')->all(),
                'next' => $ladder['next_gate'],
                'active_guardrails' => $ladder['active_guardrails'],
            ],
            'assumptions' => [
                'profit_per_order_usd' => number_format($threshold['value_usd'], 2, '.', ''),
                'profit_per_order_source' => $threshold['source'],
                'profit_per_order_is_an_estimate' => $threshold['source'] !== 'measured',
                'why' => $threshold['explanation'],
                'min_days_to_judge' => (int) config('wgh.decisions.min_days_to_judge'),
                'min_clicks_to_judge' => (int) config('wgh.decisions.min_clicks_to_judge'),
            ],
            'constraints' => [
                'The sale happens on WhatsApp. There is no checkout, so Google can never read a purchase directly. Offline conversion upload is the only feedback loop.',
                'Google Search is a cheap profitable trickle, not the growth engine. A forecast showed the v1 keyword set can only spend about $9.55 a month.',
                'Meta Click to WhatsApp is the primary paid channel.',
                'TikTok runs through Promote, not Ads Manager, because Ads Manager needs roughly $1,500 a month.',
                'No live Google Ads API. Decisions leave as an Editor change file.',
                'Product prices are placeholders until supplier calls are done.',
            ],
        ];
    }

    /**
     * The handful of ratios that actually decide anything.
     *
     * @param  array<string, mixed>  $t
     * @return array<string, ?string>
     */
    private function derived(array $t, ?string $rate): array
    {
        $spend = (float) $t['spend_usd'];
        $clicks = (int) $t['clicks'];
        $carts = (int) $t['carts'];
        $taps = (int) $t['taps'];
        $cartTaps = (int) ($t['cart_taps'] ?? 0);
        $orders = (int) $t['orders'];
        $revenue = (float) $t['revenue_ghs'];

        $pct = fn ($a, $b) => $b > 0 ? number_format($a / $b * 100, 1).'%' : null;

        return [
            'cost_per_click_usd' => $clicks > 0 ? number_format($spend / $clicks, 2, '.', '') : null,
            'cost_per_tap_usd' => $taps > 0 ? number_format($spend / $taps, 2, '.', '') : null,
            'cost_per_order_usd' => $orders > 0 ? number_format($spend / $orders, 2, '.', '') : null,
            // Each rate compares one population with itself. cart_to_tap uses
            // only the taps that came from the cart, because a product-page tap
            // never passed through a basket and counting it here would put the
            // rate over 100%.
            'cart_to_tap' => $pct($cartTaps, $carts),
            'tap_to_sale' => $pct($orders, $taps),
            'carts_per_100_ad_clicks' => $clicks > 0 ? number_format($carts / $clicks * 100, 1) : null,
            'revenue_usd' => $rate ? number_format($revenue / (float) $rate, 2, '.', '') : null,
            'unmatched_share_of_spend' => $spend > 0
                ? number_format((float) $t['unmatched_spend_usd'] / $spend * 100, 1).'%'
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $k
     * @param  array<string, array<string, string>>  $verdicts
     * @return array<string, mixed>
     */
    private function keywordLine(array $k, array $verdicts): array
    {
        $ref = $k['keyword'].' ['.$k['match_type'].']';
        $v = $verdicts['keyword'][$ref] ?? null;

        return [
            'keyword' => $k['keyword'],
            'match_type' => $k['match_type'],
            'campaign' => $k['campaign'],
            'spend_usd' => $k['spend_usd'],
            'clicks' => $k['clicks'],
            'carts' => $k['carts'],
            'taps' => $k['taps'],
            'orders' => $k['orders'],
            'revenue_ghs' => $k['revenue_ghs'],
            'cost_per_order_usd' => $k['cost_per_order_usd'],
            'days' => $k['days'],
            'join_strength' => $k['join_strength'],
            'verdict' => $v['verdict'] ?? null,
            'engine_reason' => $v['reason'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, array<string, string>>  $verdicts
     * @return array<string, mixed>
     */
    private function channelLine(array $c, array $verdicts): array
    {
        $ref = $c['platform'].' / '.$c['campaign'];
        $v = $verdicts['channel'][$ref] ?? null;

        return [
            'platform' => $c['platform'],
            'campaign' => $c['campaign'],
            'spend_usd' => $c['spend_usd'],
            'clicks' => $c['clicks'],
            'carts' => $c['carts'],
            'taps' => $c['taps'],
            'orders' => $c['orders'],
            'revenue_ghs' => $c['revenue_ghs'],
            'cost_per_order_usd' => $c['cost_per_order_usd'],
            'days' => $c['days'],
            'verdict' => $v['verdict'] ?? null,
            'engine_reason' => $v['reason'] ?? null,
        ];
    }

    /**
     * What each product left after the dealer was paid.
     *
     * Capped, because the tail of a product list is where attention goes to die
     * and no decision has ever turned on the 34th best seller.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, array<string, array<string, string>>>  $verdicts
     * @return array<string, mixed>
     */
    private function products(array $rows, array $verdicts): array
    {
        $shown = array_slice($rows, 0, self::PRODUCT_CAP);

        // Two products wearing one name need the id beside them to be told
        // apart. Every other product does not, and adding "#1008" to a clean
        // list is noise, so it is added only where it earns its place.
        $seen = array_count_values(array_map(fn ($r) => (string) $r['name'], $rows));

        $uncosted = array_values(array_filter($rows, fn ($r) => ! $r['cost_known']));
        $lostRevenue = 0.0;
        foreach ($uncosted as $u) {
            $lostRevenue += (float) $u['revenue_ghs'];
        }

        return [
            'rows' => array_map(function ($r) use ($verdicts, $seen) {
                // Keyed on the Woo id, never the name. Two products can wear one
                // name; keyed on it, one overwrote the other and the report
                // showed the same blender twice with two different verdicts.
                $ref = VerdictEngine::productRef((string) $r['name'], (int) $r['product_id']);
                $v = $verdicts['product'][$ref] ?? null;

                return $r + [
                    'ref' => $ref,
                    'label' => ($seen[(string) $r['name']] ?? 1) > 1 ? $ref : (string) $r['name'],
                    'verdict' => $v['verdict'] ?? null,
                    'engine_reason' => $v['reason'] ?? null,
                ];
            }, $shown),
            'omitted' => max(0, count($rows) - count($shown)),
            // Named as a gap rather than buried, because an uncosted product is
            // not a product with no margin: it is a product with no answer, and
            // the fix is one line in the cost sheet.
            'uncosted' => [
                'count' => count($uncosted),
                'revenue_ghs' => number_format($lostRevenue, 2, '.', ''),
                'names' => array_slice(array_column($uncosted, 'name'), 0, 8),
            ],
        ];
    }

    /**
     * The most recent engine verdict per entity, indexed for lookup.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    private function latestVerdicts(): array
    {
        $out = ['keyword' => [], 'channel' => [], 'product' => []];

        $rows = Decision::where('source', 'engine')
            ->whereIn('verdict', ['keep', 'watch', 'fix', 'kill'])
            ->orderBy('created_at')
            ->get();

        foreach ($rows as $r) {
            $out[$r->dimension][$r->entity_ref] = [
                'verdict' => $r->verdict,
                'reason' => $r->reason,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, string>>
     */
    private function latestPatterns(): array
    {
        return Decision::where('verdict', 'pattern')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($d) => [
                'pattern' => str_replace('PATTERN: ', '', $d->entity_ref),
                'observation' => $d->reason,
                'suggested_action' => $d->suggested_action,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sumField(array $rows, string $field): string
    {
        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float) ($r[$field] ?? 0);
        }

        return number_format($total, 2, '.', '');
    }
}
