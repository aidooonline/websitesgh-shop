<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Two products that end up in the same basket more often than chance explains.
 *
 * Lift, not raw co-occurrence: two popular products appear together often
 * simply because both are popular, and bundling on that wastes the offer.
 */
class ProductPair extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'lift' => 'decimal:3',
        'combined_revenue_ghs' => 'decimal:2',
        'computed_at' => 'datetime',
    ];
}
