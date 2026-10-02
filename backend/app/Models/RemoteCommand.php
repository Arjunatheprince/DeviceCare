<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemoteCommand extends Model
{
    protected $fillable = ['device_id', 'command', 'payload', 'status', 'result', 'claimed_at', 'completed_at'];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}