<?php

use App\Http\Controllers\ApprovalQueueController;
use App\Http\Controllers\DocumentArchiveController;
use App\Http\Controllers\ExecutionController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\Procurements\AmendmentController;
use App\Http\Controllers\Procurements\ApprovalController;
use App\Http\Controllers\Procurements\ChecklistController;
use App\Http\Controllers\Procurements\ChecklistFileController;
use App\Http\Controllers\Procurements\CompletionController;
use App\Http\Controllers\Procurements\DeliveryOrderController;
use App\Http\Controllers\Procurements\DocumentArchiveDownloadController;
use App\Http\Controllers\Procurements\DocumentController;
use App\Http\Controllers\Procurements\ExecutionApprovalController;
use App\Http\Controllers\Procurements\FinanceController;
use App\Http\Controllers\Procurements\PartnerCandidateController;
use App\Http\Controllers\Procurements\PicAssignmentController;
use App\Http\Controllers\Procurements\PlanningIdentityController;
use App\Http\Controllers\Procurements\ProposalController;
use App\Http\Controllers\Procurements\StatusController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('procurements', ProcurementController::class);

    Route::prefix('procurements/{procurement}')->name('procurements.')->group(function () {
        Route::put('pic', [PicAssignmentController::class, 'update'])->name('pic.update');
        Route::put('status', [StatusController::class, 'update'])->name('status.update');
        Route::put('planning-identity', [PlanningIdentityController::class, 'update'])->name('planning-identity.update');
        // Partner candidates listed in planning; one is picked in execution.
        Route::post('calon-mitra', [PartnerCandidateController::class, 'store'])->name('partner-candidates.store');
        Route::put('calon-mitra/{candidate}', [PartnerCandidateController::class, 'update'])->name('partner-candidates.update');
        Route::delete('calon-mitra/{candidate}', [PartnerCandidateController::class, 'destroy'])->name('partner-candidates.destroy');
        Route::get('calon-mitra/{candidate}/penawaran', [PartnerCandidateController::class, 'quotation'])->name('partner-candidates.quotation');
        Route::put('checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
        Route::put('checklists/{checklist}/input', [ChecklistController::class, 'updateInput'])->name('checklists.input');
        Route::post('checklists/{checklist}/berkas', [ChecklistFileController::class, 'store'])->name('checklists.files.store');
        Route::get('checklists/{checklist}/berkas/{file}', [ChecklistFileController::class, 'show'])->name('checklists.files.show');
        Route::delete('checklists/{checklist}/berkas/{file}', [ChecklistFileController::class, 'destroy'])->name('checklists.files.destroy');
        Route::post('approval', [ApprovalController::class, 'store'])->name('approval.store');
        Route::put('approval', [ApprovalController::class, 'update'])->name('approval.update');
        Route::delete('approval', [ApprovalController::class, 'destroy'])->name('approval.destroy');
        Route::post('completion', [CompletionController::class, 'store'])->name('completion.store');
        // The executor submits the execution; TL Pengadaan's approval hands
        // it to Keuangan, who records the payment.
        Route::post('execution-approval', [ExecutionApprovalController::class, 'store'])->name('execution-approval.store');
        Route::put('execution-approval', [ExecutionApprovalController::class, 'update'])->name('execution-approval.update');
        Route::post('keuangan/lunas', [FinanceController::class, 'complete'])->name('finance.complete');
        // Contract amendments, on the Amandemen tab.
        Route::post('amandemen', [AmendmentController::class, 'store'])->name('amendments.store');
        Route::put('amandemen/{amendment}', [AmendmentController::class, 'update'])->name('amendments.update');
        Route::post('amandemen/{amendment}/berkas', [AmendmentController::class, 'upload'])->name('amendments.upload');
        Route::get('amandemen/{amendment}/berkas/{file}', [AmendmentController::class, 'file'])->name('amendments.file');
        Route::post('amandemen/{amendment}/ajukan', [AmendmentController::class, 'submit'])->name('amendments.submit');
        Route::post('amandemen/{amendment}/keputusan', [AmendmentController::class, 'decide'])->name('amendments.decide');
        Route::put('amandemen/{amendment}/penerbitan', [AmendmentController::class, 'saveIssue'])->name('amendments.issue-data');
        Route::post('amandemen/{amendment}/terbitkan', [AmendmentController::class, 'issue'])->name('amendments.issue');
        // Delivery Orders of a KHS contract, on the DO tab.
        Route::put('do/daftar-harga', [DeliveryOrderController::class, 'saveContractItems'])->name('delivery-orders.contract-items');
        Route::post('do', [DeliveryOrderController::class, 'store'])->name('delivery-orders.store');
        Route::put('do/{order}/permohonan', [DeliveryOrderController::class, 'saveRequest'])->name('delivery-orders.request');
        Route::post('do/{order}/nodis-usulan', [DeliveryOrderController::class, 'uploadRequest'])->name('delivery-orders.upload-request');
        Route::get('do/{order}/nodis-usulan', [DeliveryOrderController::class, 'requestFile'])->name('delivery-orders.request-file');
        Route::post('do/{order}/ajukan', [DeliveryOrderController::class, 'submit'])->name('delivery-orders.submit');
        Route::post('do/{order}/keputusan', [DeliveryOrderController::class, 'decide'])->name('delivery-orders.decide');
        Route::put('do/{order}/nodis-do', [DeliveryOrderController::class, 'saveMemo'])->name('delivery-orders.memo');
        Route::post('do/{order}/teruskan', [DeliveryOrderController::class, 'forward'])->name('delivery-orders.forward');
        Route::put('do/{order}/penerbitan', [DeliveryOrderController::class, 'saveIssue'])->name('delivery-orders.issue-data');
        Route::post('do/{order}/terbitkan', [DeliveryOrderController::class, 'issue'])->name('delivery-orders.issue');
        // Every file of the procurement at once, as a ZIP.
        Route::get('arsip-zip', DocumentArchiveDownloadController::class)->name('documents.archive');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::post('documents/upload', [DocumentController::class, 'storeUpload'])->name('documents.upload');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
        Route::post('documents/{document}/preview', [DocumentController::class, 'preview'])
            ->name('documents.preview');
        Route::post('documents/{document}/pages', [DocumentController::class, 'pages'])
            ->name('documents.pages');
        Route::post('documents/{document}/regenerate', [DocumentController::class, 'regenerate'])
            ->name('documents.regenerate');
        Route::post('documents/{document}/signed', [DocumentController::class, 'storeSigned'])
            ->name('documents.signed.store');
        Route::delete('documents/{document}/signed', [DocumentController::class, 'destroyAllSigned'])
            ->name('documents.signed.destroy-all');
        Route::get('documents/{document}/signed/{upload}', [DocumentController::class, 'showSigned'])
            ->name('documents.signed.show');
        Route::delete('documents/{document}/signed/{upload}', [DocumentController::class, 'destroySigned'])
            ->name('documents.signed.destroy');
    });

    Route::get('penunjukan-pic', [PicAssignmentController::class, 'index'])->name('pic-assignments.index');

    // Work proposals: raised by a division, approved by the proposer's TL
    // and then the division's Direksi Pekerjaan (Asman).
    Route::prefix('usulan')->name('proposals.')->group(function () {
        Route::get('/', [ProposalController::class, 'index'])->middleware('can:menu.proposals')->name('index');
        Route::get('{procurement}', [ProposalController::class, 'show'])->name('show');
        Route::post('{procurement}/keputusan', [ProposalController::class, 'decide'])->name('decide');
        Route::post('{procurement}/ajukan-ulang', [ProposalController::class, 'resubmit'])->name('resubmit');
        Route::post('{procurement}/lampiran', [ProposalController::class, 'attach'])->name('attachments.store');
        Route::get('{procurement}/lampiran/{attachment}', [ProposalController::class, 'attachment'])->name('attachments.show');
        Route::delete('{procurement}/lampiran/{attachment}', [ProposalController::class, 'detach'])->name('attachments.destroy');
    });
    // Each menu is a right set on the "Hak Akses" screen.
    Route::get('planning', [PlanningController::class, 'index'])
        ->middleware('can:menu.planning')->name('planning.index');
    Route::get('execution', [ExecutionController::class, 'index'])
        ->middleware('can:menu.execution')->name('execution.index');
    Route::get('keuangan', [FinanceController::class, 'index'])
        ->middleware('can:menu.finance')->name('finance.index');
    Route::get('approvals', [ApprovalQueueController::class, 'index'])
        ->middleware('can:menu.approvals')->name('approvals.index');
    Route::get('monitoring', [MonitoringController::class, 'index'])
        ->middleware('can:menu.monitoring')->name('monitoring.index');
    // Amendments and Delivery Orders across every contract, on the same right.
    Route::get('monitoring/amandemen', [MonitoringController::class, 'amendments'])
        ->middleware('can:menu.monitoring')->name('monitoring.amendments');
    Route::get('monitoring/delivery-order', [MonitoringController::class, 'deliveryOrders'])
        ->middleware('can:menu.monitoring')->name('monitoring.delivery-orders');
    Route::get('documents', [DocumentArchiveController::class, 'index'])
        ->middleware('can:menu.documents')->name('documents.index');

    Route::middleware('can:menu.reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications', [NotificationController::class, 'update'])->name('notifications.update');
});
