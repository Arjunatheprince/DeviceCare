<?php

namespace App\Console\Commands;

use App\Services\BaselineService;
use Illuminate\Console\Command;

/**
 * Artisan command untuk refresh baseline semua device.
 *
 * Penggunaan:
 *   php artisan devicecare:refresh-baselines            # refresh semua device
 *   php artisan devicecare:refresh-baselines --device=1 # refresh device tertentu
 *   php artisan devicecare:refresh-baselines --force    # abaikan cooldown
 *
 * Jadwal (tambahkan di routes/console.php):
 *   Schedule::command('devicecare:refresh-baselines')->everySixHours();
 */
class RefreshBaselinesCommand extends Command
{
    protected $signature = 'devicecare:refresh-baselines
                            {--device= : ID device tertentu (opsional)}
                            {--force   : Refresh meskipun belum stale}';

    protected $description = 'Hitung ulang baseline statistik hardware untuk semua device aktif';

    public function handle(BaselineService $baselineService): int
    {
        $deviceId = $this->option('device');
        $force    = $this->option('force');

        if ($deviceId) {
            $device = \App\Models\Device::find($deviceId);
            if (!$device) {
                $this->error("Device #{$deviceId} tidak ditemukan.");
                return self::FAILURE;
            }

            $this->info("Menghitung baseline untuk Device #{$device->id} ({$device->device_name})...");
            $ok = $baselineService->calculate($device);
            $device->refresh();
            $summary = $baselineService->getSummary($device);

            if ($ok) {
                $this->info("✓ Baseline diperbarui.");
                $this->table(
                    ['Metrik', 'Mean', 'Stddev', 'Samples'],
                    collect($summary['metrics'])->map(fn ($v, $k) => [
                        $k,
                        $v ? $v['mean']         : 'N/A',
                        $v ? $v['stddev']        : 'N/A',
                        $v ? $v['sample_count']  : 'N/A',
                    ])->values()->toArray()
                );
                $this->line("Ready: " . ($summary['ready'] ? '<fg=green>Yes</>' : '<fg=yellow>No (belum cukup data)</>'));
            } else {
                $this->warn("Tidak ada data yang cukup untuk baseline device ini.");
            }

            return self::SUCCESS;
        }

        // Refresh semua device
        $this->info("Memulai refresh baseline semua device aktif...");

        if ($force) {
            // Force: reset baseline_calculated_at semua device agar dianggap stale
            \App\Models\Device::where('is_active', true)
                ->update(['baseline_calculated_at' => null]);
            $this->line("Mode force aktif — semua device akan di-refresh.");
        }

        $result = $baselineService->refreshAll();

        $this->info(sprintf(
            "Selesai. Diperbarui: %d device | Dilewati: %d device.",
            $result['refreshed'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
