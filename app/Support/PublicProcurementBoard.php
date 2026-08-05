<?php

namespace App\Support;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Feeds the rotating showcase panel next to the login form.
 *
 * Only aggregate process information is exposed: no budget figures and no
 * personnel names.
 */
class PublicProcurementBoard
{
    /**
     * The full showcase payload.
     *
     * @return array<string, mixed>
     */
    public function loginShowcase(): array
    {
        return [
            'summary' => $this->summary(),
            'statuses' => $this->statuses(),
            'units' => $this->units(),
            'unitCount' => TargetUnit::query()->active()->count(),
            'planningSteps' => $this->steps(ProcurementStage::Perencanaan),
            'executionSteps' => $this->steps(ProcurementStage::Pelaksanaan),
        ];
    }

    /**
     * The number of procurements in each stage of the process.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        $categories = Procurement::query()
            ->toBase()
            ->join('progress_statuses', 'progress_statuses.id', '=', 'procurements.progress_status_id')
            ->whereNull('procurements.deleted_at')
            ->selectRaw('progress_statuses.category as category, count(*) as total')
            ->groupBy('progress_statuses.category')
            ->pluck('total', 'category');

        return [
            'total' => Procurement::query()->count(),
            'perencanaan' => $this->stillPlanning()->count(),
            'pelaksanaan' => $this->inExecution()->count(),
            'menungguApproval' => Procurement::query()
                ->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                ->count(),
            'selesai' => (int) ($categories[StatusCategory::Selesai->value] ?? 0),
            'batal' => (int) ($categories[StatusCategory::Batal->value] ?? 0),
        ];
    }

    /**
     * The configured progress statuses.
     *
     * @return array<int, array{name: string, category: string}>
     */
    protected function statuses(): array
    {
        return ProgressStatus::query()->active()->ordered()->get()
            ->map(fn (ProgressStatus $status): array => [
                'name' => $status->name,
                'category' => $status->category->value,
            ])
            ->all();
    }

    /**
     * The units served by the system.
     *
     * @return array<int, string>
     */
    protected function units(): array
    {
        return TargetUnit::query()->active()->ordered()->get()
            ->map(fn (TargetUnit $unit): string => $unit->name)
            ->all();
    }

    /**
     * The configured checklist steps of a stage.
     *
     * @return array<int, array{name: string, is_optional: bool}>
     */
    protected function steps(ProcurementStage $stage): array
    {
        return ChecklistItem::query()->active()->forStage($stage)->ordered()->get()
            ->map(fn (ChecklistItem $item): array => [
                'name' => $item->name,
                'is_optional' => $item->is_optional,
            ])
            ->all();
    }

    /**
     * Procurements whose planning stage is still open.
     *
     * @return Builder<Procurement>
     */
    protected function stillPlanning(): Builder
    {
        return Procurement::query()
            ->whereNull('completed_at')
            ->where('planning_approval_state', '!=', PlanningApprovalState::Disetujui->value)
            ->whereHas(
                'progressStatus',
                fn (Builder $query) => $query->where('category', '!=', StatusCategory::Batal->value),
            );
    }

    /**
     * Procurements that have moved on to the execution stage.
     *
     * @return Builder<Procurement>
     */
    protected function inExecution(): Builder
    {
        return Procurement::query()
            ->whereNull('completed_at')
            ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
            ->whereHas(
                'progressStatus',
                fn (Builder $query) => $query->where('category', '!=', StatusCategory::Batal->value),
            );
    }
}
