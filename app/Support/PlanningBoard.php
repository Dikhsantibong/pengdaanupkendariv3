<?php

namespace App\Support;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything the public planning board renders.
 *
 * Budget figures and personnel names are never included.
 */
class PlanningBoard
{
    public function __construct(
        protected BoardMetrics $metrics,
        protected ProcurementSCurve $curve,
    ) {}

    /**
     * Build the full board payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $procurements = $this->scope()->get();

        return [
            'summary' => $this->summary($procurements),
            'sCurve' => $this->curve->build($procurements, ProcurementStage::Perencanaan),
            'checklistBreakdown' => $this->metrics->checklistBreakdown($procurements, ProcurementStage::Perencanaan),
            'byStatus' => $this->metrics->statusDistribution($procurements),
            'byUnit' => $this->metrics->unitProgress($procurements, ProcurementStage::Perencanaan),
            'byDirector' => $this->metrics->directorDistribution($procurements),
            'monthlyIntake' => $this->metrics->monthlySeries($procurements, 'created_at'),
            'approvalComposition' => $this->approvalComposition($procurements),
            'rows' => $this->rows($procurements),
            'generatedAt' => now()->toDateTimeString(),
        ];
    }

    /**
     * The procurements whose planning stage is still open.
     *
     * @return Builder<Procurement>
     */
    protected function scope(): Builder
    {
        return Procurement::query()
            ->whereNull('completed_at')
            ->where('planning_approval_state', '!=', PlanningApprovalState::Disetujui->value)
            ->whereHas(
                'progressStatus',
                fn (Builder $query) => $query->where('category', '!=', StatusCategory::Batal->value),
            )
            ->with([
                'targetUnit',
                'workDirector',
                'progressStatus',
                'checklists.checklistItem',
            ])
            ->orderBy('progress_status_id')
            ->latest('created_at');
    }

    /**
     * The headline numbers of the planning stage.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<string, int>
     */
    protected function summary(Collection $procurements): array
    {
        $byState = fn (PlanningApprovalState $state): int => $procurements
            ->where('planning_approval_state', $state)
            ->count();

        return [
            'total' => $procurements->count(),
            'belumDiajukan' => $byState(PlanningApprovalState::BelumDiajukan),
            'menungguPersetujuan' => $byState(PlanningApprovalState::MenungguPersetujuan),
            'ditolak' => $byState(PlanningApprovalState::Ditolak),
            'disetujui' => Procurement::query()
                ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
                ->count(),
            'rataRataProgres' => $this->metrics->averageProgress($procurements, ProcurementStage::Perencanaan),
            'rataRataUsia' => $this->metrics->averageAgeInDays($procurements, 'created_at'),
            'langkahDokumen' => ChecklistItem::query()
                ->active()
                ->forStage(ProcurementStage::Perencanaan)
                ->count(),
            'siapDiajukan' => $procurements
                ->filter(fn (Procurement $procurement): bool => $this->isReadyToSubmit($procurement))
                ->count(),
        ];
    }

    /**
     * The approval funnel of the planning stage.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array{label: string, total: int, category: string}>
     */
    protected function approvalComposition(Collection $procurements): array
    {
        $map = [
            [PlanningApprovalState::BelumDiajukan, 'pending'],
            [PlanningApprovalState::MenungguPersetujuan, 'berjalan'],
            [PlanningApprovalState::Ditolak, 'batal'],
        ];

        $composition = [];

        foreach ($map as [$state, $category]) {
            $composition[] = [
                'label' => $state->label(),
                'total' => $procurements->where('planning_approval_state', $state)->count(),
                'category' => $category,
            ];
        }

        $composition[] = [
            'label' => PlanningApprovalState::Disetujui->label(),
            'total' => Procurement::query()
                ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
                ->count(),
            'category' => 'selesai',
        ];

        return $composition;
    }

    /**
     * The per-procurement table of the planning stage.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array<string, mixed>>
     */
    protected function rows(Collection $procurements): array
    {
        return $procurements
            ->map(function (Procurement $procurement): array {
                $progress = $procurement->checklistProgress(ProcurementStage::Perencanaan);

                return [
                    'id' => $procurement->id,
                    'number' => $procurement->number,
                    'name' => $procurement->name,
                    'target_unit' => $procurement->targetUnit->name,
                    'work_director' => $procurement->workDirector->name,
                    'status' => $procurement->progressStatus->name,
                    'category' => $procurement->progressStatus->category->value,
                    'approval_label' => $procurement->planning_approval_state->label(),
                    'approval_state' => $procurement->planning_approval_state->value,
                    'completed' => $progress['completed'],
                    'total' => $progress['total'],
                    'percentage' => $progress['percentage'],
                    'age_days' => $procurement->created_at === null
                        ? 0
                        : (int) CarbonImmutable::parse($procurement->created_at)->diffInDays(CarbonImmutable::now()),
                    'target_date' => $procurement->target_completion_date?->toDateString(),
                ];
            })
            ->all();
    }

    /**
     * Whether every mandatory planning item has been ticked.
     */
    protected function isReadyToSubmit(Procurement $procurement): bool
    {
        $mandatory = $procurement->checklists
            ->where('stage', ProcurementStage::Perencanaan)
            ->filter(fn ($checklist): bool => ! $checklist->checklistItem->is_optional);

        return $mandatory->isNotEmpty()
            && $mandatory->every(fn ($checklist): bool => $checklist->is_completed)
            && $procurement->planning_approval_state !== PlanningApprovalState::MenungguPersetujuan;
    }
}
