<?php

use App\Http\Controllers\MasterData\BudgetSourceController;
use App\Http\Controllers\MasterData\ChecklistItemController;
use App\Http\Controllers\MasterData\DocumentTemplateController;
use App\Http\Controllers\MasterData\DocumentTypeController;
use App\Http\Controllers\MasterData\ProcurementMethodController;
use App\Http\Controllers\MasterData\ProgressStatusController;
use App\Http\Controllers\MasterData\PrRoNumberController;
use App\Http\Controllers\MasterData\TargetUnitController;
use App\Http\Controllers\MasterData\WorkDirectorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-master-data'])
    ->prefix('master-data')
    ->name('master-data.')
    ->group(function () {
        Route::resource('work-directors', WorkDirectorController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('target-units', TargetUnitController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('procurement-methods', ProcurementMethodController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('budget-sources', BudgetSourceController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('progress-statuses', ProgressStatusController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('pr-ro-numbers', PrRoNumberController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('checklist-items', ChecklistItemController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('document-types', DocumentTypeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('document-templates', DocumentTemplateController::class)->only(['index', 'store', 'update', 'destroy']);
    });

Route::middleware(['auth', 'verified', 'can:manage-users'])->group(function () {
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
});
