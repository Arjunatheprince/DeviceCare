<?php

namespace App\Services;

use App\Models\Device;
use App\Models\ProcessSnapshot;
use App\Models\SensorReading;

class RootCauseAnalysisService
{
    public function analyze(Device $device): array
    {
        $reading = $device->latestReading();
        if (!$reading) {
            return [
                'status' => 'NO_DATA',
                'summary' => 'Belum ada reading untuk dianalisis.',
                'causes' => [],
            ];
        }

        $processSnapshot = ProcessSnapshot::where('device_id', $device->id)
            ->where('recorded_at', '>=', $reading->recorded_at?->copy()->subMinutes(3) ?? now()->subMinutes(3))
            ->latest('recorded_at')
            ->first();

        $causes = [];
        $causes = array_merge($causes, $this->cpuCauses($reading, $processSnapshot));
        $causes = array_merge($causes, $this->memoryCauses($reading, $processSnapshot));
        $causes = array_merge($causes, $this->storageCauses($reading));
        $causes = array_merge($causes, $this->thermalCauses($reading));
        $causes = array_merge($causes, $this->batteryCauses($reading));

        usort($causes, fn ($a, $b) => $this->severityRank($b['severity']) <=> $this->severityRank($a['severity']));
        $causes = array_slice($causes, 0, 5);

        $status = empty($causes) ? 'NORMAL' : $causes[0]['severity'];

        return [
            'status' => $status,
            'summary' => empty($causes)
                ? 'Belum ada akar masalah yang menonjol dari reading terbaru.'
                : $this->buildSummary($causes[0]),
            'reading_id' => $reading->id,
            'recorded_at' => $reading->recorded_at?->toIso8601String(),
            'process_recorded_at' => $processSnapshot?->recorded_at?->toIso8601String(),
            'causes' => $causes,
        ];
    }

    private function cpuCauses(SensorReading $reading, ?ProcessSnapshot $snapshot): array
    {
        if (($reading->cpu_usage ?? 0) < 80) return [];

        $top = $this->topProcess($snapshot?->by_cpu ?? [], 'cpu_percent');
        $processText = $top
            ? " Proses paling dominan: {$top['name']} memakai " . round((float) $top['cpu_percent'], 1) . '% CPU.'
            : ' Data proses belum tersedia untuk membaca penyebab aplikasi.';

        return [[
            'type' => 'high_cpu_process',
            'severity' => ($reading->cpu_usage >= 95 ? 'CRITICAL' : 'WARNING'),
            'component' => 'cpu',
            'title' => 'CPU tinggi terdeteksi',
            'message' => 'CPU usage mencapai ' . round((float) $reading->cpu_usage, 1) . '%.'. $processText,
            'suggestion' => 'Cek proses tersebut di Task Manager atau tutup aplikasi berat jika tidak diperlukan.',
        ]];
    }

    private function memoryCauses(SensorReading $reading, ?ProcessSnapshot $snapshot): array
    {
        if (($reading->ram_usage ?? 0) < 85) return [];

        $top = $this->topProcess($snapshot?->by_memory ?? [], 'memory_mb');
        $processText = $top
            ? " Pemakai RAM terbesar: {$top['name']} sekitar " . round((float) $top['memory_mb'], 0) . ' MB.'
            : ' Data proses belum tersedia untuk membaca pemakai RAM terbesar.';

        return [[
            'type' => 'high_memory_process',
            'severity' => ($reading->ram_usage >= 94 ? 'CRITICAL' : 'WARNING'),
            'component' => 'ram',
            'title' => 'RAM hampir penuh',
            'message' => 'RAM usage mencapai ' . round((float) $reading->ram_usage, 1) . '%.'. $processText,
            'suggestion' => 'Tutup tab/aplikasi berat, lalu pantau apakah RAM turun dalam beberapa menit.',
        ]];
    }

    private function storageCauses(SensorReading $reading): array
    {
        if (($reading->disk_usage ?? 0) < 90) return [];

        return [[
            'type' => 'storage_pressure',
            'severity' => ($reading->disk_usage >= 96 ? 'CRITICAL' : 'WARNING'),
            'component' => 'disk',
            'title' => 'Storage menekan performa',
            'message' => 'Storage usage mencapai ' . round((float) $reading->disk_usage, 1) . '%, sisa sekitar ' . ($reading->disk_free_gb ?? 'N/A') . ' GB.',
            'suggestion' => 'Kosongkan file besar, cache, atau pindahkan data sebelum sistem menjadi lambat.',
        ]];
    }

    private function thermalCauses(SensorReading $reading): array
    {
        $temp = (float) ($reading->cpu_temperature ?? 0);
        $usage = (float) ($reading->cpu_usage ?? 0);
        $clock = (float) ($reading->cpu_frequency_mhz ?? 0);
        if ($temp < 85) return [];

        $looksThrottled = $usage >= 70 && $clock > 0 && $clock < 1800;
        return [[
            'type' => $looksThrottled ? 'thermal_throttling' : 'thermal_pressure',
            'severity' => ($temp >= 95 || $looksThrottled ? 'CRITICAL' : 'WARNING'),
            'component' => 'cpu',
            'title' => $looksThrottled ? 'Indikasi thermal throttling' : 'Suhu CPU tinggi',
            'message' => $looksThrottled
                ? "CPU panas ({$temp} C), usage tinggi ({$usage}%), tetapi clock turun ke {$clock} MHz. Ini mengarah ke thermal throttling."
                : "Suhu CPU mencapai {$temp} C. Jika terjadi saat beban ringan, kemungkinan airflow atau pendinginan bermasalah.",
            'suggestion' => 'Bersihkan ventilasi/kipas, gunakan permukaan rata, dan pertimbangkan cek thermal paste jika sering terjadi.',
        ]];
    }

    private function batteryCauses(SensorReading $reading): array
    {
        $wear = $reading->battery_wear_level;
        if ($wear === null || $wear < 25) return [];

        return [[
            'type' => 'battery_wear',
            'severity' => ($wear >= 35 ? 'CRITICAL' : 'WARNING'),
            'component' => 'battery',
            'title' => 'Battery wear tinggi',
            'message' => 'Battery wear sekitar ' . round((float) $wear, 1) . '%. Kapasitas efektif baterai mulai turun signifikan.',
            'suggestion' => 'Pantau cycle count dan pertimbangkan mode pengisian sehat jika tersedia.',
        ]];
    }

    private function topProcess(array $processes, string $key): ?array
    {
        if (empty($processes)) return null;
        usort($processes, fn ($a, $b) => ((float) ($b[$key] ?? 0)) <=> ((float) ($a[$key] ?? 0)));
        return $processes[0] ?? null;
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'CRITICAL' => 3,
            'WARNING' => 2,
            default => 1,
        };
    }

    private function buildSummary(array $cause): string
    {
        return $cause['title'] . ': ' . $cause['message'];
    }
}