<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // identifikasi unik per mesin
            $table->string('device_key')->unique()->comment('hostname + MAC address hash');
            $table->string('device_name');

            // hardware info
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('cpu')->nullable();
            $table->string('gpu')->nullable();
            $table->decimal('ram_total_gb', 5, 1)->nullable();
            $table->decimal('disk_total_gb', 6, 1)->nullable();

            // software
            $table->string('operating_system')->nullable();
            $table->string('os_version')->nullable();
            $table->string('agent_version')->default('1.0.0');

            // status
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
