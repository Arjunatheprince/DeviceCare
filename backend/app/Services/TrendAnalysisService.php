<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * TrendAnalysisService
 *
 * Menganalisis pola data sensor selama N hari untuk setiap device.
 * Mendeteksi:
 *   - kenaikan/penurunan konsisten (linear trend)
 *   - rata-rata harian vs periode sebelumnya
 *   - perbandingan minggu ini vs minggu lalu
 */
class TrendAnalysisService
{
    /**
     * Metrik yang dianalisis beserta arah "lebih baik".
     * true = nilai tinggi adalah baik (health_score, battery)
     * false = nilai rendah adalah baik (suhu, usage)
     */
    private const METRICS = [
        'cpu_temperature'   => false,
        'cpu_usage'         => false,
        'gpu_temperature'   => false,
        'ram_usage'         => false,
        'disk_usage'        => false,
        'battery_percentage'=> true,
        'health_score'      => true,
    ];

    /**
     * Ambil rangkuman trend semua metrik utama untuk satu device.
     * Membandingkan rata-rata 7 hari terakhir vs 7 hari sebelumnya (total window 14 hari).
     *
     * @return array<string, array{
     *   metric: string,
     *   current_avg: float|null,
     *   previous_avg: float|null,
     *   change_pct: float|null,
     *   direction: string,   // 'up'|'down'|'stable'|'no_data'
     *   severity: string,    // 'good'|'warning'|'critical'|'neutral'
     *   label: string
     * }>
     */
    public function analyzeDevice(Device $device, int $days = 7): array
    {
        $now  = now();
        $mid  = $now->copy()->subDays($days);
        $from = $now->copy()->subDays($days * 2);

        $results = [];
        foreach (self::METRICS as $metric => $higherIsBetter) {
            $results[$metric] = $this->analyzeMetric(
                $device->id, $metric, $higherIsBetter, $from, $mid, $now
            );
        }
        return $results;
    }

    /**
     * Bandingkan rata-rata periode "current" vs "previous".
     */
    private function analyzeMetric(
        int $deviceId,
        string $metric,
        bool $higherIsBetter,
        \Carbon\Carbon $from,
        \Carbon\Carbon $mid,
        \Carbon\Carbon $to
    ): array {
        $currentAvg = DB::table('sensor_readings')
            ->where('device_id', $deviceId)
            ->whereBetween('recorded_at', [$mid, $to])
            ->whereNotNull($metric)
            ->avg($metric);

        $previousAvg = DB::table('sensor_readings')
            ->where('device_id', $deviceId)
            ->whereBetween('recorded_at', [$from, $mid])
            ->whereNotNull($metric)
            ->avg($metric);

        $currentAvg  = $currentAvg  !== null ? round((float) $currentAvg,  1) : null;
        $previousAvg = $previousAvg !== null ? round((float) $previousAvg, 1) : null;

        $changePct = null;
        $direction = 'no_data';
        $severity  = 'neutral';
        $label     = 'Data belum cukup.';

        if ($currentAvg !== null && $previousAvg !== null && $previousAvg > 0) {
            $changePct = round((($currentAvg - $previousAvg) / $previousAvg) * 100, 1);
            $direction = match (true) {
                $changePct >  2 => 'up',
                $changePct < -2 => 'down',
                default         => 'stable',
            };
            [$severity, $label] = $this->interpretChange(
                $metric, $changePct, $direction, $higherIsBetter, $currentAvg, $previousAvg
            );
        } elseif ($currentAvg !== null) {
            $direction = 'no_data';
            $label     = 'Belum ada data periode sebelumnya untuk perbandingan.';
        }

        return [
            'metric'       => $metric,
            'current_avg'  => $currentAvg,
            'previous_avg' => $previousAvg,
            'change_pct'   => $changePct,
            'direction'    => $direction,
            'severity'     => $severity,
            'label'        => $label,
        ];
    }

