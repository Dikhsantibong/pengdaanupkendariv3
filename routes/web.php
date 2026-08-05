<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicMonitoringController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public boards: throttled because they are unauthenticated and polled by the client.
Route::middleware('throttle:60,1')->prefix('monitoring-publik')->name('public-monitoring.')->group(function () {
    Route::redirect('/', '/monitoring-publik/perencanaan')->name('index');

    Route::get('perencanaan', [PublicMonitoringController::class, 'planning'])->name('planning');
    Route::get('pelaksanaan', [PublicMonitoringController::class, 'execution'])->name('execution');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/procurement.php';
require __DIR__.'/master-data.php';
require __DIR__.'/settings.php';
