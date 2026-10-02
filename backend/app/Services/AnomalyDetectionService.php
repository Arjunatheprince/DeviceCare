<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SensorReading;
use Illuminate\Support\Facades\DB;

/**
 * AnomalyDetectionService
 *
 * Membandingkan reading terbaru terhadap baseline historis device.
 *
 * Pendekatan: Z-score sederhana.
 *   z = (nilai_sekarang - rata_baseline) / stddev_baseline
 *
 * Jika |z| ≥ threshold, reading dianggap anomali.
 *
 * Ini jauh lebih bermakna dari threshold statis (> 80°C = panas),
 * karena memperhitungkan pola normal PERANGKAT TERSEBUT secara spesifik.
 *
 * Contoh:
 *   Laptop A biasanya idle di 55°C → 70°C = anomali.
 *   Laptop B biasanya idle di 70°C → 70°C = normal.
 */
class AnomalyDetectionService
{
    /**
     * Z-score threshold untuk anomali.
     * z ≥ 2.0 ≈ nilai berada di luar 95% distribusi normal historical.
     */
    private const Z_THRESHOLD = 2.0;

    /**
     * Metrik yang dicek anomali beserta apakah nilai tinggi lebih berbahaya.
     */
    private const METRICS = [
        'cpu_temperature'    => ['label' => 'Suhu CPU',      'higher_bad' => true,  'unit' => '°C'],
        'gpu_temperature'    => ['label' => 'Suhu GPU',      'higher_bad' => true,  'unit' => '°C'],
        'cpu_usage'          => ['label' => 'CPU Usage',     'higher_bad' => true,  'unit' => '%'],
        'ram_usage'          => ['label' => 'RAM Usage',     'higher_bad' => true,  'unit' => '%'],
        'disk_usage'         => ['label' => 'Disk Usage',    'higher_bad' => true,  'unit' => '%'],
        'battery_percentage' => ['label' => 'Battery Level', 'higher_bad' => false, 'unit' => '%'],
        'health_score'       => ['label' => 'Health Score',  'higher_bad' => false, 'unit' => '/100'],
    ];

    /**
     * Deteksi anomali pada reading terbaru suatu device.
     *
     * @param  Device        $device
     * @param  SensorReading $reading  Reading yang baru masuk
     * @param  array         $baseline Array baseline yang sudah dihitung BaselineService
     *                                 Format: ['cpu_temperature' => ['mean' => x, 'stddev' => y], ...]
     * @return array<array{
     *   metric: string,
     *   current_value: float,
     *   baseline_mean: float,
     *   baseline_stddev: float,
     *   z_score: float,
     *   severity: string,
     *   label: string,
     *   suggestion: string
     * }>
     */
    public function detect(Device $device, SensorReading $reading, array $baseline): array
    {
        $anomalies = [];

        foreach (self::METRICS as $metric => $meta) {
            $currentValue = (float) ($reading->$metric ?? 0);
            if ($currentValue <= 0) continue;

            $baselineData = $baseline[$metric] ?? null;
            if (!$baselineData || ($baselineData['stddev'] ?? 0) < 0.5) {
                // Stddev terlalu kecil → data belum bervariasi cukup, skip
                continue;
            }

            $mean   = (float) $baselineData['mean'];
            $stddev = (float) $baselineData['stddev'];
            $z      = ($currentValue - $mean) / $stddev;

            if (abs($z) < self::Z_THRESHOLD) continue;

            // Tentukan apakah arah anomali ini merupakan "arah buruk"
            $isBad = ($meta['higher_bad'] && $z > 0)
                  || (!$meta['higher_bad'] && $z < 0);

            if (!$isBad) continue; // Anomali ke arah baik tidak perlu alert

            $severity = abs($z) >= 3.0 ? 'CRITICAL' : 'WARNING';

            $anomalies[] = [
                'metric'           => $metric,
                'current_value'    => round($currentValue, 1),
                'baseline_mean'    => round($mean, 1),
                'baseline_stddev'  => round($stddev, 1),
                'z_score'          => round($z, 2),
                'severity'         => $severity,
                'label'            => $this->buildLabel($meta, $currentValue, $mean, $z),
                'suggestion'       => $this->buildSuggestion($metric, $z),
            ];
        }

        // Urutkan: CRITICAL dulu, lalu berdasarkan |z| terbesar
        usort($anomalies, fn ($a, $b) =>
            ($b['severity'] === 'CRITICAL' ? 1 : 0) - ($a['severity'] === 'CRITICAL' ? 1 : 0)
            ?: abs($b['z_score']) <=> abs($a['z_score'])
        );

        return $anomalies;
    }

