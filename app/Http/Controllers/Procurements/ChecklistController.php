<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Enums\ProcurementStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\UpdateChecklistRequest;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChecklistController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Tick or untick a single checklist row.
     */
    public function update(
        UpdateChecklistRequest $request,
        Procurement $procurement,
        ProcurementChecklist $checklist,
    ): RedirectResponse {
        abort_unless($checklist->procurement_id === $procurement->id, 404);

        $this->authorize(
            $checklist->stage === ProcurementStage::Perencanaan
                ? 'updatePlanningChecklist'
                : 'updateExecutionChecklist',
            $procurement,
        );

        $isCompleted = $request->boolean('is_completed');

        $checklist->load('checklistItem.documentTypes');

        // A step that produces documents is only finished once every signed
        // copy is filed, which is what stops an unevidenced stage from moving
        // on to the next one.
        if ($isCompleted && $checklist->checklistItem->requiresDocument()) {
            $procurement->loadMissing('documents');

            $missing = $checklist->checklistItem->documentTypes
                ->reject(fn ($type): bool => $procurement->signedDocumentFor($type->id) !== null)
                ->pluck('name');

            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'is_completed' => 'Unggah dokumen bertanda tangan berikut sebelum menandai '
                        .'tahapan ini selesai: '.$missing->implode(', ').'.',
                ]);
            }
        }

        $checklist->update([
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
            'completed_by' => $isCompleted ? $request->user()->id : null,
            'notes' => $request->string('notes')->trim()->value() ?: null,
        ]);

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::ChecklistDiperbarui,
            "Checklist {$checklist->checklistItem->name} ditandai ".($isCompleted ? 'selesai' : 'belum selesai').'.',
            ['stage' => $checklist->stage->value, 'checklist_id' => $checklist->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Checklist diperbarui.']);

        return back();
    }
}
