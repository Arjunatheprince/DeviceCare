<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Dashboard web routes
Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/devices/{device}', [DashboardController::class, 'show'])->name('dashboard.show');

Route::get('/devices/{device}/diagnosis', [DashboardController::class, 'diagnosis'])->name('dashboard.diagnosis');
Route::get('/devices/{device}/history',   [DashboardController::class, 'history'])->name('dashboard.history');
Route::get('/devices/{device}/alerts',    [DashboardController::class, 'alerts'])->name('dashboard.alerts');
Route::get('/devices/{device}/report',    [DashboardController::class, 'report'])->name('dashboard.report');
