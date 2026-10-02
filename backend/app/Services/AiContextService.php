<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * AiContextService
 *
 * Mengumpulkan semua data relevan perangkat dan menyusunnya menjadi
 * system prompt yang informatif untuk AI Assistant.
 *
 * Prinsip: AI harus menjelaskan berdasarkan DATA, bukan asal tebak.
 * Semakin kaya konteks yang diberikan, semakin akurat penjelasan AI.
 *
 * Konteks yang dikumpulkan:
 *   1. Profil device (CPU, GPU, RAM, OS)
 *   2. Sensor reading terbaru (semua metrik + status)
 *   3. Ringkasan 7 hari terakhir (avg per hari)
 *   4. Anomali aktif (dari baseline Z-score)
 *   5. Alert yang belum resolved
 *   6. Consistent trend (kenaikan/penurunan konsisten)
 */
class AiContextService
{
    /**
     * Buat system prompt lengkap untuk satu device.
     * Ini dikirim sebagai "system" message ke OpenAI.
     */
    public function buildSystemPrompt(Device $device): string
    {
        $parts = [];

        $parts[] = $this->roleInstruction();
        $parts[] = $this->deviceProfile($device);
        $parts[] = $this->currentReadingSection($device);
        $parts[] = $this->weeklyTrendSection($device);
        $parts[] = $this->anomalySection($device);
        $parts[] = $this->alertSection($device);
        $parts[] = $this->responseGuide();

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Buat ringkasan konteks singkat untuk ditampilkan di UI
     * (agar user tahu AI sudah punya data apa).
     */
    public function buildContextSummary(Device $device): array
    {
        $latest   = $device->latestReading();
        $alerts   = \App\Models\Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->count();

        return [
            'device_name'    => $device->device_name,
            'has_reading'    => $latest !== null,
            'recorded_at'    => $latest?->recorded_at?->toIso8601String(),
            'health_score'   => $latest?->health_score,
            'active_alerts'  => $alerts,
            'baseline_ready' => (bool) $device->baseline_ready,
        ];
    }

    // ── private builders ──────────────────────────────────────────────────────

    private function roleInstruction(): string
    {
        return <<<PROMPT
Kamu adalah DeviceCare AI Assistant, asisten kesehatan hardware laptop yang cerdas dan membantu.

Tugasmu:
- Menjelaskan kondisi hardware laptop berdasarkan data sensor nyata yang diberikan
- Memberikan diagnosis yang kontekstual, bukan sekadar membaca angka
- Memberikan saran yang actionable dan spesifik
- Menggunakan bahasa Indonesia yang jelas dan mudah dipahami
- Bersikap jujur jika ada kondisi yang perlu diperhatikan

Yang TIDAK boleh kamu lakukan:
- Mengarang data atau angka yang tidak ada dalam konteks
- Memberikan diagnosis medis atau keputusan bisnis
- Menjawab pertanyaan yang tidak berkaitan dengan hardware/performa laptop

Jika data tidak tersedia untuk menjawab pertanyaan, katakan dengan jujur.
PROMPT;
    }

    private function deviceProfile(Device $device): string
    {
        $lines = ["=== PROFIL PERANGKAT ==="];
        $lines[] = "Nama         : {$device->device_name}";
        $lines[] = "Manufacturer : " . ($device->manufacturer ?? 'Unknown');
        $lines[] = "Model        : " . ($device->model ?? 'Unknown');
        $lines[] = "CPU          : " . ($device->cpu ?? 'Unknown');
        $lines[] = "GPU          : " . ($device->gpu ?? 'Unknown');
        $lines[] = "RAM Total    : " . ($device->ram_total_gb ? $device->ram_total_gb . ' GB' : 'Unknown');
        $lines[] = "Storage Total: " . ($device->disk_total_gb ? $device->disk_total_gb . ' GB' : 'Unknown');
        $lines[] = "OS           : " . ($device->operating_system ?? 'Unknown');
        $lines[] = "OS Version   : " . ($device->os_version ?? 'Unknown');

        return implode("\n", $lines);
    }

    private function currentReadingSection(Device $device): string
    {
        $r = $device->latestReading();
        if (!$r) {
            return "=== DATA SENSOR TERKINI ===\nBelum ada data sensor tersedia.";
        }

        $age = $r->recorded_at
            ? now()->diffInSeconds($r->recorded_at) . ' detik yang lalu'
            : 'Waktu tidak diketahui';

        $lines = ["=== DATA SENSOR TERKINI (direkam {$age}) ==="];

        // CPU
        $cpuTemp = $r->cpu_temperature ? $r->cpu_temperature . '°C' : 'N/A';
        $lines[] = "CPU Usage       : {$r->cpu_usage}% — Status: {$r->cpu_status}";
        $lines[] = "CPU Temperature : {$cpuTemp}";
        $lines[] = "CPU Frequency   : " . ($r->cpu_frequency_mhz ? $r->cpu_frequency_mhz . ' MHz' : 'N/A');

        // GPU
        if ($r->gpu_usage !== null || $r->gpu_temperature !== null) {
            $gpuTemp = $r->gpu_temperature ? $r->gpu_temperature . '°C' : 'N/A';
            $lines[] = "GPU Usage       : " . ($r->gpu_usage !== null ? $r->gpu_usage . '%' : 'N/A') . " — Status: " . ($r->gpu_status ?? 'N/A');
            $lines[] = "GPU Temperature : {$gpuTemp}";
        }

        // RAM
        $ramUsed = $r->ram_used_gb ? $r->ram_used_gb . ' GB digunakan' : '';
        $lines[] = "RAM Usage       : {$r->ram_usage}% {$ramUsed} — Status: {$r->ram_status}";

        // Disk
        $diskFree = $r->disk_free_gb ? $r->disk_free_gb . ' GB tersisa' : '';
        $lines[] = "Disk Usage      : {$r->disk_usage}% {$diskFree} — Status: {$r->disk_status}";

        // Battery
        if ($r->battery_percentage !== null) {
            $charging = $r->battery_charging ? 'Mengisi daya' : 'Menggunakan baterai';
            $remaining = $r->battery_time_remaining_min
                ? ', sisa ' . round($r->battery_time_remaining_min / 60, 1) . ' jam'
                : '';
            $lines[] = "Battery         : {$r->battery_percentage}% ({$charging}{$remaining}) — Status: {$r->battery_status}";
        }

        // Network
        if ($r->network_download_kbps !== null) {
            $lines[] = "Network         : ↓{$r->network_download_kbps} KB/s  ↑{$r->network_upload_kbps} KB/s";
        }

        // Health Score
        $lines[] = "Health Score    : {$r->health_score}/100";

        return implode("\n", $lines);
    }

    private function weeklyTrendSection(Device $device): string
    {
        $rows = DB::table('sensor_readings')
            ->select(
                DB::raw("DATE(recorded_at) AS day"),
                DB::raw("ROUND(AVG(cpu_temperature), 1)    AS cpu_temp"),
                DB::raw("ROUND(AVG(cpu_usage), 1)          AS cpu_usage"),
                DB::raw("ROUND(AVG(ram_usage), 1)          AS ram_usage"),
                DB::raw("ROUND(AVG(health_score), 1)       AS health"),
                DB::raw("ROUND(AVG(gpu_temperature), 1)    AS gpu_temp"),
            )
            ->where('device_id', $device->id)
            ->where('recorded_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw("DATE(recorded_at)"))
            ->orderBy('day')
            ->get();

        if ($rows->isEmpty()) {
            return "=== TREN 7 HARI TERAKHIR ===\nBelum ada data historis yang cukup.";
        }

        $lines = ["=== TREN 7 HARI TERAKHIR ==="];
        $lines[] = sprintf("%-12s %-10s %-10s %-10s %-8s %-10s", "Tanggal", "CPU Temp", "CPU %", "RAM %", "Score", "GPU Temp");
        $lines[] = str_repeat("-", 62);

        foreach ($rows as $r) {
            $lines[] = sprintf(
                "%-12s %-10s %-10s %-10s %-8s %-10s",
                $r->day,
                $r->cpu_temp  ? $r->cpu_temp . '°C'  : 'N/A',
                $r->cpu_usage ? $r->cpu_usage . '%'  : 'N/A',
                $r->ram_usage ? $r->ram_usage . '%'  : 'N/A',
                $r->health    ? $r->health            : 'N/A',
                $r->gpu_temp  ? $r->gpu_temp . '°C'  : 'N/A',
            );
        }

        // Hitung trend sederhana
        if ($rows->count() >= 3) {
            $firstHealth = $rows->first()->health;
            $lastHealth  = $rows->last()->health;
            if ($firstHealth && $lastHealth) {
                $diff = round($lastHealth - $firstHealth, 1);
                $dir  = $diff > 0 ? "membaik (+{$diff})" : ($diff < 0 ? "memburuk ({$diff})" : "stabil");
                $lines[] = "\nHealth score trend minggu ini: {$dir}";
            }
        }

        return implode("\n", $lines);
    }

    private function anomalySection(Device $device): string
    {
        if (!$device->baseline_ready || !$device->baseline_data) {
            return '';
        }

        $latest = $device->latestReading();
        if (!$latest) return '';

        try {
            $anomalyService = new AnomalyDetectionService();
            $anomalies      = $anomalyService->detect($device, $latest, $device->baseline_data);
        } catch (\Throwable $e) {
            return '';
        }

        if (empty($anomalies)) {
            return "=== ANOMALI TERDETEKSI ===\nTidak ada anomali — semua metrik dalam pola normal perangkat ini.";
        }

        $lines = ["=== ANOMALI TERDETEKSI (berdasarkan baseline {$device->baseline_days} hari) ==="];
        foreach ($anomalies as $a) {
            $unit = str_contains($a['metric'], 'temperature') ? '°C' : '%';
            $lines[] = "• [{$a['severity']}] {$a['label']}";
            $lines[] = "  Z-score: {$a['z_score']} | Baseline avg: {$a['baseline_mean']}{$unit} ± {$a['baseline_stddev']}{$unit}";
            $lines[] = "  Saran: {$a['suggestion']}";
        }

        return implode("\n", $lines);
    }

    private function alertSection(Device $device): string
    {
        $alerts = \App\Models\Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($alerts->isEmpty()) {
            return "=== ALERT AKTIF ===\nTidak ada alert aktif saat ini.";
        }

        $lines = ["=== ALERT AKTIF (" . $alerts->count() . " belum resolved) ==="];
        foreach ($alerts as $alert) {
            $age     = $alert->created_at?->diffForHumans() ?? '';
            $lines[] = "• [{$alert->severity}] {$alert->title} ({$age})";
            if ($alert->message) {
                $lines[] = "  " . self::Str_truncate($alert->message, 120);
            }
        }

        return implode("\n", $lines);
    }

    private function responseGuide(): string
    {
        return <<<GUIDE
=== PANDUAN MENJAWAB ===
- Jawab dalam bahasa Indonesia
- Gunakan data di atas sebagai dasar penjelasan
- Struktur jawaban: ringkasan kondisi → analisis → saran konkret
- Jika kondisi kritis, tekankan urgensi dengan jelas
- Maksimal 3-4 paragraf kecuali diminta detail
- Jangan ulangi semua angka di atas, cukup angka yang relevan dengan pertanyaan
GUIDE;
    }

    // ── helper ────────────────────────────────────────────────────────────────

    private static function Str_truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit) . '...'
            : $text;
    }
}
