<?php

namespace App\Services\Ads;

use App\Models\AdSpend;
use App\Models\AttributionEvent;
use App\Models\FxRate;
use App\Models\Keyword;
use Illuminate\Support\Collection;

/**
 * The join: spend to tap to order to profit, traceable end to end.
 *
 * This is the point of the whole system. No off-the-shelf tool can do it,
 * because the sale happens inside WhatsApp and the only thing tying a chat back
 * to an ad click is the ref code and the click id this shop stamps itself.
 *
 * THREE JOIN STRENGTHS, USED IN THIS ORDER
 * 1. click_id. Exact. One click, one row, no ambiguity. Only available when the
 *    ad platform put a click id on the URL and the visitor's cookie survived.
 * 2. keyword. Google search only, matched on lowercased text plus campaign.
 *    This is why keyword text is normalised on both sides at write time.
 * 3. campaign + source. The fallback for Meta and TikTok, which have no
 *    keyword to give.
 *
 * UNMATCHED SPEND IS A FINDING, NOT A ROUNDING ERROR
 * Spend with no matching attribution is reported, never quietly dropped into
 * an "other" bucket. It means one of two things and both are worth money: a
 * tracking gap, where the money is working but invisible, or a genuine leak,
 * where the money bought clicks that never reached a tap. Hiding it makes every
 * cost per order look better than it is.
 *
 * CURRENCY
 * Spend is USD, revenue is GHS. They are never compared without going through
 * a dated fx_rates row, and the join stores both sides plus the rate used, so a
 * period closed in July keeps July's rate when it is read again in December.
 */
class JoinEngine
{
    /**
     * Build the full picture for a period.
     *
     * @return array{
     *   period: array{from: string, to: string},
     *   fx: array{rate: ?string, date: ?string},
     *   keywords: list<array<string, mixed>>,
     *   channels: list<array<string, mixed>>,
     *   unmatched: list<array<string, mixed>>,
     *   totals: array<string, string|int>
     * }
     */
    public function build(string $from, string $to): array
    {
        $fx = FxRate::onOrBefore($to);
        $rate = $fx ? (float) $fx->ghs_per_usd : null;

        /*
         * Interval OVERLAP, not containment. A report covering 1 to 28 July
         * looked at on the 26th would be invisible under a containment test,
         * and the dashboard would show a month of real spend as zero while
         * every keyword sat on a WATCH verdict for lack of data. Overlap is
         * the only correct test for two date ranges.
         */
        $spend = AdSpend::query()
            ->whereDate('period_start', '<=', $to)
            ->whereDate('period_end', '>=', $from)
            ->get();

        $events = AttributionEvent::query()
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get();

        $keywords = $this->joinKeywords($spend, $events, $rate);
        $channels = $this->joinChannels($spend, $events, $rate);
        $unmatched = $this->unmatched($spend, $events);

        $totals = [
            'spend_usd' => $this->sum($spend->pluck('spend_usd')),
            'clicks' => (int) $spend->sum('clicks'),
            'impressions' => (int) $spend->sum('impressions'),
            'taps' => $events->whereNotIn('status', ['cart'])->count(),
            'carts' => $events->where('status', 'cart')->count(),
            // Taps that came FROM the cart specifically. The funnel is not
            // strictly sequential here: a visitor can message from a product
            // page without ever adding to the basket. Dividing all taps by
            // carts therefore produces rates above 100%, which is nonsense and
            // destroys trust in every other number on the page.
            'cart_taps' => $events->where('placement', 'cart_whatsapp')->count(),
            'orders' => $events->where('status', 'converted')->count(),
            'revenue_ghs' => $this->sum($events->where('status', 'converted')->pluck('conv_value_ghs')),
            'unmatched_spend_usd' => $this->sum(collect($unmatched)->pluck('spend_usd')),
        ];

        return [
            'period' => ['from' => $from, 'to' => $to],
            'fx' => [
                'rate' => $rate !== null ? number_format($rate, 6, '.', '') : null,
                'date' => $fx?->rate_date?->toDateString(),
            ],
            'keywords' => $keywords,
            'channels' => $channels,
            'unmatched' => $unmatched,
            'totals' => $totals,
        ];
    }

