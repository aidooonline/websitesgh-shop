<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ad spend is USD, sales are GHS. Every cross-currency comparison in this
 * system goes through a dated row here, so a month closed in March keeps
 * March's rate when it is re-read in September.
 */
class FxRate extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'rate_date' => 'date',
        'ghs_per_usd' => 'decimal:6',
        'created_at' => 'datetime',
    ];

    /**
     * The rate in force on a given date: that day's rate, or the most recent
     * one before it. Never a future rate, and never a silent default of 1.
     */
    public static function onOrBefore(string $date): ?self
    {
        return static::whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();
    }
}
