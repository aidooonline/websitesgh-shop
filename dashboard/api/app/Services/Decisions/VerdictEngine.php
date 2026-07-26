<?php

namespace App\Services\Decisions;

use App\Models\Decision;
use App\Models\Keyword;
use Carbon\CarbonImmutable;

/**
 * Turns numbers into "do this".
 *
 * Four verdicts, and the distinction between two of them is where the money is:
 *
 *   KEEP   cost per order is at or under profit per order. It pays. Scale it.
 *   WATCH  spending, but too early to judge. Says WHEN it can be judged.
 *   FIX    healthy clicks AND taps, but no sale. The leak is the page or the
 *          price, NOT the keyword.
 *   KILL   old enough AND spent enough AND sold nothing.
 *
 * WHY FIX EXISTS AT ALL
 * Without it, a keyword bringing plenty of interested people who then bounce off
 * a bad price or a missing photo looks identical to a keyword bringing the wrong
 * people entirely. The first is a page problem worth fixing and the second is a
 * targeting problem worth killing, and treating them the same means switching
 * off demand you already paid to create. This distinction is the reason the
 * engine is worth building instead of sorting a spreadsheet by cost.
 *
 * WHY KILL NEEDS TWO CONDITIONS
 * Time alone kills a keyword that has spent forty cents. Spend alone kills one
 * that has had two days to work. Both, always, so thin data is never fatal.
 *
 * EVERY VERDICT CARRIES ITS EVIDENCE
 * The Decision model refuses to save without it. A verdict you cannot audit is
 * an opinion, and an opinion that pauses a keyword costs real money.
 */
class VerdictEngine
{
    private float $profitPerOrder;

    private int $minDays;

    private int $minClicks;

    private int $fixMinTaps;

    private string $profitSource = 'assumed';

    private string $profitExplanation = '';

    /**
     * @param  array{value_usd: float, source: string, explanation: string}|null  $threshold
     *   The measured profit per order, when costs make it measurable. Passing
     *   null keeps the config assumption, which is what the system ran on
     *   before any dealer cost existed.
     */
    public function __construct(?array $threshold = null)
    {
        $this->profitPerOrder = (float) config('wgh.decisions.profit_per_order_usd');

        if ($threshold !== null) {
            $this->profitPerOrder = $threshold['value_usd'];
            $this->profitSource = $threshold['source'];
            $this->profitExplanation = $threshold['explanation'];
        }
        $this->minDays = (int) config('wgh.decisions.min_days_to_judge');
        $this->minClicks = (int) config('wgh.decisions.min_clicks_to_judge');
        $this->fixMinTaps = (int) config('wgh.decisions.fix_min_taps');
    }

