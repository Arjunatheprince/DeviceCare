<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sensor_reading_id')->nullable()->constrained()->nullOnDelete();

            $table->string('component', 20);          // cpu|ram|disk|battery|gpu
            $table->string('severity', 10);            // WARNING|CRITICAL
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('suggestions')->nullable();

            // channels yang sudah dikirim
            $table->boolean('sent_browser')->default(false);
            $table->boolean('sent_email')->default(false);
            $table->string('email_to')->nullable();

            // status
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            $table->index(['device_id', 'component', 'severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
