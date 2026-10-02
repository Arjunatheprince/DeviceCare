<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

class BatteryIntelligenceService
{
    public function analyze(Device $device, int $hours = 24): array
    {
        $hours = max(3, min($hours, 168));
        $readings = $device->sensorReadings()
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->whereNotNull('battery_percentage')
            ->orderBy('recorded_at')
            ->get([
                'battery_percentage',
                'battery_charging',
                'battery_wear_level',
                'battery_cycle_count',
                'battery_time_remaining_min',
                'recorded_at',
            ]);

        if ($readings->count() < 3) {
            return [
                'status' => 'NO_DATA',
                'summary' => 'Belum cukup data baterai untuk analisis kebiasaan dan drain rate.',
                'insights' => [],
                'metrics' => [],
            ];
        }

        $latest = $readings->last();
        $insights = [];
        $insights = array_merge($insights, $this->chargingHabitInsights($readings));
        $insights = array_merge($insights, $this->wearInsights($latest));

        $drain = $this->estimateDrainRate($readings);
        if ($drain['rate_per_hour'] !== null) {
            $insights = array_merge($insights, $this->drainInsights($latest, $drain));
        }

        usort($insights, fn ($a, $b) => $this->severityRank($b['severity']) <=> $this->severityRank($a['severity']));
        $insights = array_slice($insights, 0, 5);

        return [
            'status' => empty($insights) ? 'NORMAL' : $insights[0]['severity'],
            'summary' => empty($insights)
                ? 'Pola baterai terlihat normal dalam window terbaru.'
                : count($insights) . ' insight baterai perlu diperhatikan.',
            'window_hours' => $hours,
            'sample_count' => $readings->count(),
            'metrics' => [
                'latest_percentage' => round((float) $latest->battery_percentage, 1),
                'latest_wear_level' => $latest->battery_wear_level !== null ? round((float) $latest->battery_wear_level, 1) : null,
                'latest_cycle_count' => $latest->battery_cycle_count,
                'estimated_drain_per_hour' => $drain['rate_per_hour'],
                'estimated_runtime_hours' => $drain['runtime_hours'],
            ],
            'insights' => $insights,
        ];
    }

    private function chargingHabitInsights($readings): array
    {
        $total = $readings->count();
        $highPlugged = $readings->filter(fn ($r) => $r->battery_charging && (float) $r->battery_percentage >= 95)->count();
        $deepDischarge = $readings->filter(fn ($r) => !$r->battery_charging && (float) $r->battery_percentage <= 10)->count();
        $insights = [];

        if ($total > 0 && ($highPlugged / $total) >= 0.7) {
            $insights[] = [
                'type' => 'high_charge_plugged_habit',
                'severity' => 'WARNING',
                'label' => 'Laptop sering berada di 95-100% sambil charging dalam window terbaru.',
                'suggestion' => 'Jika laptop mendukung charge limit, batasi pengisian sekitar 80% untuk mengurangi battery wear jangka panjang.',
            ];
        }

        if ($deepDischarge >= 3) {
            $insights[] = [
                'type' => 'deep_discharge_habit',
                'severity' => 'WARNING',
                'label' => 'Baterai beberapa kali turun ke 10% atau kurang saat tidak charging.',
                'suggestion' => 'Usahakan mulai charging sebelum baterai terlalu rendah untuk menjaga umur baterai.',
            ];
        }

        return $insights;
    }

    private function wearInsights($latest): array
    {
        $wear = $latest->battery_wear_level;
        if ($wear === null) return [];

        if ($wear >= 35) {
            return [[
                'type' => 'high_battery_wear',
                'severity' => 'CRITICAL',
                'label' => 'Battery wear sudah sekitar ' . round((float) $wear, 1) . '%. Kapasitas efektif turun signifikan.',
                'suggestion' => 'Pertimbangkan kalibrasi atau penggantian baterai jika runtime terasa jauh berkurang.',
            ]];
        }

        if ($wear >= 25) {
            return [[
                'type' => 'elevated_battery_wear',
                'severity' => 'WARNING',
                'label' => 'Battery wear sudah sekitar ' . round((float) $wear, 1) . '%.',
                'suggestion' => 'Pantau pola charging dan hindari panas berlebih saat laptop dicolok lama.',
            ]];
        }

        return [];
    }

    private function estimateDrainRate($readings): array
    {
        $discharging = $readings
            ->filter(fn ($r) => !$r->battery_charging && $r->battery_percentage !== null)
            ->values();

        if ($discharging->count() < 3) {
            return ['rate_per_hour' => null, 'runtime_hours' => null];
        }

        $segments = [];
        for ($i = 1; $i < $discharging->count(); $i++) {
            $prev = $discharging[$i - 1];
            $curr = $discharging[$i];
            $minutes = max(1, $prev->recorded_at->diffInMinutes($curr->recorded_at));
            $drop = (float) $prev->battery_percentage - (float) $curr->battery_percentage;
            if ($drop <= 0) continue;
            $segments[] = ($drop / $minutes) * 60;
        }

        if (empty($segments)) {
            return ['rate_per_hour' => null, 'runtime_hours' => null];
        }

        $rate = round(array_sum($segments) / count($segments), 2);
        $latestPct = (float) $readings->last()->battery_percentage;
        $runtime = $rate > 0 ? round($latestPct / $rate, 1) : null;

        return ['rate_per_hour' => $rate, 'runtime_hours' => $runtime];
    }

    private function drainInsights($latest, array $drain): array
    {
        $rate = $drain['rate_per_hour'];
        if ($rate === null) return [];

        if ($rate >= 30) {
            return [[
                'type' => 'fast_battery_drain',
                'severity' => 'CRITICAL',
                'label' => "Baterai terkuras cepat, sekitar {$rate}% per jam saat tidak charging.",
                'suggestion' => 'Cek proses berat, brightness, mode performa, dan aplikasi background.',
            ]];
        }

        if ($rate >= 18) {
            return [[
                'type' => 'elevated_battery_drain',
                'severity' => 'WARNING',
                'label' => "Laju baterai cukup tinggi, sekitar {$rate}% per jam saat tidak charging.",
                'suggestion' => 'Gunakan mode hemat daya atau cek aplikasi yang memakai CPU/GPU.',
            ]];
        }

        return [];
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