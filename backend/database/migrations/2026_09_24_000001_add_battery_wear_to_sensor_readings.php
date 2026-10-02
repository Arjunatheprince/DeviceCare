<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom battery wear level ke sensor_readings.
 *
 * wear_level_percent: (full_charge_capacity / design_capacity) * 100
 * Nilai 100% = baterai baru, 70% = sudah aus 30%, <50% = perlu ganti.
 * Insight ini tidak tersedia di Task Manager bawaan Windows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->decimal('battery_wear_level', 5, 1)->nullable()->after('battery_time_remaining_min')
                ->comment('Persentase kapasitas baterai vs pabrik (0-100%)');
            $table->unsignedSmallInteger('battery_design_capacity_mwh')->nullable()->after('battery_wear_level')
                ->comment('Kapasitas desain dari pabrik dalam mWh');
            $table->unsignedSmallInteger('battery_full_charge_capacity_mwh')->nullable()->after('battery_design_capacity_mwh')
                ->comment('Kapasitas charge penuh saat ini dalam mWh');
            $table->unsignedSmallInteger('battery_cycle_count')->nullable()->after('battery_full_charge_capacity_mwh')
                ->comment('Jumlah siklus charge baterai');
        });
    }

    public function down(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->dropColumn([
                'battery_wear_level',
                'battery_design_capacity_mwh',
                'battery_full_charge_capacity_mwh',
                'battery_cycle_count',
            ]);
        });
    }
};
