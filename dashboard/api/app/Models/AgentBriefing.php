<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A stored briefing, whether it came from an API or was carried in by hand.
 *
 * model_used records which, honestly, so the track record stays auditable: in
 * six months it must be possible to ask "who actually said this" and get a
 * straight answer.
 */
class AgentBriefing extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'evidence_json' => 'array',
        'tokens_cost' => 'decimal:4',
    ];
}
