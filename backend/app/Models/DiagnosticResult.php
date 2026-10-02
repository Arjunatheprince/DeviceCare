<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id', 'sensor_reading_id', 'component', 'status',
        'score', 'message', 'suggestions', 'diagnosed_at', 'created_at',
    ];

    protected $casts = [
        'suggestions'  => 'array',
        'diagnosed_at' => 'datetime',
        'created_at'   => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function sensorReading(): BelongsTo
    {
        return $this->belongsTo(SensorReading::class);
    }
}
