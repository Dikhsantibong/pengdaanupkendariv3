<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\ReviewPlanningRequest;
use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Submit the planning documents for team leader approval.
     */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('submitPlanning', $procurement);

        $this->procurements->submitPlanning($procurement, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen perencanaan diajukan untuk persetujuan.',
        ]);

        return back();
    }

    /**
     * Approve or reject the planning documents.
     */
    public function update(ReviewPlanningRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('reviewPlanning', $procurement);

        $approved = $request->boolean('approved');

        $this->procurements->reviewPlanning(
            $procurement,
            $request->user(),
            $approved,
            $request->string('note')->trim()->value() ?: null,
        );

        Inertia::flash('toast', [
            'type' => $approved ? 'success' : 'warning',
            'message' => $approved
                ? 'Dokumen perencanaan disetujui.'
                : 'Dokumen perencanaan dikembalikan ke PIC Perencana untuk revisi.',
        ]);

        return back();
    }

    /**
     * Withdraw a rejection so the submission can be decided again.
     */
    public function destroy(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('revertPlanningRejection', $procurement);

        $this->procurements->revertPlanningRejection(
            $procurement,
            $request->user(),
            $request->string('reason')->trim()->value() ?: null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Penolakan dibatalkan. Perencanaan kembali menunggu persetujuan.',
        ]);

        return back();
    }
}
