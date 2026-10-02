<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('command', 50);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('queued');
            $table->json('result')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_commands');
    }
};