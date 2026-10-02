<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom SMART disk health ke sensor_readings.
 *
 * SMART = Self-Monitoring, Analysis and Reporting Technology.
 * Data ini tidak tersedia di Task Manager — hanya aplikasi monitoring khusus.
 * Memungkinkan deteksi kerusakan disk SEBELUM kegagalan total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->string('smart_health_status', 20)->nullable()->after('disk_write_mbps')
                ->comment('Healthy|Warning|Failed|Unknown');
            $table->decimal('smart_temperature', 5, 1)->nullable()->after('smart_health_status')
                ->comment('Suhu disk dari SMART (°C)');
            $table->unsignedSmallInteger('smart_reallocated_sectors')->nullable()->after('smart_temperature')
                ->comment('Jumlah sektor rusak yang di-realokasi (>0 = tanda kerusakan)');
            $table->unsignedSmallInteger('smart_pending_sectors')->nullable()->after('smart_reallocated_sectors')
                ->comment('Sektor menunggu realokasi');
            $table->unsignedSmallInteger('smart_uncorrectable_sectors')->nullable()->after('smart_pending_sectors')
                ->comment('Sektor tidak bisa diperbaiki');
            $table->unsignedInteger('smart_power_on_hours')->nullable()->after('smart_uncorrectable_sectors')
                ->comment('Total jam disk menyala');
            $table->unsignedSmallInteger('smart_power_cycle_count')->nullable()->after('smart_power_on_hours')
                ->comment('Jumlah siklus power disk');
            $table->boolean('smart_is_ssd')->nullable()->after('smart_power_cycle_count')
                ->comment('True jika SSD, False jika HDD');
            $table->string('smart_status', 10)->nullable()->after('smart_is_ssd')
                ->comment('NORMAL|WARNING|CRITICAL');
        });
    }

    public function down(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->dropColumn([
                'smart_health_status', 'smart_temperature',
                'smart_reallocated_sectors', 'smart_pending_sectors',
                'smart_uncorrectable_sectors', 'smart_power_on_hours',
                'smart_power_cycle_count', 'smart_is_ssd', 'smart_status',
            ]);
        });
    }
};
