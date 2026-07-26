<?php

namespace App\Services\Ads\Parsers;

use App\Services\Ads\Parser;

/**
 * TikTok exports.
 *
 * TikTok goes through Promote rather than Ads Manager, per the settled decision
 * that Ads Manager enforces roughly a $500 minimum campaign and $50 a day,
 * which is out of reach. Promote's export is thinner than Ads Manager's, so the
 * alias lists cover both: an Ads Manager file will import correctly if that
 * route ever becomes affordable.
 *
 * Like Meta, no keyword.
 */
class TikTokParser extends Parser
{
    public function platform(): string
    {
        return 'tiktok';
    }

    public function fingerprint(): array
    {
        return ['campaign name', 'ad group name', 'ad name', 'cost', 'impressions', 'clicks (destination)', 'video views'];
    }

    public function columns(): array
    {
        return [
            'campaign' => ['campaign name', 'campaign', 'promotion name'],
            'ad_group' => ['ad group name', 'ad group', 'adgroup name'],
            'keyword' => [],
            'match_type' => [],
            'impressions' => ['impressions', 'impression', 'reach'],
            'clicks' => ['clicks (destination)', 'destination clicks', 'clicks', 'link clicks'],
            'spend' => ['cost', 'total cost', 'spend', 'amount spent (usd)'],
            'day' => ['day', 'date', 'time'],
        ];
    }
}
