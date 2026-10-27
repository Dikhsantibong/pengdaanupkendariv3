<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\PaymentApprovalState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\ReviewPlanningRequest;
use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A Keuangan staff member submits the payment; their TL (TL Keuangan) and
 * then the Asman of the division (Asman Business Support) approve it. The
 * Asman's approval records the payment and closes the job.
 */
class PaymentApprovalController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Submit the payment papers for approval.
     */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('submitPayment', $procurement);

        $this->procurements->submitPayment($procurement, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $procurement->payment_approval_state === PaymentApprovalState::MenungguTl
                ? 'Pembayaran diajukan ke TL Keuangan untuk persetujuan.'
                : 'Pembayaran diajukan ke Asman untuk persetujuan.',
        ]);

        return back();
    }

    /**
     * Approve the payment at the step it waits on, or return it.
     */
    public function update(ReviewPlanningRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('decidePayment', $procurement);

        $approved = $request->boolean('approved');

        $this->procurements->decidePayment(
            $procurement,
            $request->user(),
            $approved,
            $request->string('note')->trim()->value() ?: null,
        );

        Inertia::flash('toast', [
            'type' => $approved ? 'success' : 'warning',
            'message' => match (true) {
                ! $approved => 'Pembayaran dikembalikan ke Keuangan untuk diperbaiki.',
                $procurement->isPaid() => 'Pembayaran disetujui: lunas, pengadaan ditutup.',
                default => 'Pembayaran disetujui dan diteruskan ke Asman.',
            },
        ]);

        return back();
    }
}
