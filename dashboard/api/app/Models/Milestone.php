<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'threshold_json' => 'array',
        'evidence_json' => 'array',
        'reached_at' => 'datetime',
        'decision_taken_at' => 'datetime',
        'decision_taken' => 'boolean',
        'is_guardrail' => 'boolean',
    ];
}
