<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DiagnosticResult;
use App\Models\SensorReading;
use App\Services\AlertService;
use App\Services\AnomalyDetectionService;
use App\Services\BaselineService;
use App\Services\TrendAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SensorReadingController extends Controller
{
    /**
     * POST /api/devices/{device}/readings
     */
    public function store(Request $request, Device $device): JsonResponse
    {
        $validated = $request->validate([
            'timestamp'                      => 'required|string',
            'cpu.usage_percent'              => 'nullable|numeric|min:0|max:100',
            'cpu.temperature_celsius'        => 'nullable|numeric',
            'cpu.frequency_mhz'              => 'nullable|numeric',
            'cpu.status'                     => 'nullable|string|in:NORMAL,WARNING,CRITICAL',
            'cpu.message'                    => 'nullable|string',
            'cpu.suggestions'                => 'nullable|array',
            'gpu.usage_percent'              => 'nullable|numeric|min:0|max:100',
            'gpu.temperature_celsius'        => 'nullable|numeric',
            'gpu.memory_used_mb'             => 'nullable|numeric',
            'gpu.status'                     => 'nullable|string|in:NORMAL,WARNING,CRITICAL',
            'gpu.message'                    => 'nullable|string',
            'gpu.suggestions'                => 'nullable|array',
            'ram.usage_percent'              => 'nullable|numeric|min:0|max:100',
            'ram.used_gb'                    => 'nullable|numeric',
            'ram.available_gb'               => 'nullable|numeric',
            'ram.status'                     => 'nullable|string|in:NORMAL,WARNING,CRITICAL',
            'ram.message'                    => 'nullable|string',
            'ram.suggestions'                => 'nullable|array',
            'disk.usage_percent'             => 'nullable|numeric|min:0|max:100',
            'disk.free_gb'                   => 'nullable|numeric',
            'disk.read_speed_mbps'           => 'nullable|numeric',
            'disk.write_speed_mbps'          => 'nullable|numeric',
            'disk.status'                    => 'nullable|string|in:NORMAL,WARNING,CRITICAL',
            'disk.message'                   => 'nullable|string',
            'disk.suggestions'               => 'nullable|array',
            // SMART
            'smart.health_status'            => 'nullable|string|max:20',
            'smart.temperature_celsius'      => 'nullable|numeric',
            'smart.reallocated_sectors'      => 'nullable|integer|min:0',
            'smart.pending_sectors'          => 'nullable|integer|min:0',
            'smart.uncorrectable_sectors'    => 'nullable|integer|min:0',
            'smart.power_on_hours'           => 'nullable|integer|min:0',
            'smart.power_cycle_count'        => 'nullable|integer|min:0',
            'smart.is_ssd'                   => 'nullable|boolean',
            'smart.status'                   => 'nullable|string|in:NORMAL,WARNING,CRITICAL',
            'smart.message'                  => 'nullable|string',
            'smart.suggestions'              => 'nullable|array',
            'battery.percentage'             => 'nullable|numeric|min:0|max:100',
            'battery.is_charging'            => 'nullable|boolean',
            'battery.time_remaining_minutes' => 'nullable|numeric',
            'battery.wear_level_percent'     => 'nullable|numeric|min:0|max:100',
            'battery.design_capacity_mwh'    => 'nullable|integer',
            'battery.full_charge_capacity_mwh' => 'nullable|integer',
            'battery.cycle_count'            => 'nullable|integer',
            'battery.status'                 => 'nullable|string|in:NORMAL,WARNING,CRITICAL',
            'battery.message'                => 'nullable|string',
            'battery.suggestions'            => 'nullable|array',
            'network.upload_speed_kbps'      => 'nullable|numeric',
            'network.download_speed_kbps'    => 'nullable|numeric',
            'health_score'                   => 'nullable|integer|min:0|max:100',
        ]);

        $recordedAt = Carbon::parse($validated['timestamp']);

        $reading = SensorReading::create([
            'device_id'                  => $device->id,
            'cpu_usage'                  => data_get($validated, 'cpu.usage_percent'),
            'cpu_temperature'            => data_get($validated, 'cpu.temperature_celsius'),
            'cpu_frequency_mhz'          => data_get($validated, 'cpu.frequency_mhz'),
            'gpu_usage'                  => data_get($validated, 'gpu.usage_percent'),
            'gpu_temperature'            => data_get($validated, 'gpu.temperature_celsius'),
            'gpu_memory_used_mb'         => data_get($validated, 'gpu.memory_used_mb'),
            'ram_usage'                  => data_get($validated, 'ram.usage_percent'),
            'ram_used_gb'                => data_get($validated, 'ram.used_gb'),
            'ram_available_gb'           => data_get($validated, 'ram.available_gb'),
            'disk_usage'                 => data_get($validated, 'disk.usage_percent'),
            'disk_free_gb'               => data_get($validated, 'disk.free_gb'),
            'disk_read_mbps'             => data_get($validated, 'disk.read_speed_mbps'),
            'disk_write_mbps'            => data_get($validated, 'disk.write_speed_mbps'),
            // SMART disk health
            'smart_health_status'          => data_get($validated, 'smart.health_status'),
            'smart_temperature'            => data_get($validated, 'smart.temperature_celsius'),
            'smart_reallocated_sectors'    => data_get($validated, 'smart.reallocated_sectors'),
            'smart_pending_sectors'        => data_get($validated, 'smart.pending_sectors'),
            'smart_uncorrectable_sectors'  => data_get($validated, 'smart.uncorrectable_sectors'),
            'smart_power_on_hours'         => data_get($validated, 'smart.power_on_hours'),
            'smart_power_cycle_count'      => data_get($validated, 'smart.power_cycle_count'),
            'smart_is_ssd'                 => data_get($validated, 'smart.is_ssd'),
            'smart_status'                 => data_get($validated, 'smart.status'),
            'battery_percentage'         => data_get($validated, 'battery.percentage'),
            'battery_charging'           => data_get($validated, 'battery.is_charging'),
            'battery_time_remaining_min' => data_get($validated, 'battery.time_remaining_minutes'),
            'battery_wear_level'         => data_get($validated, 'battery.wear_level_percent'),
            'battery_design_capacity_mwh'       => data_get($validated, 'battery.design_capacity_mwh'),
            'battery_full_charge_capacity_mwh'  => data_get($validated, 'battery.full_charge_capacity_mwh'),
            'battery_cycle_count'        => data_get($validated, 'battery.cycle_count'),
            'network_upload_kbps'        => data_get($validated, 'network.upload_speed_kbps'),
            'network_download_kbps'      => data_get($validated, 'network.download_speed_kbps'),
            'health_score'               => $validated['health_score'] ?? null,
            'cpu_status'                 => data_get($validated, 'cpu.status'),
            'gpu_status'                 => data_get($validated, 'gpu.status'),
            'ram_status'                 => data_get($validated, 'ram.status'),
            'disk_status'                => data_get($validated, 'disk.status'),
            'battery_status'             => data_get($validated, 'battery.status'),
            'recorded_at'                => $recordedAt,
            'created_at'                 => now(),
        ]);

        // Simpan diagnosis per komponen ke diagnostic_results
        $components = ['cpu', 'gpu', 'ram', 'disk', 'smart', 'battery'];
        foreach ($components as $comp) {
            $status = data_get($validated, "$comp.status");
            if (!$status) continue;
            DiagnosticResult::create([
                'device_id'         => $device->id,
                'sensor_reading_id' => $reading->id,
                'component'         => $comp,
                'status'            => $status,
                'message'           => data_get($validated, "$comp.message"),
                'suggestions'       => data_get($validated, "$comp.suggestions", []),
                'diagnosed_at'      => $recordedAt,
                'created_at'        => now(),
            ]);
        }

        // Simpan overall health score
        if (isset($validated['health_score'])) {
            DiagnosticResult::create([
                'device_id'         => $device->id,
                'sensor_reading_id' => $reading->id,
                'component'         => 'overall',
                'status'            => $validated['health_score'] >= 80 ? 'NORMAL' : ($validated['health_score'] >= 60 ? 'WARNING' : 'CRITICAL'),
                'score'             => $validated['health_score'],
                'diagnosed_at'      => $recordedAt,
                'created_at'        => now(),
            ]);
        }

        $device->update(['last_seen_at' => now()]);

        // Perbarui baseline jika sudah waktunya (non-blocking, background)
        try {
            (new BaselineService())->refreshIfStale($device);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Baseline] ' . $e->getMessage());
        }

        // Proses alert (cooldown-aware, non-blocking)
        try {
            $components = [];
            foreach (['cpu', 'gpu', 'ram', 'disk', 'battery'] as $comp) {
                if (isset($validated[$comp])) {
                    $components[$comp] = $validated[$comp];
                }
            }
            $alertService = new AlertService();

            // 1. Diagnostic threshold alerts (Warning/Critical dari rule engine Python)
            $alertService->processReading($device, $reading, $components);

            // 2. Anomaly detection alerts (Z-score vs baseline) — hanya jika baseline siap
            $device->refresh(); // ambil baseline_ready terbaru
            if ($device->baseline_ready && $device->baseline_data) {
                $anomalies = (new AnomalyDetectionService())->detect(
                    $device, $reading, $device->baseline_data
                );
                $alertService->processAnomalies($device, $reading, $anomalies);
            }

            // 3. Consistent trend alerts — jalankan setiap 50 reading per device
            // Gunakan count reading device ini agar tidak bergantung pada auto-increment ID
            // yang bisa tidak berurutan atau bercelah akibat data historis/seeder
            $readingCount = $device->sensorReadings()->count();
            if ($readingCount % 50 === 0) {
                $consistentTrends = (new TrendAnalysisService())->detectConsistentTrend($device, 5);
                $alertService->processConsistentTrends($device, $reading, $consistentTrends);
            }

        } catch (\Throwable $e) {
            // Jangan gagalkan request karena masalah notifikasi
            \Illuminate\Support\Facades\Log::warning('[Alert] ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'reading_id' => $reading->id], 201);
    }

    /**
     * GET /api/devices/{device}/readings/latest
     */
    public function latest(Device $device): JsonResponse
    {
        $reading = $device->latestReading();
        return response()->json(['data' => $reading?->toSummary()]);
    }

    /**
     * GET /api/devices/{device}/readings/history?minutes=60
     */
    public function history(Request $request, Device $device): JsonResponse
    {
        $minutes = min((int) $request->query('minutes', 60), 1440);

        $readings = $device->sensorReadings()
            ->where('recorded_at', '>=', now()->subMinutes($minutes))
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'cpu_usage', 'cpu_temperature',
                   'gpu_usage', 'gpu_temperature', 'ram_usage',
                   'disk_usage', 'battery_percentage', 'health_score',
                   'network_download_kbps', 'network_upload_kbps'])
            ->map(fn ($r) => ['t' => $r->recorded_at->toIso8601String()] + $r->only([
                'cpu_usage', 'cpu_temperature', 'gpu_usage', 'gpu_temperature',
                'ram_usage', 'disk_usage', 'battery_percentage', 'health_score',
                'network_download_kbps', 'network_upload_kbps',
            ]));

        return response()->json(['data' => $readings]);
    }
}