    /**
     * Judge one entity from its joined numbers.
     *
     * @param  array<string, mixed>  $row
     * @return array{verdict:string, reason:string, action:string, evidence:array<string, mixed>}
     */
    public function judge(array $row): array
    {
        $spend = (float) ($row['spend_usd'] ?? 0);
        $clicks = (int) ($row['clicks'] ?? 0);
        $taps = (int) ($row['taps'] ?? 0);
        $carts = (int) ($row['carts'] ?? 0);
        $orders = (int) ($row['orders'] ?? 0);
        $days = (int) ($row['days'] ?? 0);
        $cpo = $orders > 0 ? $spend / $orders : null;

        $evidence = [
            'spend_usd' => number_format($spend, 2, '.', ''),
            'clicks' => $clicks,
            'carts' => $carts,
            'taps' => $taps,
            'orders' => $orders,
            'days' => $days,
            'revenue_ghs' => (string) ($row['revenue_ghs'] ?? '0.00'),
            'cost_per_order_usd' => $cpo !== null ? number_format($cpo, 2, '.', '') : null,
            'profit_per_order_usd' => number_format($this->profitPerOrder, 2, '.', ''),
            'thresholds' => [
                'min_days' => $this->minDays,
                'min_clicks' => $this->minClicks,
                'fix_min_taps' => $this->fixMinTaps,
            ],
            'join_strength' => $row['join_strength'] ?? 'unknown',
            // Recorded on every verdict, because a KILL decided against a
            // guess and a KILL decided against a measured margin are not the
            // same claim, and six months later nobody will remember which
            // this was.
            'profit_per_order_source' => $this->profitSource,
            'profit_per_order_is_estimated' => $this->profitSource !== 'measured',
            'profit_per_order_basis' => $this->profitExplanation,
            'judged_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        // 1. It sells at a profit. Nothing else needs deciding.
        if ($orders > 0 && $cpo !== null && $cpo <= $this->profitPerOrder) {
            return [
                'verdict' => 'keep',
                'reason' => sprintf(
                    'Costs $%s per order against $%s profit per order, so every order it brings clears $%s.',
                    number_format($cpo, 2), number_format($this->profitPerOrder, 2),
                    number_format($this->profitPerOrder - $cpo, 2)
                ),
                'action' => 'Scale it. Raise the budget or the bid here before anywhere else, because this is the one place more money reliably becomes more profit.',
                'evidence' => $evidence,
            ];
        }

        // 2. It sells, but each order costs more than it earns.
        if ($orders > 0 && $cpo !== null && $cpo > $this->profitPerOrder) {
            return [
                'verdict' => 'fix',
                'reason' => sprintf(
                    'It sells, but at $%s per order against $%s profit, so every order loses $%s.',
                    number_format($cpo, 2), number_format($this->profitPerOrder, 2),
                    number_format($cpo - $this->profitPerOrder, 2)
                ),
                'action' => 'Cut the bid until cost per order drops under $'.number_format($this->profitPerOrder, 2)
                    .', or raise the margin. Do not kill it: it converts, it is just priced wrong.',
                'evidence' => $evidence,
            ];
        }

        /*
         * 3. Interested people arrived and stopped. THIS is the money insight.
         * Taps mean they wanted it enough to open WhatsApp. The failure is
         * after the click, so the keyword is not the problem.
         */
        if ($orders === 0 && $taps >= $this->fixMinTaps) {
            return [
                'verdict' => 'fix',
                'reason' => sprintf(
                    '%d people clicked and %d of them opened WhatsApp, but none bought. They wanted it. Something after the click is losing them.',
                    $clicks, $taps
                ),
                'action' => 'Check the landing page and the price before touching this bid. Look at the photo, the delivery promise and whether the price is above the market. This is a page problem, not a keyword problem, and pausing it would switch off demand you already paid to create.',
                'evidence' => $evidence,
            ];
        }

        // 4. Too early to judge. Say when it can be.
        if ($days < $this->minDays || $clicks < $this->minClicks) {
            $daysLeft = max(0, $this->minDays - $days);
            $clicksLeft = max(0, $this->minClicks - $clicks);

            $evidence['countdown'] = ['days_remaining' => $daysLeft, 'clicks_remaining' => $clicksLeft];

            return [
                'verdict' => 'watch',
                'reason' => sprintf(
                    'Too early to judge: %d days and %d clicks so far. Needs %d days and %d clicks.',
                    $days, $clicks, $this->minDays, $this->minClicks
                ),
                'action' => $daysLeft > 0 || $clicksLeft > 0
                    ? sprintf('Leave it alone for another %d day(s) and %d click(s). Judging it now would be guessing.', $daysLeft, $clicksLeft)
                    : 'Ready to judge on the next import.',
                'evidence' => $evidence,
            ];
        }

        // 5. Old enough, spent enough, sold nothing. Both conditions met.
        if ($orders === 0 && $spend >= $this->profitPerOrder) {
            return [
                'verdict' => 'kill',
                'reason' => sprintf(
                    'Spent $%s over %d days across %d clicks with no sale and %s.',
                    number_format($spend, 2), $days, $clicks,
                    $taps > 0 ? $taps.' tap(s)' : 'not a single WhatsApp tap'
                ),
                'action' => 'Cut it. It has had the time and the money and produced nothing. Move that budget to a KEEP keyword.',
                'evidence' => $evidence,
            ];
        }

        // 6. Judged, no sale, but has not yet spent one order's profit.
        return [
            'verdict' => 'watch',
            'reason' => sprintf(
                'No sale yet, but it has only spent $%s, under the $%s an order is worth. Not enough money at risk to call it.',
                number_format($spend, 2), number_format($this->profitPerOrder, 2)
            ),
            'action' => 'Leave it until it has spent $'.number_format($this->profitPerOrder, 2).'. Killing it now would be killing on price, not on evidence.',
            'evidence' => $evidence,
        ];
    }

    /**
     * Judge every keyword in a joined dataset and record the verdicts.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{judged:int, counts:array<string,int>, verdicts:list<array<string,mixed>>}
     */
    public function judgeKeywords(array $rows): array
    {
        $counts = ['keep' => 0, 'watch' => 0, 'fix' => 0, 'kill' => 0];
        $verdicts = [];

        foreach ($rows as $row) {
            $j = $this->judge($row);
            $ref = $row['keyword'].' ['.$row['match_type'].']';

            Decision::create([
                'dimension' => 'keyword',
                'entity_ref' => $ref,
                'verdict' => $j['verdict'],
                'reason' => $j['reason'],
                'suggested_action' => $j['action'],
                'evidence_json' => $j['evidence'],
                'source' => 'engine',
                'created_at' => CarbonImmutable::now('UTC'),
            ]);

            Keyword::where('keyword', $row['keyword'])
                ->where('match_type', $row['match_type'])
                ->where('campaign', $row['campaign'])
                ->update([
                    'current_verdict' => $j['verdict'],
                    'verdict_reason' => $j['reason'],
                    'verdict_at' => CarbonImmutable::now('UTC'),
                ]);

            $counts[$j['verdict']]++;
            $verdicts[] = $j + ['entity_ref' => $ref, 'row' => $row];
        }

        return ['judged' => count($rows), 'counts' => $counts, 'verdicts' => $verdicts];
    }

    /**
     * Judge each product on its own margin.
     *
     * The spec names four dimensions and only two were built. This is the one
     * that was missing and mattered most: Google Search can absorb about $9.55
     * a month here, so keyword tuning has a small ceiling, while WHICH product
     * to push, stock and photograph has none.
     *
     * Products are judged on margin rather than cost per order, because a
     * product does not have a cost per order. A product that sells well at a
     * thin margin is a different problem from one that never sells at all, and
     * the verdicts say which.
     *
     * @param  list<array<string, mixed>>  $rows  From ProfitEngine::productMargins()
     * @return array{judged:int, counts:array<string,int>, verdicts:list<array<string,mixed>>}
     */
    /**
     * The one stable way to name a product in a decision row.
     *
     * Used by whatever writes a product verdict and by whatever reads one back,
     * so the two can never drift apart.
     */
    public static function productRef(string $name, int $productId): string
    {
        return $name.' #'.$productId;
    }

    public function judgeProducts(array $rows): array
    {
        $counts = ['keep' => 0, 'watch' => 0, 'fix' => 0, 'kill' => 0];
        $verdicts = [];

        foreach ($rows as $row) {
            $units = (int) $row['units'];
            $margin = $row['margin_percent'];
            $profit = $row['total_profit_ghs'] !== null ? (float) $row['total_profit_ghs'] : null;

            /*
             * A PRODUCT NAME IS NOT AN IDENTIFIER.
             *
             * Variants share a name, names get edited in WooCommerce, and two
             * different products can carry the same one. Keyed on the name, two
             * products collided in the decision log and the report showed the
             * same blender twice with two different margins and two different
             * verdicts. The Woo product id is the only stable key, so it goes
             * in the reference and the name rides along for the reader.
             */
            $ref = self::productRef((string) $row['name'], (int) $row['product_id']);

            $evidence = [
                'product_id' => (int) $row['product_id'],
                'units' => $units,
                'revenue_ghs' => $row['revenue_ghs'],
                'unit_profit_ghs' => $row['unit_profit_ghs'],
                'margin_percent' => $margin,
                'total_profit_ghs' => $row['total_profit_ghs'],
                'cost_known' => $row['cost_known'],
                'cost_confirmed' => $row['cost_confirmed'],
                'judged_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            if (! $row['cost_known']) {
                $v = 'watch';
                $reason = sprintf('Sold %d unit%s for GHS %s, but no dealer cost is entered, so whether it earns anything is unknown.',
                    $units, $units === 1 ? '' : 's', $row['revenue_ghs']);
                $action = 'Enter this one on the cost sheet next. It is selling, so it is worth knowing whether it pays.';
            } elseif ($margin !== null && $margin < 0) {
                $v = 'kill';
                $reason = sprintf('Every unit LOSES GHS %s once the dealer and the rider are paid.', number_format(abs((float) $row['unit_profit_ghs']), 2));
                $action = 'Raise the price or drop the product. Selling more of this makes the month worse, not better.';
            } elseif ($margin !== null && $margin < 10) {
                $v = 'fix';
                $reason = sprintf('Sells, but at only %s%% margin, GHS %s a unit.', $margin, $row['unit_profit_ghs']);
                $action = 'Renegotiate the dealer price or raise the shelf price. At this margin the ad spend to sell it is hard to justify.';
            } else {
                $v = 'keep';
                $reason = sprintf('%s%% margin, GHS %s profit across %d unit%s.',
                    $margin, $row['total_profit_ghs'], $units, $units === 1 ? '' : 's');
                $action = 'Push this one. Feature it, photograph it properly, and put it in the ads before anything else.';
            }

            Decision::create([
                'dimension' => 'product',
                'entity_ref' => $ref,
                'verdict' => $v,
                'reason' => $reason,
                'suggested_action' => $action,
                'evidence_json' => $evidence,
                'source' => 'engine',
                'created_at' => CarbonImmutable::now('UTC'),
            ]);

            $counts[$v]++;
            $verdicts[] = ['verdict' => $v, 'reason' => $reason, 'action' => $action,
                'entity_ref' => $ref, 'row' => $row, 'evidence' => $evidence];
        }

        return ['judged' => count($rows), 'counts' => $counts, 'verdicts' => $verdicts];
    }

    /**
     * Judge each channel and campaign.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{judged:int, counts:array<string,int>, verdicts:list<array<string,mixed>>}
     */
    public function judgeChannels(array $rows): array
    {
        $counts = ['keep' => 0, 'watch' => 0, 'fix' => 0, 'kill' => 0];
        $verdicts = [];

        foreach ($rows as $row) {
            $j = $this->judge($row);
            $ref = $row['platform'].' / '.$row['campaign'];

            Decision::create([
                'dimension' => 'channel',
                'entity_ref' => $ref,
                'verdict' => $j['verdict'],
                'reason' => $j['reason'],
                'suggested_action' => $j['action'],
                'evidence_json' => $j['evidence'],
                'source' => 'engine',
                'created_at' => CarbonImmutable::now('UTC'),
            ]);

            $counts[$j['verdict']]++;
            $verdicts[] = $j + ['entity_ref' => $ref, 'row' => $row];
        }

        return ['judged' => count($rows), 'counts' => $counts, 'verdicts' => $verdicts];
    }
}
