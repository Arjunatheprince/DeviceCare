<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

class PredictiveMaintenanceService
{
    public function analyze(Device $device, int $days = 14): array
    {
        $days = max(3, min($days, 60));

        return array_values(array_filter([
            $this->predictStorage($device, $days),
            $this->predictBatteryWear($device, max($days, 30)),
            $this->predictCpuThermalDrift($device, $days),
        ]));
    }

    private function predictStorage(Device $device, int $days): ?array
    {
        $points = $this->dailyAverages($device->id, 'disk_usage', $days);
        if (count($points) < 3) return null;

        $trend = $this->linearTrend($points);
        $current = round((float) end($points)['value'], 1);
        $slope = round($trend['slope'], 3);

        if ($current >= 95) {
            return $this->prediction('storage_full', 'CRITICAL', 'Storage sudah di atas 95%. Segera kosongkan file besar atau pindahkan data penting.', $current, $slope, 0);
        }

        if ($slope <= 0.05) {
            return $this->prediction('storage_full', 'NORMAL', 'Storage stabil. Belum ada indikasi kuat akan penuh dalam waktu dekat.', $current, $slope, null);
        }

        $daysToFull = (int) ceil((95 - $current) / $slope);
        $severity = match (true) {
            $daysToFull <= 7 => 'CRITICAL',
            $daysToFull <= 30 => 'WARNING',
            default => 'NORMAL',
        };
        $label = "Storage diproyeksikan mencapai 95% sekitar {$daysToFull} hari lagi jika tren pemakaian tetap sama.";

        return $this->prediction('storage_full', $severity, $label, $current, $slope, $daysToFull);
    }

    private function predictBatteryWear(Device $device, int $days): ?array
    {
        $points = $this->dailyAverages($device->id, 'battery_wear_level', $days);
        if (count($points) < 3) return null;

        $trend = $this->linearTrend($points);
        $current = round((float) end($points)['value'], 1);
        $slope = round($trend['slope'], 3);

        if ($current >= 30) {
            return $this->prediction('battery_wear_70_capacity', 'CRITICAL', 'Battery wear sudah di atas 30%. Kapasitas efektif diperkirakan di bawah 70%.', $current, $slope, 0);
        }

        if ($slope <= 0.01) {
            return $this->prediction('battery_wear_70_capacity', 'NORMAL', 'Battery wear stabil. Belum ada proyeksi penurunan kapasitas yang mengkhawatirkan.', $current, $slope, null);
        }

        $daysToLimit = (int) ceil((30 - $current) / $slope);
        $severity = match (true) {
            $daysToLimit <= 30 => 'CRITICAL',
            $daysToLimit <= 120 => 'WARNING',
            default => 'NORMAL',
        };
        $label = "Battery wear diproyeksikan melewati 30% sekitar {$daysToLimit} hari lagi.";

        return $this->prediction('battery_wear_70_capacity', $severity, $label, $current, $slope, $daysToLimit);
    }

    private function predictCpuThermalDrift(Device $device, int $days): ?array
    {
        $points = $this->dailyAverages($device->id, 'cpu_temperature', $days);
        if (count($points) < 3) return null;

        $trend = $this->linearTrend($points);
        $current = round((float) end($points)['value'], 1);
        $slope = round($trend['slope'], 3);

        $severity = match (true) {
            $slope >= 1.0 => 'CRITICAL',
            $slope >= 0.35 => 'WARNING',
            default => 'NORMAL',
        };

        $label = $severity === 'NORMAL'
            ? 'Tren suhu CPU stabil. Tidak ada indikasi kenaikan suhu harian yang mencolok.'
            : "Suhu CPU rata-rata naik sekitar {$slope} C per hari. Ini bisa mengarah ke masalah debu, airflow, atau thermal paste.";

        return $this->prediction('cpu_thermal_drift', $severity, $label, $current, $slope, null);
    }

    private function dailyAverages(int $deviceId, string $metric, int $days): array
    {
        return DB::table('sensor_readings')
            ->select(DB::raw('DATE(recorded_at) AS day'), DB::raw("ROUND(AVG({$metric}), 3) AS value"))
            ->where('device_id', $deviceId)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->whereNotNull($metric)
            ->where($metric, '>', 0)
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => ['day' => $row->day, 'value' => (float) $row->value])
            ->all();
    }

    private function linearTrend(array $points): array
    {
        $n = count($points);
        $sumX = $sumY = $sumXY = $sumXX = 0.0;

        foreach (array_values($points) as $i => $point) {
            $x = (float) $i;
            $y = (float) $point['value'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator == 0.0 ? 0.0 : (($n * $sumXY) - ($sumX * $sumY)) / $denominator;

        return ['slope' => $slope];
    }

    private function prediction(string $type, string $severity, string $label, float $current, float $slopePerDay, ?int $daysToEvent): array
    {
        return [
            'type' => $type,
            'severity' => $severity,
            'label' => $label,
            'current_value' => $current,
            'slope_per_day' => $slopePerDay,
            'days_to_event' => $daysToEvent,
        ];
    }
}