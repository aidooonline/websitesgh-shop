<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a product actually costs to put in a customer's hands.
 *
 * is_estimate is not decoration. Until a supplier has confirmed a price, every
 * margin derived from this row is a guess, and the system says so in every
 * report rather than letting an assumption harden into a fact through repetition.
 */
class ProductCost extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'sell_price_ghs' => 'decimal:2',
        'dealer_cost_ghs' => 'decimal:2',
        'delivery_cost_ghs' => 'decimal:2',
        'is_estimate' => 'boolean',
        'confirmed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Margin on one unit, or null when the costs are not known.
     *
     * Null, never zero and never a guess. A missing cost that silently becomes
     * zero turns every product into pure profit and every verdict into
     * nonsense in the flattering direction.
     */
    public function unitProfit(): ?float
    {
        if ($this->sell_price_ghs === null || $this->dealer_cost_ghs === null) {
            return null;
        }

        return round(
            (float) $this->sell_price_ghs
            - (float) $this->dealer_cost_ghs
            - (float) ($this->delivery_cost_ghs ?? 0),
            2
        );
    }

    public function marginPercent(): ?float
    {
        $profit = $this->unitProfit();

        if ($profit === null || (float) $this->sell_price_ghs <= 0) {
            return null;
        }

        return round($profit / (float) $this->sell_price_ghs * 100, 1);
    }

    public function isComplete(): bool
    {
        return $this->dealer_cost_ghs !== null && $this->sell_price_ghs !== null;
    }
}
