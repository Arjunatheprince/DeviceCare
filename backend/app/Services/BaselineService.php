<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BaselineService
 *
 * Menghitung dan menyimpan statistik baseline (mean + stddev) per device
 * ke kolom baseline_data di tabel devices.
 *
 * Kapan dipanggil:
 *   1. Setiap kali SensorReadingController menerima reading baru
 *      → RefreshIfStale() — hanya refresh jika sudah > REFRESH_HOURS jam
 *   2. Artisan command (manual / scheduled):
 *      php artisan devicecare:refresh-baselines
 *
 * Syarat baseline dianggap "ready":
 *   - Minimal MIN_SAMPLES data point
 *   - Minimal MIN_DAYS hari data (agar tidak bias satu sesi panjang)
 */
class BaselineService
{
    /** Refresh baseline setiap berapa jam (tidak perlu setiap reading) */
    private const REFRESH_HOURS = 6;

    /** Berapa hari data historis digunakan untuk baseline */
    private const BASELINE_DAYS = 14;

    /** Minimal sample agar baseline dianggap valid */
    private const MIN_SAMPLES = 50;

    /** Minimal hari (distinct date) agar baseline dianggap matang */
    private const MIN_DAYS = 3;

    /** Metrik yang dihitung baseline-nya */
    private const METRICS = [
        'cpu_temperature',
        'gpu_temperature',
        'cpu_usage',
        'ram_usage',
        'disk_usage',
        'battery_percentage',
        'health_score',
    ];

    /**
     * Refresh baseline untuk satu device jika sudah waktunya.
     * Dipanggil dari SensorReadingController — non-blocking (try/catch).
     */
    public function refreshIfStale(Device $device): void
    {
        try {
            if ($this->isStale($device)) {
                $this->calculate($device);
            }
        } catch (\Throwable $e) {
            Log::warning("[BaselineService] Gagal refresh baseline device #{$device->id}: {$e->getMessage()}");
        }
    }

    /**
     * Hitung baseline dan simpan ke device.
     * Bisa dipanggil langsung (dari artisan command atau force refresh).
     */
    public function calculate(Device $device, int $days = self::BASELINE_DAYS): bool
    {
        $since = now()->subDays($days);

        // Hitung mean, stddev, dan sample count per metrik dalam satu query per metrik
        $baselineData = [];
        foreach (self::METRICS as $metric) {
            // SQLite tidak punya STDDEV() — hitung manual dengan dua query
            $row = DB::table('sensor_readings')
                ->where('device_id', $device->id)
                ->where('recorded_at', '>=', $since)
                ->whereNotNull($metric)
                ->where($metric, '>', 0)
                ->selectRaw("
                    ROUND(AVG({$metric}), 2)                  AS mean,
                    COUNT(*)                                   AS sample_count,
                    COUNT(DISTINCT DATE(recorded_at))          AS day_count,
                    ROUND(AVG({$metric} * {$metric}), 2)       AS mean_sq
                ")
                ->first();

            if (!$row || (int) $row->sample_count < 1) continue;

            $mean    = (float) $row->mean;
            $meanSq  = (float) $row->mean_sq;
            // stddev = sqrt(E[X²] - (E[X])²)
            $variance = max(0, $meanSq - ($mean * $mean));
            $stddev   = round(sqrt($variance), 2);

            $baselineData[$metric] = [
                'mean'         => $mean,
                'stddev'       => $stddev,
                'sample_count' => (int)   $row->sample_count,
                'day_count'    => (int)   $row->day_count,
            ];
        }

        if (empty($baselineData)) {
            Log::debug("[BaselineService] Device #{$device->id}: belum ada data untuk baseline.");
            return false;
        }

        // Cek apakah baseline sudah cukup matang
        $totalSamples   = array_sum(array_column($baselineData, 'sample_count'));
        $maxDays        = max(array_column($baselineData, 'day_count'));
        $isReady        = $totalSamples >= self::MIN_SAMPLES && $maxDays >= self::MIN_DAYS;

        $device->update([
            'baseline_data'          => $baselineData,
            'baseline_days'          => $days,
            'baseline_calculated_at' => now(),
            'baseline_ready'         => $isReady,
        ]);

        Log::debug(sprintf(
            '[BaselineService] Device #%d baseline diperbarui. Samples: %d, Days: %d, Ready: %s',
            $device->id,
            $totalSamples,
            $maxDays,
            $isReady ? 'yes' : 'no'
        ));

        return true;
    }

    /**
     * Refresh baseline untuk semua device aktif.
     * Digunakan dari artisan command terjadwal.
     *
     * @return array{refreshed: int, skipped: int}
     */
    public function refreshAll(): array
    {
        $refreshed = 0;
        $skipped   = 0;

        Device::where('is_active', true)->each(function (Device $device) use (&$refreshed, &$skipped) {
            if ($this->isStale($device) || !$device->baseline_ready) {
                $ok = $this->calculate($device);
                $ok ? $refreshed++ : $skipped++;
            } else {
                $skipped++;
            }
        });

        return compact('refreshed', 'skipped');
    }

    /**
     * Ambil baseline dari device, atau hitung on-the-fly jika belum ada.
     *
     * @return array<string, array{mean: float, stddev: float, sample_count: int}>
     */
    public function getBaseline(Device $device): array
    {
        // Gunakan cached baseline jika masih fresh (< 2x REFRESH_HOURS)
        if (
            $device->baseline_data
            && $device->baseline_calculated_at
            && $device->baseline_calculated_at->diffInHours(now()) < (self::REFRESH_HOURS * 2)
        ) {
            return $device->baseline_data;
        }

        // Hitung langsung dari DB (lebih lambat, tapi akurat)
        $this->calculate($device);
        $device->refresh();

        return $device->baseline_data ?? [];
    }

    /**
     * Ringkasan baseline device untuk ditampilkan di API / dashboard.
     *
     * @return array{
     *   ready: bool,
     *   calculated_at: string|null,
     *   days_used: int,
     *   metrics: array
     * }
     */
    public function getSummary(Device $device): array
    {
        $data = $device->baseline_data ?? [];

        $metrics = [];
        foreach (self::METRICS as $metric) {
            $row = $data[$metric] ?? null;
            $metrics[$metric] = $row ? [
                'mean'         => $row['mean'],
                'stddev'       => $row['stddev'],
                'sample_count' => $row['sample_count'],
                'day_count'    => $row['day_count'] ?? null,
            ] : null;
        }

        return [
            'ready'          => (bool) $device->baseline_ready,
            'calculated_at'  => $device->baseline_calculated_at?->toIso8601String(),
            'days_used'      => $device->baseline_days ?? self::BASELINE_DAYS,
            'min_days_needed'=> self::MIN_DAYS,
            'min_samples_needed' => self::MIN_SAMPLES,
            'metrics'        => $metrics,
        ];
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function isStale(Device $device): bool
    {
        if (!$device->baseline_calculated_at) return true;
        return $device->baseline_calculated_at->diffInHours(now()) >= self::REFRESH_HOURS;
    }
}
