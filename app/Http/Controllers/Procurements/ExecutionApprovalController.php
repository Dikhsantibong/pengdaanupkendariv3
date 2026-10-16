<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\ReviewPlanningRequest;
use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The execution PIC submits the finished execution; TL Pengadaan approves
 * it into Penyerahan and Pembayaran, or returns it.
 */
class ExecutionApprovalController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Submit the finished execution for TL Pengadaan's approval.
     */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('submitExecution', $procurement);

        $this->procurements->submitExecution($procurement, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pelaksanaan diajukan ke TL Pengadaan untuk persetujuan.',
        ]);

        return back();
    }

    /**
     * Approve or return the execution.
     */
    public function update(ReviewPlanningRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('reviewExecution', $procurement);

        $approved = $request->boolean('approved');

        $this->procurements->reviewExecution(
            $procurement,
            $request->user(),
            $approved,
            $request->string('note')->trim()->value() ?: null,
        );

        Inertia::flash('toast', [
            'type' => $approved ? 'success' : 'warning',
            'message' => $approved
                ? 'Pelaksanaan disetujui dan diteruskan ke Penyerahan dan Pembayaran.'
                : 'Pelaksanaan dikembalikan ke PIC Pelaksana untuk diperbaiki.',
        ]);

        return back();
    }
}
