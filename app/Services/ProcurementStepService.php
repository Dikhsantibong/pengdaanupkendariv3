<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementStepExclusion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adjusting the steps of one procurement: adding a step of its own,
 * removing a step it does not need, and bringing a removed step back.
 *
 * Only that procurement changes. A step added is a checklist item owned by
 * the procurement and marked custom; a step removed is an exclusion of the
 * item for the procurement, so Data Master and every other procurement keep
 * it. A step already ticked, or carrying an uploaded file or a note, is
 * never removed: its record stays as it is.
 */
class ProcurementStepService
{
    /**
     * The stages whose steps can be adjusted, with the Keuangan tab of each.
     *
     * @var array<string, array{0: ProcurementStage, 1: FinanceSection|null}>
     */
    public const STAGES = [
        'perencanaan' => [ProcurementStage::Perencanaan, null],
        'pelaksanaan' => [ProcurementStage::Pelaksanaan, null],
        'penyerahan' => [ProcurementStage::Keuangan, FinanceSection::Penyerahan],
    ];

    /**
     * How each stage is named in the activity log.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'perencanaan' => 'Perencanaan',
        'pelaksanaan' => 'Pelaksanaan',
        'penyerahan' => 'Penyerahan',
    ];

    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Whether a stage's steps may still be adjusted: until the stage is
     * submitted for approval (again open while it is returned), and never on
     * a finished or cancelled procurement.
     */
    public function isOpen(Procurement $procurement, string $stage): bool
    {
        if (! array_key_exists($stage, self::STAGES)
            || $procurement->completed_at !== null
            || $procurement->progressStatus->category === StatusCategory::Batal) {
            return false;
        }

        $open = [PlanningApprovalState::BelumDiajukan, PlanningApprovalState::Ditolak];

        return match ($stage) {
            'perencanaan' => in_array($procurement->planning_approval_state, $open, true),
            'pelaksanaan' => ! $procurement->isExecutionAwaitingApproval()
                && $procurement->finance_submitted_at === null,
            default => $procurement->payment_completed_at === null
                && in_array($procurement->handover_approval_state, $open, true),
        };
    }

    /**
     * The adjustable stage a step sits on, or null for one that is not
     * adjusted here (Pembayaran).
     */
    public function stageOf(ProcurementChecklist $checklist): ?string
    {
        return match ($checklist->stage) {
            ProcurementStage::Perencanaan => 'perencanaan',
            ProcurementStage::Pelaksanaan => 'pelaksanaan',
            ProcurementStage::Keuangan => $checklist->financeSection() === FinanceSection::Penyerahan ? 'penyerahan' : null,
        };
    }

    /**
     * Add a step of its own to one stage of the procurement.
     *
     * It goes right after the step named, or at the end of the stage.
     *
     * @param  array{name: string, description?: string|null, is_optional?: bool, after_checklist_id?: int|null, document_type_id?: int|null}  $data
     */
    public function add(Procurement $procurement, User $actor, string $stage, array $data): ChecklistItem
    {
        [$procurementStage, $section] = self::STAGES[$stage];

        $item = DB::transaction(function () use ($procurement, $stage, $procurementStage, $section, $data): ChecklistItem {
            $after = isset($data['after_checklist_id'])
                ? $procurement->checklists()->with('checklistItem')->whereKey($data['after_checklist_id'])->first()
                : null;

            // A step of another stage is no place to put it.
            if ($after !== null && $this->stageOf($after) !== $stage) {
                $after = null;
            }

            $item = ChecklistItem::query()->create([
                'procurement_id' => $procurement->id,
                'is_custom' => true,
                'stage' => $procurementStage,
                'section' => $section,
                'name' => trim($data['name']),
                'description' => isset($data['description']) && trim($data['description']) !== '' ? trim($data['description']) : null,
                'is_optional' => (bool) ($data['is_optional'] ?? false),
                // Ties keep the order the steps were added in.
                'sort_order' => $after?->checklistItem->sort_order ?? $this->lastSortOrder($procurement, $procurementStage, $section) + 1,
                'is_active' => true,
            ]);

            if (isset($data['document_type_id'])) {
                $item->documentTypes()->attach($data['document_type_id'], ['sort_order' => 1, 'is_alternative' => false]);
            }

            return $item;
        });

        $this->procurements->recordActivity(
            $procurement,
            $actor,
            ActivityType::ChecklistDiperbarui,
            "Tahapan {$item->name} ditambahkan ke ".self::LABELS[$stage].'.',
            ['stage' => $procurementStage->value, 'step' => 'added', 'checklist_item_id' => $item->id],
        );

        $this->procurements->syncChecklists($procurement);

        return $item;
    }

