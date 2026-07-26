<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    public $timestamps = false;

    protected $table = 'sync_state';

    protected $guarded = [];

    protected $casts = [
        'cursor_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public static function forStream(string $stream): self
    {
        return static::firstOrCreate(['stream' => $stream], ['last_status' => 'never']);
    }
}
