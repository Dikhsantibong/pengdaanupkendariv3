<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Enums\ChecklistInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\UpdateChecklistInputRequest;
use App\Http\Requests\Procurements\UpdateChecklistRequest;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Services\ProcurementService;
use App\Services\ProgressStatusResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChecklistController extends Controller
{
    /**
     * The ability that guards the steps of each stage.
     *
     * @var array<string, string>
     */
    protected const ABILITIES = [
        'perencanaan' => 'updatePlanningChecklist',
        'pelaksanaan' => 'updateExecutionChecklist',
        'keuangan' => 'updateFinanceChecklist',
    ];

    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Save the data a checklist step asks for.
     *
     * Authorised like ticking the step itself, since the data is part of
     * finishing it. Only the fields of the step's own input are written.
     */
    public function updateInput(
        UpdateChecklistInputRequest $request,
        Procurement $procurement,
        ProcurementChecklist $checklist,
    ): RedirectResponse {
        abort_unless($checklist->procurement_id === $procurement->id, 404);

        $this->authorize(self::ABILITIES[$checklist->stage->value], $procurement);

        $kind = $checklist->checklistItem->input_kind;

        // A kind without fields of its own (the calon mitra) is not saved here.
        abort_if($kind === null || $kind->fields() === [], 404);

        $values = $request->safe()->only($kind->fields());

        // Picking the executor puts that candidate's details on the
        // procurement, where the contract documents read them.
        if ($kind === ChecklistInput::BankAccount) {
            $candidate = $procurement->partnerCandidates()->whereKey((int) $values['partner_candidate_id'])->firstOrFail();
            $values = [...$values, ...$candidate->partnerFields()];
        }

        // The category picks the barang or jasa contract template, as it did
        // on the procurement form; formats without one keep none.
        if ($kind === ChecklistInput::ContractNumber) {
            $values['number'] = trim((string) $values['number']);
            $usesCategory = $procurement->contractNumberFormat?->uses_work_category === true;
            $contractType = $usesCategory && ($values['contract_type_id'] ?? null) !== null
                ? ContractType::query()->find((int) $values['contract_type_id'])
                : null;
            $values['contract_type_id'] = $contractType?->id;
            $values['work_category'] = $contractType?->work_category;
        }

        $procurement->forceFill($values)->save();

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::ChecklistDiperbarui,
            "Isian {$checklist->checklistItem->name} diperbarui.",
            ['stage' => $checklist->stage->value, 'checklist_id' => $checklist->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Isian tersimpan.']);

        return back();
    }

    /**
     * Tick or untick a single checklist row.
     */
    public function update(
        UpdateChecklistRequest $request,
        Procurement $procurement,
        ProcurementChecklist $checklist,
    ): RedirectResponse {
        abort_unless($checklist->procurement_id === $procurement->id, 404);

        $this->authorize(self::ABILITIES[$checklist->stage->value], $procurement);

        $isCompleted = $request->boolean('is_completed');

        $checklist->load('checklistItem.documentTypes');

        // A step that produces documents is only finished once every signed
        // copy is filed, which is what stops an unevidenced stage from moving
        // on to the next one. An optional step may be ticked without them:
        // its document is filed only when the job actually needs it.
        if ($isCompleted && ! $checklist->checklistItem->is_optional && $checklist->checklistItem->documentTypesFor($procurement)->isNotEmpty()) {
            $procurement->loadMissing('documents');

            $missing = $checklist->checklistItem->missingDocuments(
                fn (int $typeId): bool => $procurement->signedDocumentFor($typeId) !== null,
                $procurement,
            );

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'is_completed' => 'Unggah dokumen berikut sebelum menandai '
                        .'tahapan ini selesai: '.implode(', ', $missing).'.',
                ]);
            }
        }

        // A Penyerahan / Pembayaran requirement is an upload, not a bare tick.
        if ($isCompleted && ! $checklist->checklistItem->is_optional
            && $checklist->needsOwnUpload() && ! $checklist->files()->exists()) {
            throw ValidationException::withMessages([
                'is_completed' => "Unggah berkas {$checklist->checklistItem->name} sebelum menandai tahapan ini selesai.",
            ]);
        }

        // A step that asks for data is only finished once that data is in.
        $input = $checklist->checklistItem->input_kind;

        if ($isCompleted && $input !== null && ! $input->isFilledOn($procurement)) {
            throw ValidationException::withMessages([
                'is_completed' => 'Lengkapi isian '.mb_strtolower($input->label())
                    .' sebelum menandai tahapan ini selesai.',
            ]);
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

        app(ProgressStatusResolver::class)->sync($procurement);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Checklist diperbarui.']);

        return back();
    }
}
