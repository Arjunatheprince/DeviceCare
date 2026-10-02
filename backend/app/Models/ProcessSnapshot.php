<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id', 'by_cpu', 'by_memory', 'recorded_at', 'created_at',
    ];

    protected $casts = [
        'by_cpu'      => 'array',
        'by_memory'   => 'array',
        'recorded_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
