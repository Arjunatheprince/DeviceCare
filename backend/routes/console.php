<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── DeviceCare scheduled tasks ────────────────────────────────────────────────

// Refresh baseline statistik semua device setiap 6 jam
Schedule::command('devicecare:refresh-baselines')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/baseline-refresh.log'));
