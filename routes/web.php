<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonationController;
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

    // The administrator's "Pindah Akun": every check lives in the controller
    // and App\Support\Impersonation, the switch itself is throttled.
    Route::get('pindah-akun', [ImpersonationController::class, 'index'])
        ->middleware('throttle:60,1')->name('impersonation.index');
    Route::post('pindah-akun/{user}', [ImpersonationController::class, 'store'])
        ->middleware('throttle:10,1')->name('impersonation.store');
});

// Coming back works from any account the administrator switched into.
Route::delete('pindah-akun', [ImpersonationController::class, 'destroy'])
    ->middleware(['auth', 'throttle:30,1'])->name('impersonation.destroy');

require __DIR__.'/procurement.php';
require __DIR__.'/master-data.php';
require __DIR__.'/vendor-assessment.php';
require __DIR__.'/settings.php';

// Any other address: the 404 page, through the web middleware, so it knows
// who is signed in as every other page does.
Route::fallback(fn () => abort(404));
