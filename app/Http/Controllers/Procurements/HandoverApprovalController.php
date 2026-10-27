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
 * The execution PIC submits the hand-over papers; TL Pengadaan approves
 * them on to Pembayaran (Keuangan), or returns them.
 */
class HandoverApprovalController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Submit the hand-over papers for TL Pengadaan's approval.
     */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('submitHandover', $procurement);

        $this->procurements->submitHandover($procurement, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Penyerahan diajukan ke TL Pengadaan untuk persetujuan.',
        ]);

        return back();
    }

    /**
     * Approve or return the hand-over.
     */
    public function update(ReviewPlanningRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('reviewHandover', $procurement);

        $approved = $request->boolean('approved');

        $this->procurements->reviewHandover(
            $procurement,
            $request->user(),
            $approved,
            $request->string('note')->trim()->value() ?: null,
        );

        Inertia::flash('toast', [
            'type' => $approved ? 'success' : 'warning',
            'message' => $approved
                ? 'Penyerahan disetujui dan diteruskan ke Pembayaran (Keuangan).'
                : 'Penyerahan dikembalikan ke PIC Pelaksana untuk diperbaiki.',
        ]);

        return back();
    }
}
