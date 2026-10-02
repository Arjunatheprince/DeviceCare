<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SensorReading;

class HealthScoreBreakdownService
{
    private const WEIGHTS = [
        'cpu' => 25,
        'ram' => 20,
        'disk' => 15,
        'battery' => 20,
        'gpu' => 10,
        'smart' => 10,
    ];

    public function analyze(Device $device): array
    {
        $reading = $device->latestReading();
        if (!$reading) {
            return [
                'score' => null,
                'status' => 'NO_DATA',
                'components' => [],
                'top_penalties' => [],
                'summary' => 'Belum ada health score untuk dianalisis.',
            ];
        }

        $components = [];
        foreach (self::WEIGHTS as $component => $weight) {
            $status = $this->componentStatus($reading, $component);
            $penalty = $this->penaltyFor($status, $weight);
            $components[$component] = [
                'component' => $component,
                'label' => $this->label($component),
                'weight' => $weight,
                'status' => $status,
                'score_contribution' => max(0, $weight - $penalty),
                'penalty' => $penalty,
                'reason' => $this->reason($reading, $component, $status),
            ];
        }

        $topPenalties = collect($components)
            ->filter(fn ($row) => $row['penalty'] > 0)
            ->sortByDesc('penalty')
            ->take(4)
            ->values()
            ->all();

        $score = $reading->health_score;
        $status = $score === null ? 'NO_DATA' : ($score >= 80 ? 'NORMAL' : ($score >= 60 ? 'WARNING' : 'CRITICAL'));

        return [
            'score' => $score,
            'status' => $status,
            'components' => array_values($components),
            'top_penalties' => $topPenalties,
            'summary' => $this->summary($score, $topPenalties),
        ];
    }

    private function componentStatus(SensorReading $reading, string $component): string
    {
        return match ($component) {
            'cpu' => $reading->cpu_status ?: $this->statusByThreshold($reading->cpu_temperature, 85, 95),
            'ram' => $reading->ram_status ?: $this->statusByThreshold($reading->ram_usage, 75, 90),
            'disk' => $reading->disk_status ?: $this->statusByThreshold($reading->disk_usage, 85, 95),
            'battery' => $reading->battery_status ?: $this->batteryStatus($reading),
            'gpu' => $reading->gpu_status ?: $this->statusByThreshold($reading->gpu_temperature, 85, 95),
            'smart' => $reading->smart_status ?: 'NORMAL',
            default => 'UNKNOWN',
        };
    }

    private function statusByThreshold(null|int|float $value, float $warning, float $critical): string
    {
        if ($value === null || $value <= 0) return 'UNKNOWN';
        return match (true) {
            $value >= $critical => 'CRITICAL',
            $value >= $warning => 'WARNING',
            default => 'NORMAL',
        };
    }

    private function batteryStatus(SensorReading $reading): string
    {
        if ($reading->battery_percentage === null) return 'UNKNOWN';
        if (!$reading->battery_charging && $reading->battery_percentage <= 10) return 'CRITICAL';
        if (!$reading->battery_charging && $reading->battery_percentage <= 20) return 'WARNING';
        if (($reading->battery_wear_level ?? 0) >= 35) return 'CRITICAL';
        if (($reading->battery_wear_level ?? 0) >= 25) return 'WARNING';
        return 'NORMAL';
    }

    private function penaltyFor(string $status, int $weight): float
    {
        return match ($status) {
            'CRITICAL' => $weight,
            'WARNING' => round($weight * 0.45, 1),
            'UNKNOWN' => round($weight * 0.2, 1),
            default => 0,
        };
    }

    private function reason(SensorReading $reading, string $component, string $status): string
    {
        if ($status === 'NORMAL') return 'Komponen berada dalam batas normal.';
        if ($status === 'UNKNOWN') return 'Data komponen belum lengkap.';

        return match ($component) {
            'cpu' => 'CPU usage ' . ($reading->cpu_usage ?? 'N/A') . '%, suhu ' . ($reading->cpu_temperature ?? 'N/A') . ' C.',
            'ram' => 'RAM usage ' . ($reading->ram_usage ?? 'N/A') . '%, tersedia ' . ($reading->ram_available_gb ?? 'N/A') . ' GB.',
            'disk' => 'Storage usage ' . ($reading->disk_usage ?? 'N/A') . '%, sisa ' . ($reading->disk_free_gb ?? 'N/A') . ' GB.',
            'battery' => 'Baterai ' . ($reading->battery_percentage ?? 'N/A') . '%, wear ' . ($reading->battery_wear_level ?? 'N/A') . '%.',
            'gpu' => 'GPU usage ' . ($reading->gpu_usage ?? 'N/A') . '%, suhu ' . ($reading->gpu_temperature ?? 'N/A') . ' C.',
            'smart' => 'SMART status: ' . ($reading->smart_health_status ?? $reading->smart_status ?? 'N/A') . '.',
            default => 'Komponen memerlukan pemeriksaan.',
        };
    }

    private function label(string $component): string
    {
        return match ($component) {
            'cpu' => 'CPU',
            'ram' => 'RAM',
            'disk' => 'Storage',
            'battery' => 'Battery',
            'gpu' => 'GPU',
            'smart' => 'SMART Disk',
            default => strtoupper($component),
        };
    }

    private function summary(?int $score, array $topPenalties): string
    {
        if ($score === null) return 'Health score belum tersedia.';
        if (empty($topPenalties)) return 'Health score tidak memiliki faktor penurun besar saat ini.';

        $main = $topPenalties[0];
        return "Faktor terbesar yang menurunkan skor: {$main['label']} ({$main['status']}). {$main['reason']}";
    }
}