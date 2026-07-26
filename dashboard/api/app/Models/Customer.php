<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One buyer, identified only by the hash of their phone number.
 *
 * The shop keeps the raw number. This database never needs it: the only
 * question it has to answer is whether two orders came from the same person,
 * and a hash answers that while holding nothing worth stealing.
 *
 * This table exists because the cheapest sale in the business is the second one
 * to somebody who already bought, and until now nothing in the system could see
 * whether that was happening at all.
 */
class Customer extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'first_order_at' => 'datetime',
        'last_order_at' => 'datetime',
        'computed_at' => 'datetime',
        'orders_count' => 'integer',
        'order_days_count' => 'integer',
        'taps_count' => 'integer',
        'lifetime_revenue_ghs' => 'decimal:2',
        'lifetime_profit_ghs' => 'decimal:2',
        'average_order_ghs' => 'decimal:2',
        'days_to_second_order' => 'integer',
    ];

    /**
     * Came back on a different day, which is the only version of "repeat" that
     * means anything. Two orders in one afternoon are a forgotten item, not a
     * customer who was won back.
     */
    public function isRepeat(): bool
    {
        return $this->order_days_count > 1;
    }
}
