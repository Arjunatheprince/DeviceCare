<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'device_id', 'sensor_reading_id',
        'component', 'severity', 'title', 'message', 'suggestions',
        'sent_browser', 'sent_email', 'email_to',
        'is_resolved', 'resolved_at', 'notified_at',
    ];

    protected $casts = [
        'suggestions'  => 'array',
        'sent_browser' => 'boolean',
        'sent_email'   => 'boolean',
        'is_resolved'  => 'boolean',
        'resolved_at'  => 'datetime',
        'notified_at'  => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function sensorReading(): BelongsTo
    {
        return $this->belongsTo(SensorReading::class);
    }

    /** Apakah sudah dalam cooldown (hindari spam notif). */
    public static function inCooldown(int $deviceId, string $component, int $minutes = 5): bool
    {
        return static::where('device_id', $deviceId)
            ->where('component', $component)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }
}
