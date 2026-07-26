<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A mirror of one row of the shop's wghs_attribution table.
 *
 * These rows are not write-once. A 'pending' WhatsApp tap becomes 'converted'
 * later, which is why the shop stamps updated_at and why the cursor rides on
 * it rather than on created_at.
 */
class AttributionEvent extends Model
{
    public $timestamps = false;

    protected $table = 'attribution_events';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'converted_at' => 'datetime',
        'synced_at' => 'datetime',
        'price_ghs' => 'decimal:2',
        'conv_value_ghs' => 'decimal:2',
        'exported' => 'boolean',
    ];

    protected $hidden = ['cust_phone'];
}
