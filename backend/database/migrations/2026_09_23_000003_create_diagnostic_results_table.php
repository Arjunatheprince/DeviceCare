<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sensor_reading_id')->nullable()->constrained()->nullOnDelete();

            $table->string('component', 20)->comment('cpu|gpu|ram|disk|battery|network|overall');
            $table->string('status', 10)->comment('NORMAL|WARNING|CRITICAL');
            $table->integer('score')->nullable()->comment('0-100');
            $table->text('message')->nullable();
            $table->json('suggestions')->nullable();

            $table->timestamp('diagnosed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['device_id', 'component', 'diagnosed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_results');
    }
};
