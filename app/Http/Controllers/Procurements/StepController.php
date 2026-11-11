<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\StoreProcurementStepRequest;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementStepExclusion;
use App\Services\ProcurementStepService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Adjusting the steps of one procurement: adding a step of its own,
 * removing a step it does not need, and bringing a removed step back.
 */
class StepController extends Controller
{
    public function __construct(protected ProcurementStepService $steps) {}

    /**
     * Add a step to one stage of the procurement.
     */
    public function store(StoreProcurementStepRequest $request, Procurement $procurement): RedirectResponse
    {
        $item = $this->steps->add($procurement, $request->user(), $request->string('stage')->value(), [
            'name' => $request->string('name')->value(),
            'description' => $request->input('description'),
            'is_optional' => $request->boolean('is_optional'),
            'after_checklist_id' => $request->integer('after_checklist_id') ?: null,
            'document_type_id' => $request->integer('document_type_id') ?: null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tahapan {$item->name} ditambahkan."]);

        return back();
    }

    /**
     * Remove a step from the procurement.
     */
    public function destroy(Request $request, Procurement $procurement, ProcurementChecklist $checklist): RedirectResponse
    {
        abort_unless($checklist->procurement_id === $procurement->id, 404);

        $stage = $this->steps->stageOf($checklist);
        abort_if($stage === null, 404);

        $this->authorize('manageSteps', [$procurement, $stage]);

        $this->steps->remove($procurement, $request->user(), $checklist);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tahapan dihapus dari pengadaan ini. Dapat dipulihkan kapan saja.']);

        return back();
    }

    /**
     * Bring a removed step back.
     */
    public function restore(Request $request, Procurement $procurement, ProcurementStepExclusion $exclusion): RedirectResponse
    {
        abort_unless($exclusion->procurement_id === $procurement->id, 404);

        $item = $exclusion->checklistItem;
        $stage = match (true) {
            $item->stage->value === 'keuangan' => 'penyerahan',
            default => $item->stage->value,
        };

        $this->authorize('manageSteps', [$procurement, $stage]);

        $this->steps->restore($procurement, $request->user(), $exclusion);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tahapan {$item->name} dipulihkan."]);

        return back();
    }
}
