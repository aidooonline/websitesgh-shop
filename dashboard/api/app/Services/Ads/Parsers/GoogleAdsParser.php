<?php

namespace App\Services\Ads\Parsers;

use App\Services\Ads\Parser;

/**
 * Google Ads report exports.
 *
 * The alias lists are long on purpose. Google's column labels differ between
 * the Keywords report, the Search terms report, the Campaigns report and the
 * older AdWords exports, and between UI language settings. Every alias here is
 * a name Google has actually used. Missing one produces a clear error at import
 * time; guessing by position would produce wrong numbers silently.
 */
class GoogleAdsParser extends Parser
{
    public function platform(): string
    {
        return 'google';
    }

    public function fingerprint(): array
    {
        return ['campaign', 'ad group', 'keyword', 'search keyword', 'clicks', 'impr.', 'impressions', 'cost'];
    }

    public function columns(): array
    {
        return [
            'campaign' => ['campaign', 'campaign name'],
            'ad_group' => ['ad group', 'ad group name', 'adgroup'],
            'keyword' => ['keyword', 'search keyword', 'search term', 'keyword text'],
            'match_type' => ['match type', 'keyword match type', 'match'],
            'impressions' => ['impr.', 'impressions', 'impr'],
            'clicks' => ['clicks'],
            'spend' => ['cost', 'cost (usd)', 'spend', 'amount spent'],
            'day' => ['day', 'date'],
        ];
    }
}
