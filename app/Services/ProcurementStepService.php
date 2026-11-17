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
use App\Models\ProcurementStepAddition;
use App\Models\ProcurementStepExclusion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adjusting the steps of one procurement: adding a Data Master step it
 * would otherwise skip, removing a step it does not need, and bringing a
 * removed step back.
 *
 * Only that procurement changes. A step added is recorded as an addition of
 * the Data Master item for the procurement; a step removed is an exclusion
 * of it, so Data Master and every other procurement keep their steps. (Steps
 * written by hand on one procurement, is_custom, are no longer created but
 * stay removable.) A step already ticked, or carrying an uploaded file or a note, is
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
        'pembayaran' => [ProcurementStage::Keuangan, FinanceSection::Pembayaran],
    ];

    /**
     * How each stage is named in the activity log.
     *
     * @var array<string, string>
     */
    /**
     * The stages whose new steps are typed by hand instead of picked from
     * the Data Master.
     *
     * @var list<string>
     */
    public const TYPED_STAGES = ['penyerahan', 'pembayaran'];

    private const LABELS = [
        'perencanaan' => 'Perencanaan',
        'pelaksanaan' => 'Pelaksanaan',
        'penyerahan' => 'Penyerahan',
        'pembayaran' => 'Pembayaran',
    ];

    public function __construct(
        protected ProcurementService $procurements,
        protected ContractRequirementReader $contracts,
    ) {}

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
            'penyerahan' => $procurement->payment_completed_at === null
                && in_array($procurement->handover_approval_state, $open, true),
            // Until Keuangan submits the payment (again open while returned).
            default => $procurement->payment_completed_at === null
                && $procurement->payment_approval_state->isOpen(),
        };
    }

    /**
     * The adjustable stage a step sits on: its Keuangan tab for a Keuangan
     * step (one without a tab shows under Pembayaran).
     */
    public function stageOf(ProcurementChecklist $checklist): string
    {
        return $this->stageOfItem($checklist->checklistItem);
    }

    /**
     * The adjustable stage a checklist item belongs to.
     */
    public function stageOfItem(ChecklistItem $item): string
    {
        return match ($item->stage) {
            ProcurementStage::Perencanaan => 'perencanaan',
            ProcurementStage::Pelaksanaan => 'pelaksanaan',
            ProcurementStage::Keuangan => $item->section === FinanceSection::Penyerahan ? 'penyerahan' : 'pembayaran',
        };
    }

    /**
     * Add a Data Master step to one stage of the procurement, one it does
     * not have yet (left out for its contract format, method or category).
     * It takes its place in the stage by its Data Master order.
     *
     * A step removed from the procurement by hand is brought back instead.
     */
    public function add(Procurement $procurement, User $actor, string $stage, int $itemId): ChecklistItem
    {
        $item = collect($this->available($procurement, $stage))->firstWhere('id', $itemId) === null
            ? null
            : ChecklistItem::query()->find($itemId);

        if ($item === null) {
            throw ValidationException::withMessages([
                'checklist_item_id' => 'Tahapan ini sudah ada pada pengadaan atau bukan tahapan '.self::LABELS[$stage].'.',
            ]);
        }

        // Back from a removal: lifting it is all there is to do.
        $procurement->stepExclusions()->where('checklist_item_id', $item->id)->delete();

        ProcurementStepAddition::query()->firstOrCreate(
            ['procurement_id' => $procurement->id, 'checklist_item_id' => $item->id],
            ['added_by' => $actor->id],
        );

        $this->procurements->recordActivity(
            $procurement,
            $actor,
            ActivityType::ChecklistDiperbarui,
            "Tahapan {$item->name} ditambahkan ke ".self::LABELS[$stage].'.',
            ['stage' => $item->stage->value, 'step' => 'added', 'checklist_item_id' => $item->id],
        );

        $this->procurements->syncChecklists($procurement);

        return $item;
    }

    /**
     * Add a step typed by hand to the Penyerahan or Pembayaran stage of this
     * procurement only. It is placed after the steps already there, and is
     * removed and restored like any other step.
     */
    public function addTyped(Procurement $procurement, User $actor, string $stage, string $name, ?string $description, bool $isOptional): ChecklistItem
    {
        [$procurementStage, $section] = self::STAGES[$stage];
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        $present = $procurement->checklists()
            ->with('checklistItem')
            ->get()
            ->map(fn (ProcurementChecklist $checklist): ChecklistItem => $checklist->checklistItem)
            ->filter(fn (ChecklistItem $item): bool => $item->stage === $procurementStage && $item->section === $section);

        if (! in_array($stage, self::TYPED_STAGES, true) || $section === null) {
            throw ValidationException::withMessages([
                'name' => 'Tahapan '.self::LABELS[$stage].' dipilih dari Data Master.',
            ]);
        }

        if ($present->contains(fn (ChecklistItem $item): bool => mb_strtolower($item->name) === mb_strtolower($name))) {
            throw ValidationException::withMessages([
                'name' => "Tahapan {$name} sudah ada pada ".self::LABELS[$stage].' pengadaan ini.',
            ]);
        }

        $item = ChecklistItem::query()->create([
            'procurement_id' => $procurement->id,
            'is_custom' => true,
            'stage' => $procurementStage,
            'section' => $section,
            'name' => $name,
            'description' => $description === null || trim($description) === '' ? null : trim($description),
            'is_optional' => $isOptional,
            'sort_order' => ((int) $present->max('sort_order')) + 1,
            'is_active' => true,
        ]);

        $this->procurements->recordActivity(
            $procurement,
            $actor,
            ActivityType::ChecklistDiperbarui,
            "Tahapan {$item->name} ditambahkan ke ".self::LABELS[$stage].'.',
            ['stage' => $item->stage->value, 'step' => 'added', 'checklist_item_id' => $item->id],
        );

        $this->procurements->syncChecklists($procurement);

        return $item;
    }

    /**
     * The names to recommend while a Penyerahan or Pembayaran step is typed:
     * first the requirements listed in this procurement's own contract,
     * then every name that stage has used on other procurements, the most
     * used first. Nothing is added by itself: these are only offered. Names
     * already on this procurement are left out; the same name in another
     * case counts once.
     *
     * @return list<array{name: string, uses: int, from_contract: bool}>
     */
    public function suggestions(Procurement $procurement, string $stage): array
    {
        [$procurementStage, $section] = self::STAGES[$stage];

        if (! in_array($stage, self::TYPED_STAGES, true) || $section === null) {
            return [];
        }

        $present = $procurement->checklists()
            ->join('checklist_items', 'checklist_items.id', '=', 'procurement_checklists.checklist_item_id')
            ->where('checklist_items.section', $section->value)
            ->pluck('checklist_items.name')
            ->map(fn (string $name): string => mb_strtolower(trim($name)))
            ->all();

        $names = [];

        // The contract's own list, as it reads now.
        try {
            $contract = $this->contracts->read($procurement)[$section->value] ?? [];
        } catch (Throwable $exception) {
            // An unreadable contract only means no recommendation from it.
            report($exception);
            $contract = [];
        }

        foreach ($contract as $requirement) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $requirement['name']));
            $key = mb_strtolower($name);

            if ($name !== '' && ! in_array($key, $present, true)) {
                $names[$key] ??= ['name' => $name, 'uses' => 0, 'from_contract' => true];
            }
        }

        $used = [];

        DB::table('checklist_items')
            ->leftJoin('procurement_checklists', 'procurement_checklists.checklist_item_id', '=', 'checklist_items.id')
            ->where('checklist_items.stage', $procurementStage->value)
            ->where('checklist_items.section', $section->value)
            ->where('checklist_items.is_active', true)
            ->whereNull('checklist_items.deleted_at')
            ->groupBy('checklist_items.name')
            ->selectRaw('checklist_items.name as name, count(procurement_checklists.id) as uses')
            // Only names actually used on a procurement: an unused Data
            // Master entry is not a recommendation.
            ->havingRaw('count(procurement_checklists.id) > 0')
            ->get()
            ->each(function (object $row) use (&$used, $present): void {
                $name = trim((string) preg_replace('/\s+/u', ' ', (string) $row->name));
                $key = mb_strtolower($name);

                if ($name === '' || in_array($key, $present, true)) {
                    return;
                }

                $used[$key] = [
                    'name' => $used[$key]['name'] ?? $name,
                    'uses' => ($used[$key]['uses'] ?? 0) + (int) $row->uses,
                    'from_contract' => false,
                ];
            });

        // A contract requirement also used before shows how often.
        foreach ($used as $key => $suggestion) {
            if (isset($names[$key])) {
                $names[$key]['uses'] = $suggestion['uses'];
                unset($used[$key]);
            }
        }

        usort($used, fn (array $a, array $b): int => [$b['uses'], $a['name']] <=> [$a['uses'], $b['name']]);

        return array_slice([...array_values($names), ...$used], 0, 200);
    }

    /**
     * The Data Master steps of a stage the procurement does not have, in
     * their Data Master order: the ones its contract format, method or
     * category leaves out. Those removed by hand are restored from their
     * own list instead.
     *
     * @return list<array{id: int, name: string, description: string|null, is_optional: bool}>
     */
    public function available(Procurement $procurement, string $stage): array
    {
        [$procurementStage, $section] = self::STAGES[$stage];

        $present = $procurement->checklists()->pluck('checklist_item_id');
        $excluded = $procurement->stepExclusions()->pluck('checklist_item_id');

        return array_values(ChecklistItem::query()
            ->shared()
            ->active()
            ->forStage($procurementStage)
            ->when($section !== null, fn ($query) => $query->where('section', $section?->value))
            ->whereNotIn('id', $present->merge($excluded))
            ->ordered()
            ->get()
            ->map(fn (ChecklistItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'is_optional' => $item->is_optional,
            ])
            ->all());
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

        $stage = $this->stageOf($checklist);

        // A step added by hand just goes again; one the procurement has of
        // its own is excluded, so it can be restored.
        $wasAdded = $procurement->stepAdditions()->where('checklist_item_id', $checklist->checklist_item_id)->delete() > 0;

        if (! $wasAdded) {
            ProcurementStepExclusion::query()->firstOrCreate(
                ['procurement_id' => $procurement->id, 'checklist_item_id' => $checklist->checklist_item_id],
                ['removed_by' => $actor->id],
            );
        }

        $this->procurements->recordActivity(
            $procurement,
            $actor,
            ActivityType::ChecklistDiperbarui,
            "Tahapan {$checklist->checklistItem->name} dihapus dari ".self::LABELS[$stage].'.',
            ['stage' => $checklist->stage->value, 'step' => 'removed', 'checklist_item_id' => $checklist->checklist_item_id],
        );

        $this->procurements->syncChecklists($procurement);

        // Added by hand but since part of the procurement's own steps (its
        // format changed): excluded like any of them.
        if ($wasAdded && $procurement->checklists()->where('checklist_item_id', $checklist->checklist_item_id)->exists()) {
            ProcurementStepExclusion::query()->firstOrCreate(
                ['procurement_id' => $procurement->id, 'checklist_item_id' => $checklist->checklist_item_id],
                ['removed_by' => $actor->id],
            );

            $this->procurements->syncChecklists($procurement);
        }
    }

    /**
     * Bring a removed step back, as a fresh step to do.
     */
    public function restore(Procurement $procurement, User $actor, ProcurementStepExclusion $exclusion): void
    {
        $item = $exclusion->checklistItem;
        $stage = $this->stageOfItem($item);

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
}
