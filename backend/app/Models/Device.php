<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'device_key', 'device_name', 'manufacturer', 'model',
        'cpu', 'gpu', 'ram_total_gb', 'disk_total_gb',
        'operating_system', 'os_version', 'agent_version',
        'last_seen_at', 'is_active',
        // baseline columns (M4)
        'baseline_data', 'baseline_days', 'baseline_calculated_at', 'baseline_ready',
    ];

    protected $casts = [
        'ram_total_gb'            => 'float',
        'disk_total_gb'           => 'float',
        'last_seen_at'            => 'datetime',
        'is_active'               => 'boolean',
        // baseline columns (M4)
        'baseline_data'           => 'array',
        'baseline_days'           => 'integer',
        'baseline_calculated_at'  => 'datetime',
        'baseline_ready'          => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sensorReadings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    public function diagnosticResults(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class);
    }

    public function latestReading(): ?SensorReading
    {
        return $this->sensorReadings()->latest('recorded_at')->first();
    }

    public function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }
        return $this->last_seen_at->diffInSeconds(now()) < 30;
    }
}
