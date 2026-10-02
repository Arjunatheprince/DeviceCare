<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\ProcessController;
use App\Http\Controllers\Api\RemoteCommandController;
use App\Http\Controllers\Api\SensorReadingController;
use App\Http\Controllers\Api\TrendController;
use Illuminate\Support\Facades\Route;

Route::prefix('devices')->group(function () {
    Route::post('/register', [DeviceController::class, 'register']);
    Route::get('/', [DeviceController::class, 'index']);
    Route::get('/{device}', [DeviceController::class, 'show']);

    // Sensor readings
    Route::prefix('/{device}/readings')->group(function () {
        Route::post('/',         [SensorReadingController::class, 'store']);
        Route::get('/latest',    [SensorReadingController::class, 'latest']);
        Route::get('/history',   [SensorReadingController::class, 'history']);
    });

    // History & trend analysis (HistoryController — aggregated chart data)
    Route::prefix('/{device}/history')->group(function () {
        Route::get('/',      [HistoryController::class, 'index']);
        Route::get('/multi', [HistoryController::class, 'multi']);
    });

    // ── Milestone 4: Intelligent Monitoring ──────────────────────────────────
    Route::get('/{device}/trends',            [TrendController::class, 'trends']);
    Route::get('/{device}/trends/consistent', [TrendController::class, 'consistent']);
    Route::get('/{device}/anomalies',         [TrendController::class, 'anomalies']);
    Route::get('/{device}/baseline',          [TrendController::class, 'baseline']);
    Route::post('/{device}/baseline/refresh', [TrendController::class, 'refreshBaseline']);
    Route::get('/{device}/storage-analysis',  [TrendController::class, 'storageAnalysis']);
    Route::get('/{device}/maintenance-health',[TrendController::class, 'maintenanceHealth']);
    Route::get('/{device}/battery-intelligence', [TrendController::class, 'batteryIntelligence']);
    Route::get('/{device}/health-score',      [TrendController::class, 'healthScore']);
    Route::get('/{device}/root-cause',        [TrendController::class, 'rootCause']);
    Route::get('/{device}/predictions',       [TrendController::class, 'predictions']);
    Route::get('/{device}/process-insights',  [TrendController::class, 'processInsights']);
    Route::get('/{device}/intelligence',      [TrendController::class, 'intelligence']);

    // ── Milestone 5: AI Assistant ─────────────────────────────────────────────
    // Streaming SSE — token dikirim satu per satu (untuk chat UI)
    Route::post('/{device}/chat',         [ChatController::class, 'stream']);
    // Non-streaming fallback — return JSON (untuk testing / fallback)
    Route::post('/{device}/chat/simple',  [ChatController::class, 'simple']);
    // Konteks yang dimiliki AI untuk device ini (untuk UI badge)
    Route::get('/{device}/chat/context',  [ChatController::class, 'context']);
    // Starter questions kontekstual berdasarkan kondisi device
    Route::get('/{device}/chat/starters', [ChatController::class, 'starters']);
    // ─────────────────────────────────────────────────────────────────────────

    // ── Top Processes (Task Manager insight) ─────────────────────────────────
    Route::post('/{device}/processes',    [ProcessController::class, 'store']);
    Route::get('/{device}/processes',     [ProcessController::class, 'latest']);

    // Remote safe actions
    Route::get('/{device}/commands',          [RemoteCommandController::class, 'index']);
    Route::post('/{device}/commands',         [RemoteCommandController::class, 'store']);
    Route::get('/{device}/commands/pending',  [RemoteCommandController::class, 'pending']);
    Route::post('/{device}/commands/{command}/complete', [RemoteCommandController::class, 'complete']);

    // Alerts
    Route::prefix('/{device}/alerts')->group(function () {
        Route::get('/',             [AlertController::class, 'index']);
        Route::get('/unread-count', [AlertController::class, 'unreadCount']);
        Route::post('/resolve-all', [AlertController::class, 'resolveAll']);
        // Resolve single alert — dalam konteks device agar validasi kepemilikan bisa dilakukan
        Route::post('/{alert}/resolve', [AlertController::class, 'resolve']);
    });
});

// Route lama dipertahankan untuk backward compatibility (alerts.blade.php pakai ini)
Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve']);
