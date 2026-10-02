<?php

namespace App\Services;

use App\Models\Device;

class StorageAnalysisService
{
    public function analyze(Device $device, int $days = 14): array
    {
        $days = max(3, min($days, 60));
        $latest = $device->latestReading();
        if (!$latest) {
            return ['status' => 'NO_DATA', 'summary' => 'Belum ada data storage.', 'insights' => [], 'metrics' => []];
        }

        $history = $device->sensorReadings()
            ->where('recorded_at', '>=', now()->subDays($days))
            ->whereNotNull('disk_usage')
            ->orderBy('recorded_at')
            ->get(['disk_usage', 'disk_free_gb', 'recorded_at']);

        $usage = (float) ($latest->disk_usage ?? 0);
        $free = $latest->disk_free_gb;
        $growth = null;
        if ($history->count() >= 2) {
            $first = (float) $history->first()->disk_usage;
            $last = (float) $history->last()->disk_usage;
            $spanDays = max(1, $history->first()->recorded_at->diffInDays($history->last()->recorded_at));
            $growth = round(($last - $first) / $spanDays, 2);
        }

        $insights = [];
        if ($usage >= 95) {
            $insights[] = ['severity' => 'CRITICAL', 'type' => 'storage_critical', 'label' => "Storage hampir penuh ({$usage}%).", 'suggestion' => 'Kosongkan file besar, cache, download lama, atau pindahkan data penting.'];
        } elseif ($usage >= 85) {
            $insights[] = ['severity' => 'WARNING', 'type' => 'storage_warning', 'label' => "Storage mulai penuh ({$usage}%).", 'suggestion' => 'Audit folder Download, cache aplikasi, video, installer, dan file sementara.'];
        }
        if ($growth !== null && $growth > 1) {
            $insights[] = ['severity' => $growth >= 3 ? 'CRITICAL' : 'WARNING', 'type' => 'storage_growth', 'label' => "Storage bertambah sekitar {$growth}% per hari.", 'suggestion' => 'Cari sumber pertumbuhan: rekaman video, cache browser, build artifact, atau backup otomatis.'];
        }

        $cleanupTargets = [
            ['name' => 'Temporary files', 'risk' => 'low', 'description' => 'File sementara Windows dan aplikasi.'],
            ['name' => 'Downloads lama', 'risk' => 'medium', 'description' => 'Installer, arsip, dan media yang tidak dipakai.'],
            ['name' => 'Cache browser/aplikasi', 'risk' => 'low', 'description' => 'Cache yang bisa dibuat ulang aplikasi.'],
            ['name' => 'File besar personal', 'risk' => 'manual', 'description' => 'Video, dataset, VM image, atau backup yang perlu dicek manual.'],
        ];

        return [
            'status' => empty($insights) ? 'NORMAL' : $insights[0]['severity'],
            'summary' => empty($insights) ? 'Storage terlihat aman.' : count($insights) . ' insight storage perlu diperhatikan.',
            'metrics' => ['usage_percent' => $usage ?: null, 'free_gb' => $free, 'growth_per_day' => $growth],
            'cleanup_targets' => $cleanupTargets,
            'insights' => $insights,
        ];
    }
}