    /**
     * Terjemahkan angka perubahan menjadi severity + pesan bahasa manusia.
     */
    private function interpretChange(
        string $metric,
        float $changePct,
        string $direction,
        bool $higherIsBetter,
        float $currentAvg,
        float $previousAvg
    ): array {
        $abs  = abs($changePct);
        $unit = $this->unit($metric);

        $worsening = ($higherIsBetter && $direction === 'down')
                  || (!$higherIsBetter && $direction === 'up');

        $severity = match (true) {
            $worsening && $abs >= 15 => 'critical',
            $worsening && $abs >= 5  => 'warning',
            $direction === 'stable'  => 'neutral',
            default                  => 'good',
        };

        $label = match ($direction) {
            'stable' => "Stabil. Rata-rata saat ini {$currentAvg}{$unit}, tidak ada perubahan signifikan.",
            'up'     => $higherIsBetter
                ? "Meningkat {$abs}% ({$previousAvg}→{$currentAvg}{$unit}). Kondisi membaik."
                : "Meningkat {$abs}% ({$previousAvg}→{$currentAvg}{$unit}). Perlu diperhatikan.",
            'down'   => $higherIsBetter
                ? "Menurun {$abs}% ({$previousAvg}→{$currentAvg}{$unit}). Perlu diperhatikan."
                : "Menurun {$abs}% ({$previousAvg}→{$currentAvg}{$unit}). Kondisi membaik.",
            default  => "Rata-rata saat ini {$currentAvg}{$unit}.",
        };

        return [$severity, $label];
    }

    /**
     * Deteksi apakah ada kenaikan konsisten selama N hari terakhir (monotone-ish).
     * Mengembalikan daftar metrik yang menunjukkan kenaikan/penurunan konsisten.
     *
     * @return array<array{metric: string, direction: string, days_count: int, label: string}>
     */
    public function detectConsistentTrend(Device $device, int $days = 5): array
    {
        $since = now()->subDays($days);

        $rows = DB::table('sensor_readings')
            ->select(
                DB::raw("DATE(recorded_at) AS day"),
                DB::raw("ROUND(AVG(cpu_temperature), 1)    AS cpu_temperature"),
                DB::raw("ROUND(AVG(cpu_usage), 1)          AS cpu_usage"),
                DB::raw("ROUND(AVG(gpu_temperature), 1)    AS gpu_temperature"),
                DB::raw("ROUND(AVG(ram_usage), 1)          AS ram_usage"),
                DB::raw("ROUND(AVG(health_score), 1)       AS health_score"),
            )
            ->where('device_id', $device->id)
            ->where('recorded_at', '>=', $since)
            ->groupBy(DB::raw("DATE(recorded_at)"))
            ->orderBy('day')
            ->get()
            ->toArray();

        if (count($rows) < 3) {
            return [];
        }

        $alerts = [];
        $checkMetrics = [
            'cpu_temperature' => ['label' => 'Suhu CPU', 'higher_bad' => true],
            'gpu_temperature' => ['label' => 'Suhu GPU', 'higher_bad' => true],
            'ram_usage'       => ['label' => 'RAM Usage', 'higher_bad' => true],
            'health_score'    => ['label' => 'Health Score', 'higher_bad' => false],
        ];

        foreach ($checkMetrics as $metric => $meta) {
            $values = array_filter(
                array_map(fn ($r) => (float) ($r->$metric ?? 0), $rows),
                fn ($v) => $v > 0
            );
            $values = array_values($values);
            if (count($values) < 3) continue;

            $direction = $this->isConsistentDirection($values);
            if ($direction === null) continue;

            $isBad = ($meta['higher_bad'] && $direction === 'up')
                  || (!$meta['higher_bad'] && $direction === 'down');

            if (!$isBad) continue;

            $first = round($values[0], 1);
            $last  = round(end($values), 1);
            $unit  = $this->unit($metric);

            $alerts[] = [
                'metric'     => $metric,
                'direction'  => $direction,
                'days_count' => count($values),
                'first_val'  => $first,
                'last_val'   => $last,
                'label'      => "{$meta['label']} menunjukkan kenaikan konsisten selama "
                              . count($values) . " hari ({$first}{$unit} → {$last}{$unit}).",
            ];
        }

        return $alerts;
    }

    /**
     * Cek apakah array nilai bergerak ke satu arah secara konsisten (≥ 70% transisi searah).
     */
    private function isConsistentDirection(array $values): ?string
    {
        if (count($values) < 3) return null;

        $ups   = 0;
        $downs = 0;
        for ($i = 1; $i < count($values); $i++) {
            $diff = $values[$i] - $values[$i - 1];
            if ($diff >  0.5) $ups++;
            if ($diff < -0.5) $downs++;
        }
        $total = $ups + $downs;
        if ($total === 0) return null;

        $ratio = 0.7; // 70% transisi harus searah
        if ($ups   / ($total) >= $ratio) return 'up';
        if ($downs / ($total) >= $ratio) return 'down';

        return null;
    }

    private function unit(string $metric): string
    {
        return match (true) {
            str_contains($metric, 'temperature') => '°C',
            str_contains($metric, 'score')       => '/100',
            str_contains($metric, 'percentage')  => '%',
            default                              => '%',
        };
    }
}