    /**
     * Per keyword: spend, clicks, taps, orders, revenue, profit.
     *
     * @param  Collection<int, AdSpend>  $spend
     * @param  Collection<int, AttributionEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function joinKeywords(Collection $spend, Collection $events, ?float $rate): array
    {
        $rows = [];

        $searchSpend = $spend->where('platform', 'google')->filter(fn ($s) => $s->keyword !== '');

        foreach ($searchSpend->groupBy(fn ($s) => $s->keyword.'|'.$s->match_type) as $key => $group) {
            [$keyword, $matchType] = array_pad(explode('|', (string) $key, 2), 2, '');

            /*
             * Attribution rows belonging to this keyword. utm_term is
             * normalised at write time on both sides, so this is a plain
             * equality rather than a fuzzy match. Rows carrying a click id are
             * counted separately because that is the exact join and its count
             * is the honest measure of how much of the picture is certain.
             */
            $mine = $events->filter(fn ($e) => $e->utm_term !== null
                && Keyword::normalise($e->utm_term) === $keyword);

            $exact = $mine->filter(fn ($e) => $e->click_id !== null)->count();
            $taps = $mine->whereNotIn('status', ['cart'])->count();
            $carts = $mine->where('status', 'cart')->count();
            $sales = $mine->where('status', 'converted');

            $spendUsd = $this->sum($group->pluck('spend_usd'));
            $clicks = (int) $group->sum('clicks');
            $orders = $sales->count();
            $revenueGhs = $this->sum($sales->pluck('conv_value_ghs'));

