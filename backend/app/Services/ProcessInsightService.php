<?php

namespace App\Services;

use App\Models\Device;
use App\Models\ProcessSnapshot;

class ProcessInsightService
{
    public function analyze(Device $device, int $minutes = 15): array
    {
        $snapshots = ProcessSnapshot::where('device_id', $device->id)
            ->where('recorded_at', '>=', now()->subMinutes($minutes))
            ->orderBy('recorded_at')
            ->get();

        if ($snapshots->count() < 3) {
            return [
                'status' => 'NO_DATA',
                'summary' => 'Belum cukup snapshot proses untuk analisis pola aplikasi.',
                'insights' => [],
            ];
        }

        $insights = [];
        $insights = array_merge($insights, $this->detectSustainedCpu($snapshots));
        $insights = array_merge($insights, $this->detectMemoryGrowth($snapshots));
        $insights = array_merge($insights, $this->detectHeavyMemoryProcess($snapshots));

        usort($insights, fn ($a, $b) => $this->severityRank($b['severity']) <=> $this->severityRank($a['severity']));
        $insights = array_slice($insights, 0, 5);

        return [
            'status' => empty($insights) ? 'NORMAL' : $insights[0]['severity'],
            'summary' => empty($insights)
                ? 'Tidak ada proses yang terlihat bermasalah dalam window terbaru.'
                : count($insights) . ' pola proses perlu diperhatikan.',
            'window_minutes' => $minutes,
            'snapshot_count' => $snapshots->count(),
            'insights' => $insights,
        ];
    }

    private function detectSustainedCpu($snapshots): array
    {
        $series = [];
        foreach ($snapshots as $snapshot) {
            foreach (($snapshot->by_cpu ?? []) as $process) {
                $name = $this->normalizeName($process['name'] ?? 'unknown');
                $series[$name]['name'] = $process['name'] ?? $name;
                $series[$name]['cpu'][] = (float) ($process['cpu_percent'] ?? 0);
            }
        }

        $insights = [];
        foreach ($series as $row) {
            if (count($row['cpu']) < 3) continue;
            $avg = array_sum($row['cpu']) / count($row['cpu']);
            $max = max($row['cpu']);
            if ($avg < 35 && $max < 70) continue;

            $severity = $avg >= 65 || $max >= 90 ? 'CRITICAL' : 'WARNING';
            $insights[] = [
                'type' => 'sustained_cpu_process',
                'severity' => $severity,
                'process' => $row['name'],
                'label' => "{$row['name']} memakai CPU tinggi secara berulang (rata-rata " . round($avg, 1) . "%, puncak " . round($max, 1) . "%).",
                'suggestion' => 'Cek apakah proses ini memang sedang menjalankan pekerjaan berat. Jika tidak, tutup atau restart aplikasinya.',
            ];
        }
        return $insights;
    }

    private function detectMemoryGrowth($snapshots): array
    {
        $series = [];
        foreach ($snapshots as $snapshot) {
            foreach (($snapshot->by_memory ?? []) as $process) {
                $name = $this->normalizeName($process['name'] ?? 'unknown');
                $series[$name]['name'] = $process['name'] ?? $name;
                $series[$name]['memory'][] = (float) ($process['memory_mb'] ?? 0);
            }
        }

        $insights = [];
        foreach ($series as $row) {
            $values = $row['memory'] ?? [];
            if (count($values) < 3) continue;
            $first = reset($values);
            $last = end($values);
            $growth = $last - $first;
            $isMostlyRising = $this->mostlyRising($values);

            if (!$isMostlyRising || $growth < 150) continue;

            $severity = $growth >= 500 || $last >= 2500 ? 'CRITICAL' : 'WARNING';
            $insights[] = [
                'type' => 'memory_growth_process',
                'severity' => $severity,
                'process' => $row['name'],
                'label' => "RAM {$row['name']} naik sekitar " . round($growth, 0) . " MB dalam window terbaru.",
                'suggestion' => 'Pantau proses ini. Jika terus naik tanpa turun, ada indikasi memory leak atau tab/aplikasi terlalu berat.',
            ];
        }
        return $insights;
    }

    private function detectHeavyMemoryProcess($snapshots): array
    {
        $latest = $snapshots->last();
        if (!$latest) return [];

        $insights = [];
        foreach (($latest->by_memory ?? []) as $process) {
            $memory = (float) ($process['memory_mb'] ?? 0);
            if ($memory < 1800) continue;

            $severity = $memory >= 3500 ? 'CRITICAL' : 'WARNING';
            $name = $process['name'] ?? 'unknown';
            $insights[] = [
                'type' => 'heavy_memory_process',
                'severity' => $severity,
                'process' => $name,
                'label' => "{$name} memakai RAM besar sekitar " . round($memory, 0) . " MB.",
                'suggestion' => 'Tutup tab/proyek yang tidak diperlukan atau restart aplikasi jika performa terasa lambat.',
            ];
        }
        return $insights;
    }

    private function mostlyRising(array $values): bool
    {
        $rising = 0;
        $falling = 0;
        for ($i = 1; $i < count($values); $i++) {
            $diff = $values[$i] - $values[$i - 1];
            if ($diff > 20) $rising++;
            if ($diff < -20) $falling++;
        }
        return $rising >= 2 && $rising > $falling;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim($name));
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'CRITICAL' => 3,
            'WARNING' => 2,
            default => 1,
        };
    }
}