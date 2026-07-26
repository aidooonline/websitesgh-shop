<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A WooCommerce order, mirrored.
 *
 * Eloquent timestamps are off: created_at here is the moment the customer
 * placed the order in the shop, not the moment this row was written. Letting
 * Eloquent touch it would overwrite real trading history with sync history.
 */
class Order extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'woo_modified_at' => 'datetime',
        'synced_at' => 'datetime',
        'revenue_ghs' => 'decimal:2',
        'dealer_cost_ghs' => 'decimal:2',
        'delivery_cost_ghs' => 'decimal:2',
        'profit_ghs' => 'decimal:2',
        'delivered' => 'boolean',
        'delivery_failed' => 'boolean',
        'momo_received' => 'boolean',
    ];

    protected $hidden = ['customer_phone'];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Profit is only real when BOTH costs are known.
     *
     * Computing it from a dealer cost alone would report a delivery-free
     * margin as truth and make every verdict downstream too generous.
     */
    public function recomputeProfit(): ?string
    {
        if ($this->dealer_cost_ghs === null || $this->delivery_cost_ghs === null) {
            $this->profit_ghs = null;

            return null;
        }

        // Deliberately not bcmath: it is a non-default PHP extension and this
        // has to run on shared cPanel without a support ticket. These are
        // four-figure cedi amounts at two decimal places, far inside the range
        // a float represents exactly once rounded, and the value is stored in
        // an exact numeric column either way.
        $profit = round(
            (float) $this->revenue_ghs - (float) $this->dealer_cost_ghs - (float) $this->delivery_cost_ghs,
            2
        );

        $this->profit_ghs = number_format($profit, 2, '.', '');

        return $this->profit_ghs;
    }
}