    /**
     * Remove a step from the procurement.
     *
     * Refused for a step already ticked, or with a file or note on it, so
     * nothing filed on a step is ever lost.
     */
    public function remove(Procurement $procurement, User $actor, ProcurementChecklist $checklist): void
    {
        $checklist->loadMissing('checklistItem');

        $reason = match (true) {
            $checklist->is_completed => 'sudah ditandai selesai. Tandai belum selesai dulu bila memang perlu dihapus',
            $checklist->files()->exists() => 'sudah berisi berkas unggahan. Hapus berkasnya dulu bila memang perlu dihapus',
            $checklist->notes !== null && trim($checklist->notes) !== '' => 'memiliki catatan',
            default => null,
        };

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'step' => "Tahapan {$checklist->checklistItem->name} tidak dapat dihapus karena {$reason}.",
            ]);
        }

        $stage = (string) $this->stageOf($checklist);

        ProcurementStepExclusion::query()->firstOrCreate(
            ['procurement_id' => $procurement->id, 'checklist_item_id' => $checklist->checklist_item_id],
            ['removed_by' => $actor->id],
        );

        $this->procurements->recordActivity(
            $procurement,
            $actor,
            ActivityType::ChecklistDiperbarui,
            "Tahapan {$checklist->checklistItem->name} dihapus dari ".self::LABELS[$stage].'.',
            ['stage' => $checklist->stage->value, 'step' => 'removed', 'checklist_item_id' => $checklist->checklist_item_id],
        );

        $this->procurements->syncChecklists($procurement);
    }

    /**
     * Bring a removed step back, as a fresh step to do.
     */
    public function restore(Procurement $procurement, User $actor, ProcurementStepExclusion $exclusion): void
    {
        $item = $exclusion->checklistItem;
        $stage = $item->stage === ProcurementStage::Keuangan ? 'penyerahan' : $item->stage->value;

        $exclusion->delete();

        $this->procurements->recordActivity(
            $procurement,
            $actor,
            ActivityType::ChecklistDiperbarui,
            "Tahapan {$item->name} dipulihkan ke ".self::LABELS[$stage].'.',
            ['stage' => $item->stage->value, 'step' => 'restored', 'checklist_item_id' => $item->id],
        );

        $this->procurements->syncChecklists($procurement);
    }

    /**
     * The steps removed from one stage of the procurement, latest first.
     *
     * @return list<array{id: int, name: string, is_custom: bool, removed_by: string|null, removed_at: string|null}>
     */
    public function removed(Procurement $procurement, string $stage): array
    {
        [$procurementStage, $section] = self::STAGES[$stage];

        return array_values($procurement->stepExclusions()
            ->with(['checklistItem', 'remover:id,name'])
            ->latest('id')
            ->get()
            ->filter(fn (ProcurementStepExclusion $exclusion): bool => $exclusion->checklistItem->stage === $procurementStage
                && ($section === null || $exclusion->checklistItem->section === $section))
            ->map(fn (ProcurementStepExclusion $exclusion): array => [
                'id' => $exclusion->id,
                'name' => $exclusion->checklistItem->name,
                'is_custom' => $exclusion->checklistItem->is_custom,
                'removed_by' => $exclusion->remover?->name,
                'removed_at' => $exclusion->created_at?->toDateTimeString(),
            ])
            ->all());
    }

    /**
     * The highest sort order among the procurement's steps of a stage.
     */
    protected function lastSortOrder(Procurement $procurement, ProcurementStage $stage, ?FinanceSection $section): int
    {
        return (int) ChecklistItem::query()
            ->withTrashed()
            ->whereIn('id', $procurement->checklists()->where('stage', $stage->value)->select('checklist_item_id'))
            ->when($section !== null, fn ($query) => $query->where('section', $section?->value))
            ->max('sort_order');
    }
}
