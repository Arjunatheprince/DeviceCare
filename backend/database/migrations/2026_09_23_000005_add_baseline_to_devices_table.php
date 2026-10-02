<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom baseline ke tabel devices.
 *
 * Baseline menyimpan statistik "kondisi normal" per device:
 *   - mean (rata-rata) dan stddev (standar deviasi) dari tiap metrik sensor
 *   - dihitung dari data N hari terakhir oleh BaselineService
 *   - di-refresh secara berkala (misal setiap 24 jam)
 *
 * Disimpan sebagai JSON agar fleksibel tanpa perlu menambah kolom baru
 * setiap kali ada metrik baru.
 *
 * Format JSON baseline_data:
 * {
 *   "cpu_temperature":    {"mean": 52.3, "stddev": 4.1, "sample_count": 2880},
 *   "gpu_temperature":    {"mean": 48.7, "stddev": 5.2, "sample_count": 2850},
 *   "cpu_usage":          {"mean": 23.4, "stddev": 12.6, "sample_count": 2880},
 *   "ram_usage":          {"mean": 61.2, "stddev": 8.3, "sample_count": 2880},
 *   "disk_usage":         {"mean": 46.0, "stddev": 0.5, "sample_count": 2880},
 *   "battery_percentage": {"mean": 72.1, "stddev": 18.4, "sample_count": 2800},
 *   "health_score":       {"mean": 82.0, "stddev": 6.2, "sample_count": 2880}
 * }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Data baseline statistik (mean + stddev per metrik)
            $table->json('baseline_data')->nullable()->after('agent_version')
                ->comment('Statistik baseline per metrik: {metric: {mean, stddev, sample_count}}');

            // Berapa hari data digunakan untuk menghitung baseline ini
            $table->unsignedTinyInteger('baseline_days')->default(14)->after('baseline_data')
                ->comment('Jumlah hari data yang digunakan untuk menghitung baseline');

            // Kapan terakhir baseline di-refresh
            $table->timestamp('baseline_calculated_at')->nullable()->after('baseline_days')
                ->comment('Waktu terakhir baseline dihitung ulang');

            // Apakah baseline sudah cukup matang (minimal 3 hari data)
            $table->boolean('baseline_ready')->default(false)->after('baseline_calculated_at')
                ->comment('True jika baseline sudah punya cukup data untuk deteksi anomali');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'baseline_data',
                'baseline_days',
                'baseline_calculated_at',
                'baseline_ready',
            ]);
        });
    }
};
