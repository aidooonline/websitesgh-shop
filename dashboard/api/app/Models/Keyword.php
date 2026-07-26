<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The living registry the decision engine judges.
 *
 * Keyword text is stored lowercased and trimmed, because Google writes
 * "Blender Price" in one report and "blender price" in another, and joining
 * on the raw text splits one keyword into two half-as-profitable rows.
 */
class Keyword extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'verdict_at' => 'datetime',
        'owner_decision_at' => 'datetime',
        'lifetime_spend_usd' => 'decimal:2',
        'lifetime_revenue_ghs' => 'decimal:2',
        'lifetime_clicks' => 'integer',
        'lifetime_taps' => 'integer',
        'lifetime_orders' => 'integer',
    ];

    public static function normalise(string $keyword): string
    {
        // Google wraps match types in the text itself: [exact], "phrase".
        // Stripping them here means one keyword is one row whatever report it
        // arrived in, and the match type lives in its own column where it can
        // actually be grouped on.
        $keyword = trim($keyword);
        $keyword = trim($keyword, "[]\"'");
        $keyword = preg_replace('/\s+/', ' ', $keyword) ?? $keyword;

        return mb_strtolower(trim($keyword));
    }
}
