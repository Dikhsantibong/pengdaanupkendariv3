<?php

use App\Http\Controllers\ApprovalQueueController;
use App\Http\Controllers\DocumentArchiveController;
use App\Http\Controllers\ExecutionController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\Procurements\ApprovalController;
use App\Http\Controllers\Procurements\ChecklistController;
use App\Http\Controllers\Procurements\CompletionController;
use App\Http\Controllers\Procurements\DocumentController;
use App\Http\Controllers\Procurements\PicAssignmentController;
use App\Http\Controllers\Procurements\StatusController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('procurements', ProcurementController::class);

    Route::prefix('procurements/{procurement}')->name('procurements.')->group(function () {
        Route::put('pic', [PicAssignmentController::class, 'update'])->name('pic.update');
        Route::put('status', [StatusController::class, 'update'])->name('status.update');
        Route::put('checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
        Route::post('approval', [ApprovalController::class, 'store'])->name('approval.store');
        Route::put('approval', [ApprovalController::class, 'update'])->name('approval.update');
        Route::post('completion', [CompletionController::class, 'store'])->name('completion.store');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
        Route::post('documents/{document}/regenerate', [DocumentController::class, 'regenerate'])
            ->name('documents.regenerate');
    });

    Route::get('penunjukan-pic', [PicAssignmentController::class, 'index'])->name('pic-assignments.index');
    Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::get('execution', [ExecutionController::class, 'index'])->name('execution.index');
    Route::get('approvals', [ApprovalQueueController::class, 'index'])->name('approvals.index');
    Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('documents', [DocumentArchiveController::class, 'index'])->name('documents.index');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications', [NotificationController::class, 'update'])->name('notifications.update');
});
