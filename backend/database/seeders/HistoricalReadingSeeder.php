<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DiagnosticResult;
use App\Models\SensorReading;
use App\Services\BaselineService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * HistoricalReadingSeeder
 *
 * Generate data historis 14 hari dengan pola realistis:
 *   - Suhu CPU naik siang (10.00–22.00), turun malam
 *   - RAM usage tinggi siang, lebih rendah malam
 *   - Battery: discharge siang hari, charge malam
 *   - CPU usage fluktuatif (lonjakan sesekali)
 *   - Tren suhu CPU naik perlahan selama 14 hari (simulasi debu)
 *   - Interval per reading: 5 menit
 */
class HistoricalReadingSeeder extends Seeder
{
    // ── Profil device (sesuaikan dengan device nyata) ──────────────────────
    private const RAM_TOTAL_GB  = 7.7;
    private const DISK_TOTAL_GB = 442.2;
    private const DAYS          = 14;
    private const INTERVAL_MIN  = 5;   // satu reading tiap 5 menit

    public function run(): void
    {
        $device = Device::first();
        if (!$device) {
            $this->command->error('Tidak ada device di database. Jalankan agent dulu.');
            return;
        }

        $this->command->info("Seeder untuk device: {$device->device_name} (ID: {$device->id})");

        // Hapus reading lama kecuali 2 yang sudah ada (biarkan reading asli)
        $keepIds = SensorReading::where('device_id', $device->id)
            ->latest('recorded_at')->limit(2)->pluck('id');
        SensorReading::where('device_id', $device->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
        DiagnosticResult::where('device_id', $device->id)
            ->whereNotIn('sensor_reading_id', $keepIds)
            ->delete();

        $now        = Carbon::now();
        $start      = $now->copy()->subDays(self::DAYS)->startOfDay();
        $totalMins  = self::DAYS * 24 * 60;
        $steps      = intdiv($totalMins, self::INTERVAL_MIN);

        $this->command->info("Generate {$steps} readings selama ".self::DAYS." hari...");
        $bar = $this->command->getOutput()->createProgressBar($steps);
        $bar->start();

        $readings = [];
        $batch    = 500;

        // State untuk simulasi baterai yang realistis
        $battery     = 85.0;
        $isCharging  = false;
        $diskUsage   = 91.0;   // disk relatif statis

        for ($i = 0; $i < $steps; $i++) {
            $ts   = $start->copy()->addMinutes($i * self::INTERVAL_MIN);
            $hour = $ts->hour + ($ts->minute / 60);
            $day  = $i / ($steps / self::DAYS); // 0.0 – 14.0

            // ── CPU temperature: naik siang + tren naik +0.3°C/hari (simulasi debu) ──
            $baseCpuTemp  = 47.0;
            $dailyPeak    = $this->dailyCurve($hour, peak: 12, width: 7, amplitude: 14);
            $trendDrift   = $day * 0.3;   // perlahan naik selama 14 hari
            $noise        = $this->noise(2.5);
            $cpuTemp      = round($baseCpuTemp + $dailyPeak + $trendDrift + $noise, 1);

            // ── CPU usage: fluktuatif, spike sesekali ──
            $baseCpuUse   = 18.0;
            $cpuPattern   = $this->dailyCurve($hour, peak: 14, width: 5, amplitude: 20);
            $cpuSpike     = $this->randomSpike(prob: 0.04, height: 45);
            $cpuUsage     = round(max(2, min(98, $baseCpuUse + $cpuPattern + $cpuSpike + $this->noise(6))), 1);

            // ── GPU temperature: lebih rendah dari CPU, stabil ──
            $gpuTemp      = round(48 + $this->dailyCurve($hour, peak: 15, width: 6, amplitude: 10) + $this->noise(3), 1);
            $gpuUsage     = round(max(0, min(80, 5 + $this->randomSpike(prob: 0.06, height: 40) + $this->noise(5))), 1);

            // ── RAM: tinggi, fluktuatif siang ──
            $baseRam      = 88.0;
            $ramPattern   = $this->dailyCurve($hour, peak: 13, width: 6, amplitude: 8);
            $ramUsage     = round(max(60, min(99, $baseRam + $ramPattern + $this->noise(4))), 1);
            $ramUsedGb    = round(self::RAM_TOTAL_GB * $ramUsage / 100, 2);
            $ramAvailGb   = round(self::RAM_TOTAL_GB - $ramUsedGb, 2);

            // ── Battery: discharge siang, charge malam ──
            [$battery, $isCharging] = $this->simulateBattery($battery, $isCharging, $hour);
            $battPct     = round($battery, 1);
            $timeRem     = $isCharging ? null : round(($battPct / 100) * 180, 0);

            // ── Disk: stabil dengan kenaikan sangat kecil ──
            $diskUsage   = round(min(95, $diskUsage + ($this->noise(0.02))), 1);
            $diskFreeGb  = round(self::DISK_TOTAL_GB * (100 - $diskUsage) / 100, 1);

            // ── Network: aktif siang ──
            $netBase     = $this->dailyCurve($hour, peak: 13, width: 5, amplitude: 40);
            $netDl       = round(max(0.5, 5 + $netBase + $this->noise(15)), 2);
            $netUl       = round(max(0.1, 2 + $netBase * 0.3 + $this->noise(4)), 2);

            // ── Disk I/O ──
            $diskRead    = round(max(0, 10 + $this->randomSpike(prob: 0.05, height: 200) + $this->noise(20)), 2);
            $diskWrite   = round(max(0, 2 + $this->randomSpike(prob: 0.05, height: 80) + $this->noise(8)), 2);

            // ── Status diagnosis ──
            $cpuStatus   = $this->diagnoseTemp($cpuTemp, $cpuUsage);
            $gpuStatus   = $cpuTemp > 80 ? 'WARNING' : 'NORMAL';
            $ramStatus   = $ramUsage >= 90 ? 'CRITICAL' : ($ramUsage >= 75 ? 'WARNING' : 'NORMAL');
            $diskStatus  = $diskUsage >= 95 ? 'CRITICAL' : ($diskUsage >= 85 ? 'WARNING' : 'NORMAL');
            $battStatus  = (!$isCharging && $battPct <= 10) ? 'CRITICAL' : ((!$isCharging && $battPct <= 20) ? 'WARNING' : 'NORMAL');

            // ── Health score ──
            $scores = [
                $this->statusScore($cpuStatus)  * 0.35,
                $this->statusScore($ramStatus)  * 0.20,
                $this->statusScore($diskStatus) * 0.15,
                $this->statusScore($battStatus) * 0.15,
                $this->statusScore($gpuStatus)  * 0.15,
            ];
            $healthScore = (int) round(array_sum($scores));

            $readings[] = [
                'device_id'                  => $device->id,
                'cpu_usage'                  => $cpuUsage,
                'cpu_temperature'            => $cpuTemp,
                'cpu_frequency_mhz'          => round(2400 + $cpuUsage * 8 + $this->noise(100), 0),
                'gpu_usage'                  => $gpuUsage,
                'gpu_temperature'            => $gpuTemp,
                'gpu_memory_used_mb'         => null,
                'ram_usage'                  => $ramUsage,
                'ram_used_gb'                => $ramUsedGb,
                'ram_available_gb'           => $ramAvailGb,
                'disk_usage'                 => $diskUsage,
                'disk_free_gb'               => $diskFreeGb,
                'disk_read_mbps'             => $diskRead,
                'disk_write_mbps'            => $diskWrite,
                'battery_percentage'         => $battPct,
                'battery_charging'           => $isCharging,
                'battery_time_remaining_min' => $timeRem,
                'network_upload_kbps'        => $netUl,
                'network_download_kbps'      => $netDl,
                'health_score'               => $healthScore,
                'cpu_status'                 => $cpuStatus,
                'gpu_status'                 => $gpuStatus,
                'ram_status'                 => $ramStatus,
                'disk_status'                => $diskStatus,
                'battery_status'             => $battStatus,
                'recorded_at'                => $ts->toDateTimeString(),
                'created_at'                 => $ts->toDateTimeString(),
            ];

            if (count($readings) >= $batch) {
                DB::table('sensor_readings')->insert($readings);
                $readings = [];
            }
            $bar->advance();
        }

        if (!empty($readings)) {
            DB::table('sensor_readings')->insert($readings);
        }

        $bar->finish();
        $this->command->newLine();

        $total = SensorReading::where('device_id', $device->id)->count();
        $this->command->info("✓ {$total} readings tersimpan.");

        // Hitung baseline setelah data tersedia
        $this->command->info("Menghitung baseline...");
        (new BaselineService())->calculate($device, 14);
        $device->refresh();
        $this->command->info("✓ Baseline selesai. Ready: " . ($device->baseline_ready ? 'Ya' : 'Belum'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Gaussian-like daily curve: puncak di `peak` jam, turun di kedua sisi */
    private function dailyCurve(float $hour, float $peak, float $width, float $amplitude): float
    {
        return $amplitude * exp(-0.5 * pow(($hour - $peak) / $width, 2));
    }

    /** Random Gaussian noise */
    private function noise(float $std): float
    {
        // Box-Muller transform
        $u1 = max(1e-10, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        return $std * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    /** Random spike dengan probabilitas tertentu */
    private function randomSpike(float $prob, float $height): float
    {
        return (mt_rand() / mt_getrandmax() < $prob) ? $height * (0.5 + mt_rand() / mt_getrandmax() * 0.5) : 0;
    }

    /** Simulasi baterai: discharge siang, charge saat rendah/malam */
    private function simulateBattery(float $pct, bool $charging, float $hour): array
    {
        if ($charging) {
            $pct += 0.8;  // charge ~48% per jam
            if ($pct >= 98) { $pct = 98; $charging = false; }
        } else {
            $dischargeRate = ($hour >= 8 && $hour <= 22) ? 0.35 : 0.1;  // lebih cepat siang
            $pct -= $dischargeRate;
            if ($pct <= 20 || ($hour >= 22 || $hour <= 6)) {
                $charging = true;  // mulai charge saat rendah atau malam
            }
        }
        return [max(5, min(100, $pct)), $charging];
    }

    /** Diagnosis CPU berdasarkan suhu + usage */
    private function diagnoseTemp(float $temp, float $usage): string
    {
        if ($temp >= 95) return 'CRITICAL';
        if ($temp >= 85 && $usage < 40) return 'WARNING';
        if ($temp >= 85) return 'WARNING';
        if ($temp >= 75 && $usage < 20) return 'WARNING';
        return 'NORMAL';
    }

    private function statusScore(string $status): int
    {
        return match ($status) { 'NORMAL' => 100, 'WARNING' => 60, 'CRITICAL' => 20, default => 50 };
    }
}
