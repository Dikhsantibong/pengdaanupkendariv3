<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\UpdateStatusRequest;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StatusController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Move a procurement onto a different progress status.
     */
    public function update(UpdateStatusRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('updateStatus', $procurement);

        $status = ProgressStatus::query()->findOrFail($request->integer('progress_status_id'));

        $this->procurements->changeStatus(
            $procurement,
            $request->user(),
            $status,
            $request->string('note')->trim()->value() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Status diubah menjadi {$status->name}."]);

        return back();
    }
}
