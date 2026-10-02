<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorReading extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id', 'cpu_usage', 'cpu_temperature', 'cpu_frequency_mhz',
        'gpu_usage', 'gpu_temperature', 'gpu_memory_used_mb',
        'ram_usage', 'ram_used_gb', 'ram_available_gb',
        'disk_usage', 'disk_free_gb', 'disk_read_mbps', 'disk_write_mbps',
        // SMART
        'smart_health_status', 'smart_temperature', 'smart_reallocated_sectors',
        'smart_pending_sectors', 'smart_uncorrectable_sectors',
        'smart_power_on_hours', 'smart_power_cycle_count', 'smart_is_ssd', 'smart_status',
        // Battery
        'battery_percentage', 'battery_charging', 'battery_time_remaining_min',
        'battery_wear_level', 'battery_design_capacity_mwh',
        'battery_full_charge_capacity_mwh', 'battery_cycle_count',
        'network_upload_kbps', 'network_download_kbps',
        'health_score', 'cpu_status', 'gpu_status', 'ram_status', 'disk_status', 'battery_status',
        'recorded_at', 'created_at',
    ];

    protected $casts = [
        'battery_charging' => 'boolean',
        'smart_is_ssd'     => 'boolean',
        'recorded_at'      => 'datetime',
        'created_at'       => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function diagnosticResults(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class);
    }

    public function toSummary(): array
    {
        return [
            'id'                 => $this->id,
            'recorded_at'        => $this->recorded_at?->toIso8601String(),
            'health_score'       => $this->health_score,
            'cpu_usage'          => $this->cpu_usage,
            'cpu_temperature'    => $this->cpu_temperature,
            'cpu_frequency_mhz'  => $this->cpu_frequency_mhz,
            'gpu_usage'          => $this->gpu_usage,
            'gpu_temperature'    => $this->gpu_temperature,
            'gpu_memory_used_mb' => $this->gpu_memory_used_mb,
            'ram_usage'          => $this->ram_usage,
            'ram_used_gb'        => $this->ram_used_gb,
            'ram_available_gb'   => $this->ram_available_gb,
            'disk_usage'         => $this->disk_usage,
            'disk_free_gb'       => $this->disk_free_gb,
            'disk_read_mbps'     => $this->disk_read_mbps,
            'disk_write_mbps'    => $this->disk_write_mbps,
            // SMART
            'smart_health_status'          => $this->smart_health_status,
            'smart_temperature'            => $this->smart_temperature,
            'smart_reallocated_sectors'    => $this->smart_reallocated_sectors,
            'smart_pending_sectors'        => $this->smart_pending_sectors,
            'smart_uncorrectable_sectors'  => $this->smart_uncorrectable_sectors,
            'smart_power_on_hours'         => $this->smart_power_on_hours,
            'smart_is_ssd'                 => $this->smart_is_ssd,
            'smart_status'                 => $this->smart_status,
            'battery_percentage' => $this->battery_percentage,
            'battery_charging'   => $this->battery_charging,
            'battery_wear_level' => $this->battery_wear_level,
            'battery_cycle_count'=> $this->battery_cycle_count,
            'battery_time_remaining_min' => $this->battery_time_remaining_min,
            'network_download'   => $this->network_download_kbps,
            'network_upload'     => $this->network_upload_kbps,
            'statuses' => [
                'cpu'     => $this->cpu_status,
                'gpu'     => $this->gpu_status,
                'ram'     => $this->ram_status,
                'disk'    => $this->disk_status,
                'battery' => $this->battery_status,
            ],
        ];
    }
}
