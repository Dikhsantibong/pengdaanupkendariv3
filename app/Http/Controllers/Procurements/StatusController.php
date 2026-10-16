<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\UpdateStatusRequest;
use App\Models\Procurement;
use App\Services\ProcurementService;
use App\Services\ProgressStatusResolver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The progress status is set by the system; the only status chosen by hand
 * is Batal, and it can be lifted again.
 */
class StatusController extends Controller
{
    public function __construct(
        protected ProcurementService $procurements,
        protected ProgressStatusResolver $statuses,
    ) {}

    /**
     * Cancel the procurement, or lift its cancellation.
     */
    public function update(UpdateStatusRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('updateStatus', $procurement);

        $cancel = $request->boolean('cancelled');
        $note = $request->string('note')->trim()->value() ?: null;

        abort_unless($this->statuses->isAvailable(), 409, 'Status otomatis belum disiapkan di Data Master Status Progres.');

        if ($cancel === $this->statuses->isCancelled($procurement)) {
            return back();
        }

        $previous = $procurement->progressStatus->name;

        $cancel ? $this->statuses->cancel($procurement) : $this->statuses->restore($procurement);

        $current = $procurement->load('progressStatus')->progressStatus->name;

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::StatusDiubah,
            ($cancel ? 'Pengadaan dibatalkan.' : "Pembatalan dicabut, status kembali menjadi {$current}.")
                .($note !== null ? " Catatan: {$note}" : ''),
            ['from' => $previous, 'to' => $current, 'note' => $note],
        );

        Inertia::flash('toast', [
            'type' => $cancel ? 'warning' : 'success',
            'message' => $cancel ? 'Pengadaan dibatalkan.' : "Pembatalan dicabut. Status: {$current}.",
        ]);

        return back();
    }
}
