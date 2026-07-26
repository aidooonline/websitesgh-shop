<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One platform's spend for one entity over one period.
 *
 * The natural key (platform, campaign, ad_group, keyword, period_start,
 * period_end) is unique, which is the entire defence against the most common
 * reporting error in this class of system: importing last month's export twice
 * and reporting double the spend, which makes every profit number wrong in the
 * flattering direction.
 */
class AdSpend extends Model
{
    public $timestamps = false;

    protected $table = 'ad_spend';

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'imported_at' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'spend_usd' => 'decimal:2',
    ];

    /**
     * The columns that make a spend row unique.
     *
     * @return array<string, mixed>
     */
    public function naturalKey(): array
    {
        return [
            'platform' => $this->platform,
            'campaign' => $this->campaign,
            'ad_group' => $this->ad_group,
            'keyword' => $this->keyword,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
        ];
    }
}
