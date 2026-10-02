<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\SensorReading;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertService
{
    /**
     * Komponen yang butuh alert.
     * Hanya buat alert untuk WARNING dan CRITICAL, bukan NORMAL.
     */
    private const ALERT_STATUSES = ['WARNING', 'CRITICAL'];

    /**
     * Cooldown per severity untuk alert diagnostic biasa (menit).
     * Cukup jarang — user tidak perlu diingatkan setiap 3 menit untuk kondisi sama.
     */
    private const COOLDOWN = [
        'CRITICAL' => 60,   // 1 jam
        'WARNING'  => 120,  // 2 jam
    ];

    /**
     * Konfirmasi durasi untuk WARNING agar spike 1 detik tidak langsung menjadi alert.
     * CRITICAL tetap langsung dibuat.
     */
    private const CONFIRMATION_WINDOW_MINUTES = 2;
    private const CONFIRMATION_READINGS = [
        'CRITICAL' => 1,
        'WARNING'  => 3,
    ];

    /**
     * Cooldown untuk alert anomali (lebih panjang — anomali tidak setiap menit).
     * Anomali Z-score butuh waktu untuk terkonfirmasi, hindari spam.
     */
    private const ANOMALY_COOLDOWN = [
        'CRITICAL' => 60,   // 1 jam
        'WARNING'  => 120,  // 2 jam
    ];

    /**
     * Label komponen untuk notifikasi.
     */
    private const LABELS = [
        'cpu'     => 'CPU',
        'gpu'     => 'GPU',
        'ram'     => 'RAM',
        'disk'    => 'Storage',
        'battery' => 'Battery',
        'overall' => 'Overall Health',
    ];

    /**
     * Label metrik untuk notifikasi anomali.
     */
    private const METRIC_LABELS = [
        'cpu_temperature'    => 'Suhu CPU',
        'gpu_temperature'    => 'Suhu GPU',
        'cpu_usage'          => 'CPU Usage',
        'ram_usage'          => 'RAM Usage',
        'disk_usage'         => 'Disk Usage',
        'battery_percentage' => 'Battery Level',
        'health_score'       => 'Health Score',
    ];

    // ── Diagnostic alerts (threshold-based) ──────────────────────────────────

    /**
     * Proses satu reading dan buat alert jika ada komponen WARNING/CRITICAL.
     * Dipanggil dari SensorReadingController setelah menyimpan reading.
     */
    public function processReading(Device $device, SensorReading $reading, array $components): void
    {
        foreach ($components as $comp => $data) {
            $status = $data['status'] ?? null;
            if (!in_array($status, self::ALERT_STATUSES)) continue;

            if (!$this->isConfirmedDiagnosticStatus($device, $comp, $status)) {
                Log::debug("[AlertService] {$comp} {$status} belum terkonfirmasi durasi, alert ditunda.");
                continue;
            }

            $cooldown = self::COOLDOWN[$status] ?? 60;

            // Skip jika masih dalam cooldown untuk komponen ini
            if (Alert::inCooldown($device->id, $comp, $cooldown)) continue;

            // Skip jika alert terakhir untuk komponen ini belum resolved dan severity sama
            // (kondisi tidak berubah — tidak perlu alert baru)
            $lastAlert = Alert::where('device_id', $device->id)
                ->where('component', $comp)
                ->where('is_resolved', false)
                ->latest()
                ->first();
            if ($lastAlert && $lastAlert->severity === $status) continue;

            $label   = self::LABELS[$comp] ?? strtoupper($comp);
            $message = $data['message'] ?? '';
            $suggs   = $data['suggestions'] ?? [];

            $alert = Alert::create([
                'device_id'         => $device->id,
                'sensor_reading_id' => $reading->id,
                'component'         => $comp,
                'severity'          => $status,
                'title'             => "{$status}: {$label} — {$device->device_name}",
                'message'           => $message,
                'suggestions'       => $suggs,
                'notified_at'       => now(),
            ]);

            $this->sendEmail($alert, $device);

            Log::info("[AlertService] Alert #{$alert->id} dibuat: {$comp} {$status} — {$device->device_name}");
        }
    }

    // ── Anomaly alerts (Z-score based) ───────────────────────────────────────

    /**
     * Proses hasil deteksi anomali dari AnomalyDetectionService dan buat alert.
     *
     * Dipanggil dari SensorReadingController setelah baseline tersedia.
     * Menggunakan cooldown lebih panjang daripada diagnostic alert biasa.
     *
     * @param  Device        $device
     * @param  SensorReading $reading
     * @param  array         $anomalies  Output dari AnomalyDetectionService::detect()
     */
    public function processAnomalies(Device $device, SensorReading $reading, array $anomalies): void
    {
        if (empty($anomalies)) return;

        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'];         // 'WARNING' | 'CRITICAL'
            $metric   = $anomaly['metric'];
            $label    = self::METRIC_LABELS[$metric] ?? strtoupper($metric);

            // Cooldown lebih panjang untuk anomali — hindari spam z-score
            $cooldownKey = "anomaly_{$metric}";
            $cooldown    = self::ANOMALY_COOLDOWN[$severity] ?? 120;

            if (Alert::inCooldown($device->id, $cooldownKey, $cooldown)) continue;

            // Skip jika alert anomali untuk metrik ini masih aktif dengan severity sama
            $lastAnomaly = Alert::where('device_id', $device->id)
                ->where('component', $cooldownKey)
                ->where('is_resolved', false)
                ->latest()->first();
            if ($lastAnomaly && $lastAnomaly->severity === $severity) continue;

            $zScore = $anomaly['z_score'] ?? null;
            $unit   = $this->metricUnit($metric);

            // Bangun pesan kontekstual
            $message = sprintf(
                '%s — nilai saat ini %s%s, %.1f%s di luar rata-rata normal perangkat ini (%s%s ± %s%s).',
                $anomaly['label'],
                $anomaly['current_value'],
                $unit,
                abs($anomaly['current_value'] - $anomaly['baseline_mean']),
                $unit,
                $anomaly['baseline_mean'],
                $unit,
                $anomaly['baseline_stddev'],
                $unit
            );

            $suggestions = [$anomaly['suggestion']];

            $title = sprintf(
                'ANOMALI %s: %s — %s',
                $severity,
                $label,
                $device->device_name
            );

            $alert = Alert::create([
                'device_id'         => $device->id,
                'sensor_reading_id' => $reading->id,
                'component'         => $cooldownKey,   // key unik per metrik anomali
                'severity'          => $severity,
                'title'             => $title,
                'message'           => $message,
                'suggestions'       => $suggestions,
                'notified_at'       => now(),
            ]);

            $this->sendEmail($alert, $device, isAnomaly: true);

            Log::info(sprintf(
                '[AlertService] Anomaly alert #%d: %s %s (z=%.2f) — %s',
                $alert->id,
                $metric,
                $severity,
                $zScore ?? 0,
                $device->device_name
            ));
        }
    }

    /**
     * Proses consistent trend alerts (kenaikan N hari berturut-turut).
     *
     * @param  Device        $device
     * @param  SensorReading $reading
     * @param  array         $consistentTrends  Output dari TrendAnalysisService::detectConsistentTrend()
     */
    public function processConsistentTrends(Device $device, SensorReading $reading, array $consistentTrends): void
    {
        if (empty($consistentTrends)) return;

        foreach ($consistentTrends as $trend) {
            $metric  = $trend['metric'];
            $label   = self::METRIC_LABELS[$metric] ?? strtoupper($metric);

            // Cooldown 4 jam untuk consistent trend — ini bukan alert urgent
            $cooldownKey = "trend_{$metric}";
            if (Alert::inCooldown($device->id, $cooldownKey, 240)) continue;

            $message = sprintf(
                '%s Ini mengindikasikan perubahan kondisi yang konsisten, bukan fluktuasi sesaat.',
                $trend['label']
            );

            $alert = Alert::create([
                'device_id'         => $device->id,
                'sensor_reading_id' => $reading->id,
                'component'         => $cooldownKey,
                'severity'          => 'WARNING',
                'title'             => "TREND: {$label} — {$device->device_name}",
                'message'           => $message,
                'suggestions'       => [
                    'Pantau komponen ini secara berkala.',
                    'Cek apakah ada perubahan lingkungan atau beban kerja baru.',
                ],
                'notified_at'       => now(),
            ]);

            $this->sendEmail($alert, $device);

            Log::info(sprintf(
                '[AlertService] Consistent trend alert #%d: %s — %s',
                $alert->id,
                $metric,
                $device->device_name
            ));
        }
    }

    // ── Email ─────────────────────────────────────────────────────────────────

    /**
     * Kirim email notifikasi jika MAIL_ALERT_TO dikonfigurasi.
     */
    private function sendEmail(Alert $alert, Device $device, bool $isAnomaly = false): void
    {
        $to = config('devicecare.alert_email');
        if (!$to) return;

        try {
            Mail::send([], [], function ($mail) use ($alert, $device, $to, $isAnomaly) {
                $suggestions = collect($alert->suggestions ?? [])
                    ->map(fn ($s) => "• {$s}")
                    ->join("\n");

                $type = $isAnomaly ? 'Anomali Terdeteksi' : 'Diagnostic Alert';

                $body = "DeviceCare — {$type}\n"
                    . str_repeat('─', 40) . "\n"
                    . "Device   : {$device->device_name}\n"
                    . "Model    : {$device->manufacturer} {$device->model}\n"
                    . "Komponen : " . strtoupper($alert->component) . "\n"
                    . "Status   : {$alert->severity}\n"
                    . "Waktu    : " . now()->format('d M Y H:i:s') . "\n\n"
                    . ($alert->message ? "Detail:\n{$alert->message}\n\n" : '')
                    . ($suggestions ? "Saran Tindakan:\n{$suggestions}\n\n" : '')
                    . str_repeat('─', 40) . "\n"
                    . "DeviceCare Hardware Health Monitor";

                $mail->to($to)
                    ->subject("[{$alert->severity}] {$alert->title}")
                    ->text($body);
            });

            $alert->update(['sent_email' => true, 'email_to' => $to]);
        } catch (\Throwable $e) {
            Log::warning("[AlertService] Gagal kirim email: {$e->getMessage()}");
        }
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function isConfirmedDiagnosticStatus(Device $device, string $component, string $status): bool
    {
        $needed = self::CONFIRMATION_READINGS[$status] ?? 1;
        if ($needed <= 1) return true;

        $column = $this->statusColumn($component);
        if (!$column) return true;

        $confirmedCount = SensorReading::where('device_id', $device->id)
            ->where('recorded_at', '>=', now()->subMinutes(self::CONFIRMATION_WINDOW_MINUTES))
            ->whereIn($column, $status === 'WARNING' ? ['WARNING', 'CRITICAL'] : [$status])
            ->count();

        return $confirmedCount >= $needed;
    }

    private function statusColumn(string $component): ?string
    {
        return match ($component) {
            'cpu' => 'cpu_status',
            'gpu' => 'gpu_status',
            'ram' => 'ram_status',
            'disk' => 'disk_status',
            'battery' => 'battery_status',
            default => null,
        };
    }

    private function metricUnit(string $metric): string
    {
        return match (true) {
            str_contains($metric, 'temperature') => '°C',
            str_contains($metric, 'score')       => '/100',
            default                              => '%',
        };
    }
}
