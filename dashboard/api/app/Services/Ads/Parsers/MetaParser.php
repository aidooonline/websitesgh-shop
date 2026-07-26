<?php

namespace App\Services\Ads\Parsers;

use App\Services\Ads\Parser;

/**
 * Meta Ads Manager exports.
 *
 * Meta has no keyword: it is interest and audience targeting, so keyword stays
 * empty and keyword verdicts are never produced from a Meta file. That is
 * correct behaviour, not a gap, and the decision engine judges Meta on campaign
 * and creative instead.
 *
 * Meta puts the currency inside the column name ("Amount spent (USD)"), so the
 * alias list carries the common variants. The account is USD, per the settled
 * decision that ad spend is USD and only shop prices are GHS.
 *
 * "Link clicks" is preferred over "Clicks (all)": clicks (all) counts reactions
 * and profile taps, which would deflate cost per click and make a bad campaign
 * look efficient.
 */
class MetaParser extends Parser
{
    public function platform(): string
    {
        return 'meta';
    }

    public function fingerprint(): array
    {
        return ['campaign name', 'ad set name', 'ad name', 'amount spent (usd)', 'amount spent', 'impressions', 'link clicks'];
    }

    public function columns(): array
    {
        return [
            'campaign' => ['campaign name', 'campaign'],
            'ad_group' => ['ad set name', 'ad set', 'adset name'],
            'keyword' => [],
            'match_type' => [],
            'impressions' => ['impressions', 'impr.'],
            'clicks' => ['link clicks', 'clicks (all)', 'clicks', 'unique link clicks'],
            'spend' => ['amount spent (usd)', 'amount spent', 'spend', 'cost'],
            'day' => ['day', 'date', 'reporting starts'],
        ];
    }
}
