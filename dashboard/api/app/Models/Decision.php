<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The memory: every verdict and every owner action, immutable.
 *
 * A verdict without evidence is an opinion, so the model refuses to be created
 * without an evidence payload. The spec makes this a hard rule and it is
 * enforced here rather than left to each caller's discipline.
 */
class Decision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'evidence_json' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Decision $decision) {
            if (empty($decision->evidence_json)) {
                throw new InvalidArgumentException(
                    "Refusing to record a {$decision->verdict} verdict for {$decision->entity_ref} with no evidence. "
                    .'Every verdict must carry the numbers that produced it, or it cannot be audited or argued with.'
                );
            }
        });

        static::updating(function () {
            throw new InvalidArgumentException('The decision log is immutable. Write a new row instead.');
        });
    }
}
