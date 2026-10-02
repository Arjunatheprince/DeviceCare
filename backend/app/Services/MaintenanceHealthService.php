<?php

namespace App\Services;

use App\Models\Device;

class MaintenanceHealthService
{
    public function analyze(Device $device): array
    {
        $latest = $device->latestReading();
        $insights = [];

        if (!$latest) {
            return ['status' => 'NO_DATA', 'summary' => 'Belum ada data maintenance.', 'insights' => [], 'checks' => []];
        }

        if (($latest->smart_status ?? null) === 'CRITICAL' || ($latest->smart_health_status && !in_array(strtoupper((string) $latest->smart_health_status), ['OK', 'HEALTHY', 'GOOD']))) {
            $insights[] = ['severity' => 'CRITICAL', 'type' => 'smart_health', 'label' => 'SMART disk menunjukkan kondisi tidak sehat.', 'suggestion' => 'Backup data penting dan cek disk dengan tool vendor.'];
        }
        if (($latest->smart_pending_sectors ?? 0) > 0 || ($latest->smart_reallocated_sectors ?? 0) > 0) {
            $insights[] = ['severity' => 'WARNING', 'type' => 'smart_sectors', 'label' => 'Ada sektor disk bermasalah atau dialokasikan ulang.', 'suggestion' => 'Pantau tren sektor dan siapkan backup.'];
        }
        if (($latest->battery_wear_level ?? 0) >= 30) {
            $insights[] = ['severity' => 'WARNING', 'type' => 'battery_maintenance', 'label' => 'Battery wear sudah tinggi untuk pemakaian jangka panjang.', 'suggestion' => 'Gunakan charge limit jika tersedia dan hindari panas saat charging.'];
        }

        $checks = [
            ['name' => 'SMART Disk', 'status' => $latest->smart_status ?? 'UNKNOWN'],
            ['name' => 'Battery Wear', 'status' => ($latest->battery_wear_level ?? null) !== null ? round((float) $latest->battery_wear_level, 1) . '%' : 'UNKNOWN'],
            ['name' => 'Windows Defender', 'status' => 'NOT_REPORTED'],
            ['name' => 'Firewall', 'status' => 'NOT_REPORTED'],
            ['name' => 'Windows Update', 'status' => 'NOT_REPORTED'],
        ];

        return [
            'status' => empty($insights) ? 'NORMAL' : $insights[0]['severity'],
            'summary' => empty($insights) ? 'Tidak ada sinyal maintenance kritis dari data yang tersedia.' : count($insights) . ' item maintenance perlu perhatian.',
            'checks' => $checks,
            'insights' => $insights,
        ];
    }
}