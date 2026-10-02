<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            // CPU
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->decimal('cpu_temperature', 5, 2)->nullable();
            $table->decimal('cpu_frequency_mhz', 8, 2)->nullable();

            // GPU
            $table->decimal('gpu_usage', 5, 2)->nullable();
            $table->decimal('gpu_temperature', 5, 2)->nullable();
            $table->decimal('gpu_memory_used_mb', 8, 2)->nullable();

            // RAM
            $table->decimal('ram_usage', 5, 2)->nullable();
            $table->decimal('ram_used_gb', 6, 2)->nullable();
            $table->decimal('ram_available_gb', 6, 2)->nullable();

            // Disk
            $table->decimal('disk_usage', 5, 2)->nullable();
            $table->decimal('disk_free_gb', 8, 2)->nullable();
            $table->decimal('disk_read_mbps', 8, 2)->nullable();
            $table->decimal('disk_write_mbps', 8, 2)->nullable();

            // Battery
            $table->decimal('battery_percentage', 5, 2)->nullable();
            $table->boolean('battery_charging')->nullable();
            $table->decimal('battery_time_remaining_min', 8, 2)->nullable();

            // Network
            $table->decimal('network_upload_kbps', 10, 2)->nullable();
            $table->decimal('network_download_kbps', 10, 2)->nullable();

            // Diagnosis
            $table->tinyInteger('health_score')->nullable()->comment('0-100');
            $table->string('cpu_status', 10)->nullable();
            $table->string('gpu_status', 10)->nullable();
            $table->string('ram_status', 10)->nullable();
            $table->string('disk_status', 10)->nullable();
            $table->string('battery_status', 10)->nullable();

            // Timestamp dari agent (bukan created_at server)
            $table->timestamp('recorded_at');

            $table->index(['device_id', 'recorded_at']);

            // created_at saja (tidak butuh updated_at untuk time-series)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