    /**
     * Versi ringan: hanya gunakan data dari DB tanpa perlu objek SensorReading.
     * Digunakan untuk pengecekan on-demand dari TrendController.
     */
    public function detectFromValues(Device $device, array $currentValues, array $baseline): array
    {
        $fakeReading = new SensorReading($currentValues);
        foreach ($currentValues as $k => $v) {
            $fakeReading->$k = $v;
        }
        return $this->detect($device, $fakeReading, $baseline);
    }

    /**
     * Hitung statistik baseline langsung dari DB (tanpa cache).
     * Digunakan sebagai fallback jika kolom baseline di devices kosong.
     *
     * @param  Device $device
     * @param  int    $days   Berapa hari ke belakang untuk baseline (default 14)
     * @return array<string, array{mean: float, stddev: float, sample_count: int}>
     */
    public function computeBaselineFromDb(Device $device, int $days = 14): array
    {
        $since = now()->subDays($days);
        $baseline = [];

        foreach (array_keys(self::METRICS) as $metric) {
            $row = DB::table('sensor_readings')
                ->where('device_id', $device->id)
                ->where('recorded_at', '>=', $since)
                ->whereNotNull($metric)
                ->where($metric, '>', 0)
                ->selectRaw("
                    AVG({$metric})              AS mean,
                    AVG({$metric} * {$metric})  AS mean_sq,
                    COUNT(*)                    AS sample_count
                ")
                ->first();

            if (!$row || (int) $row->sample_count < 10) continue;

            $mean     = (float) $row->mean;
            $meanSq   = (float) $row->mean_sq;
            $variance = max(0, $meanSq - ($mean * $mean));
            $stddev   = round(sqrt($variance), 2);

            $baseline[$metric] = [
                'mean'         => round($mean,   1),
                'stddev'       => $stddev,
                'sample_count' => (int) $row->sample_count,
            ];
        }

        return $baseline;
    }

    // ── label & suggestion builders ───────────────────────────────────────────

    private function buildLabel(array $meta, float $current, float $mean, float $z): string
    {
        $direction = $z > 0 ? 'lebih tinggi' : 'lebih rendah';
        $diff      = round(abs($current - $mean), 1);
        $unit      = $meta['unit'];
        return sprintf(
            '%s saat ini %s%s, %s %s%s dari rata-rata normal (%s%s).',
            $meta['label'],
            $current,
            $unit,
            $direction,
            $diff,
            $unit,
            $mean,
            $unit
        );
    }

    private function buildSuggestion(string $metric, float $z): string
    {
        return match ($metric) {
            'cpu_temperature' => $z > 0
                ? 'Periksa sistem pendinginan CPU. Bersihkan kipas, heatsink, dan pastikan ventilasi tidak tersumbat.'
                : 'Suhu CPU lebih rendah dari biasanya — tidak ada tindakan diperlukan.',
            'gpu_temperature' => $z > 0
                ? 'Suhu GPU melonjak di luar pola normal. Kurangi beban grafis atau periksa kondisi pendingin GPU.'
                : 'Suhu GPU lebih rendah dari biasanya.',
            'cpu_usage' => $z > 0
                ? 'CPU usage jauh di atas pola normal perangkat ini. Cek proses yang berjalan di Task Manager.'
                : 'CPU usage sangat rendah — normal jika sedang tidak ada pekerjaan berat.',
            'ram_usage' => $z > 0
                ? 'RAM usage melampaui pola normal. Tutup aplikasi yang tidak diperlukan atau restart aplikasi berat.'
                : 'RAM usage lebih rendah dari biasanya.',
            'disk_usage' => $z > 0
                ? 'Penggunaan storage meningkat drastis. Cek apakah ada file besar yang baru ditambahkan.'
                : 'Storage usage lebih rendah dari biasanya.',
            'battery_percentage' => $z < 0
                ? 'Baterai terkuras lebih cepat dari pola normal. Cek konsumsi daya aplikasi yang berjalan.'
                : 'Baterai terisi lebih tinggi dari biasanya.',
            'health_score' => $z < 0
                ? 'Health score turun drastis dari baseline. Periksa semua komponen secara menyeluruh.'
                : 'Health score di atas pola normal.',
            default => 'Pantau kondisi komponen ini lebih sering dalam beberapa jam ke depan.',
        };
    }

    /**
     * Kembalikan daftar metrik yang dimonitor (untuk dokumentasi API).
     */
    public static function monitoredMetrics(): array
    {
        return array_map(
            fn ($k, $v) => ['metric' => $k, 'label' => $v['label'], 'unit' => $v['unit']],
            array_keys(self::METRICS),
            self::METRICS
        );
    }
}
