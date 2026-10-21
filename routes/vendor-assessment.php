<?php

use App\Http\Controllers\AssessmentRequestFormController;
use App\Http\Controllers\AssessmentSigningController;
use App\Http\Controllers\VendorAssessmentController;
use App\Http\Controllers\VendorAssessmentRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-vendor-assessments'])
    ->prefix('penilaian-penyedia')
    ->name('vendor-assessments.')
    ->group(function () {
        Route::get('/', [VendorAssessmentController::class, 'index'])->name('index');
        Route::get('ekspor', [VendorAssessmentController::class, 'export'])->name('export');
        Route::get('buat', [VendorAssessmentController::class, 'create'])->name('create');
        // One vendor's folder: its assessments with the PDF shown large.
        Route::get('mitra', [VendorAssessmentController::class, 'vendor'])->name('vendor');
        // Invoice details sent in by vendors through the fixed request link.
        Route::delete('permintaan/{assessmentRequest}', [VendorAssessmentRequestController::class, 'destroy'])
            ->name('requests.destroy');
        // One click: the assessment and a signing link for every sheet.
        Route::post('permintaan/{assessmentRequest}/proses', [VendorAssessmentRequestController::class, 'process'])
            ->name('requests.process');
        Route::post('/', [VendorAssessmentController::class, 'store'])->name('store');
        Route::get('{assessment}', [VendorAssessmentController::class, 'show'])->name('show');
        Route::put('{assessment}', [VendorAssessmentController::class, 'update'])->name('update');
        Route::put('{assessment}/forms/{form}', [VendorAssessmentController::class, 'updateScores'])
            ->name('scores.update');
        Route::delete('{assessment}', [VendorAssessmentController::class, 'destroy'])->name('destroy');
        Route::get('{assessment}/cetak/{form?}', [VendorAssessmentController::class, 'print'])
            ->name('print');
        Route::get('{assessment}/cetak-semua', [VendorAssessmentController::class, 'printAll'])
            ->name('print-all');
        Route::get('{assessment}/unduh-semua', [VendorAssessmentController::class, 'downloadAll'])
            ->name('download-all');
        Route::post('{assessment}/forms/{form}/tautan', [VendorAssessmentController::class, 'issueLink'])
            ->name('links.store');
        Route::delete('{assessment}/forms/{form}/tautan', [VendorAssessmentController::class, 'revokeLink'])
            ->name('links.destroy');
        Route::post('{assessment}/tautan', [VendorAssessmentController::class, 'issueAllLinks'])
            ->name('links.store-all');
    });

// Public signed URL: panitia downloads the merged PDF without logging in.
Route::middleware('signed')
    ->get('penilaian-penyedia/{assessment}/unduh-panitia', [VendorAssessmentController::class, 'downloadPanitia'])
    ->name('vendor-assessments.download-panitia');

// Public signed URL: pengadaan downloads the akumulasi PDF without logging in.
Route::middleware('signed')
    ->get('penilaian-penyedia/{assessment}/unduh-akumulasi', [VendorAssessmentController::class, 'downloadAkumulasi'])
    ->name('vendor-assessments.download-akumulasi');

// The assessor has no account: the token in the address is the whole of the
// authorisation, so the route is throttled against guessing and exposes nothing
// beyond the single sheet that token names.
Route::middleware('throttle:30,1')
    ->prefix('penilaian')
    ->name('assessment-signing.')
    ->group(function () {
        Route::get('{token}', [AssessmentSigningController::class, 'show'])->name('show');
        Route::post('{token}', [AssessmentSigningController::class, 'store'])->name('store');
    });

// The fixed link printed in the contract, where every vendor sends in the
// invoice details for their assessment. Open to anyone and write-only, so
// submissions are throttled harder than page views.
Route::prefix('permintaan-penilaian')
    ->name('assessment-requests.')
    ->group(function () {
        Route::get('/', [AssessmentRequestFormController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('show');
        Route::post('/', [AssessmentRequestFormController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
    });