            $rows[] = [
                'keyword' => $keyword,
                'match_type' => $matchType,
                'campaign' => (string) $group->first()->campaign,
                'ad_group' => (string) $group->first()->ad_group,
                'campaign_id' => $mine->first()?->campaign_id,

                'spend_usd' => $spendUsd,
                'impressions' => (int) $group->sum('impressions'),
                'clicks' => $clicks,
                'carts' => $carts,
                'taps' => $taps,
                'orders' => $orders,
                'revenue_ghs' => $revenueGhs,

                'exact_join_rows' => $exact,
                'join_strength' => $exact > 0 ? 'click_id' : ($mine->isNotEmpty() ? 'keyword' : 'none'),

                'first_seen' => optional($group->min('period_start'))->toDateString(),
                'last_seen' => optional($group->max('period_end'))->toDateString(),
                'days' => $this->daysCovered($group),

                'cost_per_click_usd' => $clicks > 0 ? $this->div($spendUsd, $clicks) : null,
                'cost_per_tap_usd' => $taps > 0 ? $this->div($spendUsd, $taps) : null,
                'cost_per_order_usd' => $orders > 0 ? $this->div($spendUsd, $orders) : null,
                'revenue_usd' => $rate ? number_format((float) $revenueGhs / $rate, 2, '.', '') : null,
                'fx_rate' => $rate ? number_format($rate, 6, '.', '') : null,
            ];
        }

        usort($rows, fn ($a, $b) => (float) $b['spend_usd'] <=> (float) $a['spend_usd']);

        return $rows;
    }

    /**
     * Per platform and campaign, which is how Meta and TikTok must be judged.
     *
     * @param  Collection<int, AdSpend>  $spend
     * @param  Collection<int, AttributionEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function joinChannels(Collection $spend, Collection $events, ?float $rate): array
    {
        /*
         * EVERY EVENT BELONGS TO AT MOST ONE CHANNEL.
         *
         * The first version matched each campaign against every event and fell
         * back to "same utm_source" when the campaign name did not match. With
         * three Meta campaigns running, all three then claimed all Meta
         * attribution: identical taps, identical sales, identical revenue on
         * every row, and total attributed revenue 2.6 times the money actually
         * taken. Cost per order was understated everywhere, and a cold audience
         * that had sold nothing was handed a KEEP verdict at $4.00 an order.
         * That is the exact failure that gets a losing campaign scaled.
         *
         * So attribution is now assigned, once, in strict precedence, and what
         * cannot be assigned is reported as unassigned rather than shared out.
         */
        $byId = [];
        $byName = [];
        $platformCampaigns = [];

        foreach ($spend->groupBy(fn ($s) => $s->platform.'|'.$s->campaign) as $key => $group) {
            [$platform, $campaign] = array_pad(explode('|', (string) $key, 2), 2, '');
            $byName[$platform][mb_strtolower($campaign)] = $key;
            $byId[$campaign] = $key;      // Campaign ids arrive as the campaign string on Google.
            $platformCampaigns[$platform][] = $key;
        }

        /** @var array<string, list<AttributionEvent>> $assigned */
        $assigned = [];
        $unassigned = [];

        foreach ($events as $e) {
            $key = null;

            // 1. Campaign id. Strongest: it survives a rename.
            if ($e->campaign_id !== null && isset($byId[$e->campaign_id])) {
                $key = $byId[$e->campaign_id];
            }

            // 2. Campaign name, case-insensitive.
            if ($key === null && $e->utm_campaign !== null) {
                $needle = mb_strtolower($e->utm_campaign);
                foreach ($byName as $platform => $names) {
                    if (isset($names[$needle])) {
                        $key = $names[$needle];
                        break;
                    }
                }
            }

            /*
             * 3. Source only. Safe ONLY when that platform is running a single
             * campaign, because then there is nothing to be ambiguous about.
             * With two or more, guessing would be inventing precision.
             */
            if ($key === null && $e->utm_source !== null) {
                $platform = mb_strtolower($e->utm_source);
                if (isset($platformCampaigns[$platform]) && count($platformCampaigns[$platform]) === 1) {
                    $key = $platformCampaigns[$platform][0];
                }
            }

            if ($key === null) {
                if ($e->utm_source !== null && isset($platformCampaigns[mb_strtolower($e->utm_source)])) {
                    $unassigned[mb_strtolower($e->utm_source)][] = $e;
                }

                continue;
            }

            $assigned[$key][] = $e;
        }

        $rows = [];

        foreach ($spend->groupBy(fn ($s) => $s->platform.'|'.$s->campaign) as $key => $group) {
            [$platform, $campaign] = array_pad(explode('|', (string) $key, 2), 2, '');
            $mine = collect($assigned[$key] ?? []);

            $sales = $mine->where('status', 'converted');
            $spendUsd = $this->sum($group->pluck('spend_usd'));
            $orders = $sales->count();
            $revenueGhs = $this->sum($sales->pluck('conv_value_ghs'));

            $rows[] = [
                'platform' => $platform,
                'campaign' => $campaign,
                'spend_usd' => $spendUsd,
                'impressions' => (int) $group->sum('impressions'),
                'clicks' => (int) $group->sum('clicks'),
                'carts' => $mine->where('status', 'cart')->count(),
                'taps' => $mine->whereNotIn('status', ['cart'])->count(),
                'orders' => $orders,
                'revenue_ghs' => $revenueGhs,
                'days' => $this->daysCovered($group),
                'cost_per_order_usd' => $orders > 0 ? $this->div($spendUsd, $orders) : null,
                'revenue_usd' => $rate ? number_format((float) $revenueGhs / $rate, 2, '.', '') : null,
                'attribution_confidence' => $mine->isEmpty()
                    ? 'none'
                    : ($mine->filter(fn ($e) => $e->campaign_id !== null || $e->utm_campaign !== null)->isNotEmpty()
                        ? 'campaign' : 'platform only'),
            ];
        }

        /*
         * Traffic that names a platform but no campaign, where more than one
         * campaign is running. Shown as its own line rather than shared out,
         * because a number split on a guess is worse than a number labelled
         * unknown: it looks like knowledge.
         */
        foreach ($unassigned as $platform => $list) {
            $mine = collect($list);
            $sales = $mine->where('status', 'converted');

            $rows[] = [
                'platform' => $platform,
                'campaign' => '(campaign not identified)',
                'spend_usd' => '0.00',
                'impressions' => 0,
                'clicks' => 0,
                'carts' => $mine->where('status', 'cart')->count(),
                'taps' => $mine->whereNotIn('status', ['cart'])->count(),
                'orders' => $sales->count(),
                'revenue_ghs' => $this->sum($sales->pluck('conv_value_ghs')),
                'days' => 0,
                'cost_per_order_usd' => null,
                'revenue_usd' => null,
                'attribution_confidence' => 'unassigned',
            ];
        }

        usort($rows, fn ($a, $b) => (float) $b['spend_usd'] <=> (float) $a['spend_usd']);

        return $rows;
    }

    /**
     * Spend that matched no attribution at all.
     *
     * @param  Collection<int, AdSpend>  $spend
     * @param  Collection<int, AttributionEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function unmatched(Collection $spend, Collection $events): array
    {
        $rows = [];

        foreach ($spend as $s) {
            $matched = $events->contains(function ($e) use ($s) {
                if ($s->keyword !== '' && $e->utm_term !== null
                    && Keyword::normalise($e->utm_term) === $s->keyword) {
                    return true;
                }
                if ($e->campaign_id !== null && $e->campaign_id === $s->campaign) {
                    return true;
                }
                if ($e->utm_campaign !== null && mb_strtolower($e->utm_campaign) === mb_strtolower($s->campaign)) {
                    return true;
                }

                return false;
            });

            if ($matched || (float) $s->spend_usd <= 0) {
                continue;
            }

            $rows[] = [
                'platform' => $s->platform,
                'campaign' => $s->campaign,
                'ad_group' => $s->ad_group,
                'keyword' => $s->keyword,
                'clicks' => (int) $s->clicks,
                'spend_usd' => (string) $s->spend_usd,
                'period' => $s->period_start->toDateString().' to '.$s->period_end->toDateString(),
                'likely_cause' => $s->clicks > 0
                    ? 'Clicks happened but no tap was ever logged against this. Either the tracking template is not set, or the landing page loses everyone before the WhatsApp button.'
                    : 'No clicks at all, so this is spend on impressions with nothing to attribute.',
            ];
        }

        usort($rows, fn ($a, $b) => (float) $b['spend_usd'] <=> (float) $a['spend_usd']);

        return $rows;
    }

    /**
     * Write the joined per-keyword numbers back onto the registry.
     *
     * @param  list<array<string, mixed>>  $keywordRows
     */
    public function updateRegistry(array $keywordRows): int
    {
        $n = 0;

        foreach ($keywordRows as $row) {
            $keyword = Keyword::where('keyword', $row['keyword'])
                ->where('match_type', $row['match_type'])
                ->where('campaign', $row['campaign'])
                ->first();

            if (! $keyword) {
                continue;
            }

            // Recomputed, never incremented, so a re-import cannot drift them.
            $keyword->forceFill([
                'lifetime_taps' => $row['taps'],
                'lifetime_orders' => $row['orders'],
                'lifetime_revenue_ghs' => $row['revenue_ghs'],
                'campaign_id' => $row['campaign_id'],
                'last_seen' => $row['last_seen'],
            ])->save();

            $n++;
        }

        return $n;
    }

    private function sourceFor(string $platform): string
    {
        return match ($platform) {
            'google' => 'google',
            'meta' => 'meta',
            'tiktok' => 'tiktok',
            default => $platform,
        };
    }

    /**
     * @param  Collection<int, AdSpend>  $group
     */
    private function daysCovered(Collection $group): int
    {
        $start = $group->min('period_start');
        $end = $group->max('period_end');

        if (! $start || ! $end) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    private function sum(Collection $values): string
    {
        $total = 0.0;
        foreach ($values as $v) {
            $total += (float) $v;
        }

        return number_format($total, 2, '.', '');
    }

    private function div(string $amount, int $by): string
    {
        return $by > 0 ? number_format((float) $amount / $by, 2, '.', '') : '0.00';
    }
}
