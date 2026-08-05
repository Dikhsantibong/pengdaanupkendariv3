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
 * Everything the public execution board renders.
 *
 * Budget figures and personnel names are never included.
 */
class ExecutionBoard
{
    /**
     * Procurements due within this many days count as approaching their target.
     */
    private const DUE_SOON_DAYS = 14;

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
            'sCurve' => $this->curve->build($procurements, ProcurementStage::Pelaksanaan),
            'checklistBreakdown' => $this->metrics->checklistBreakdown($procurements, ProcurementStage::Pelaksanaan),
            'byStatus' => $this->metrics->statusDistribution($procurements),
            'byUnit' => $this->metrics->unitProgress($procurements, ProcurementStage::Pelaksanaan),
            'monthlyCompleted' => $this->metrics->monthlySeries($this->completedProcurements(), 'completed_at'),
            'scheduleComposition' => $this->scheduleComposition($procurements),
            'rows' => $this->rows($procurements),
            'completed' => $this->recentlyCompleted(),
            'generatedAt' => now()->toDateTimeString(),
        ];
    }

    /**
     * The procurements that have moved on to the execution stage.
     *
     * @return Builder<Procurement>
     */
    protected function scope(): Builder
    {
        return Procurement::query()
            ->whereNull('completed_at')
            ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
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
     * The headline numbers of the execution stage.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<string, int>
     */
    protected function summary(Collection $procurements): array
    {
        return [
            'total' => $procurements->count(),
            'selesai' => Procurement::query()->whereNotNull('completed_at')->count(),
            'rataRataProgres' => $this->metrics->averageProgress($procurements, ProcurementStage::Pelaksanaan),
            'rataRataUsia' => $this->metrics->averageAgeInDays($procurements, 'planning_reviewed_at'),
            'terlambat' => $procurements
                ->filter(fn (Procurement $procurement): bool => $this->remainingDays($procurement) < 0)
                ->count(),
            'mendekatiTarget' => $procurements
                ->filter(function (Procurement $procurement): bool {
                    $remaining = $this->remainingDays($procurement);

                    return $remaining !== null && $remaining >= 0 && $remaining <= self::DUE_SOON_DAYS;
                })
                ->count(),
            'tahapanPelaksanaan' => ChecklistItem::query()
                ->active()
                ->forStage(ProcurementStage::Pelaksanaan)
                ->count(),
            'hampirTuntas' => $procurements
                ->filter(fn (Procurement $procurement): bool => $procurement->checklistProgress(ProcurementStage::Pelaksanaan)['percentage'] >= 80)
                ->count(),
        ];
    }

    /**
     * How the running procurements sit against their target date.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array{label: string, total: int, category: string}>
     */
    protected function scheduleComposition(Collection $procurements): array
    {
        $onTrack = 0;
        $dueSoon = 0;
        $late = 0;
        $noTarget = 0;

        foreach ($procurements as $procurement) {
            $remaining = $this->remainingDays($procurement);

            if ($remaining === null) {
                $noTarget++;

                continue;
            }

            if ($remaining < 0) {
                $late++;
            } elseif ($remaining <= self::DUE_SOON_DAYS) {
                $dueSoon++;
            } else {
                $onTrack++;
            }
        }

        return [
            ['label' => 'Sesuai jadwal', 'total' => $onTrack, 'category' => 'selesai'],
            ['label' => 'Mendekati target', 'total' => $dueSoon, 'category' => 'berjalan'],
            ['label' => 'Melewati target', 'total' => $late, 'category' => 'batal'],
            ['label' => 'Tanpa target', 'total' => $noTarget, 'category' => 'pending'],
        ];
    }

    /**
     * The per-procurement table of the execution stage.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array<string, mixed>>
     */
    protected function rows(Collection $procurements): array
    {
        return $procurements
            ->map(function (Procurement $procurement): array {
                $progress = $procurement->checklistProgress(ProcurementStage::Pelaksanaan);

                return [
                    'id' => $procurement->id,
                    'number' => $procurement->number,
                    'name' => $procurement->name,
                    'target_unit' => $procurement->targetUnit->name,
                    'work_director' => $procurement->workDirector->name,
                    'status' => $procurement->progressStatus->name,
                    'category' => $procurement->progressStatus->category->value,
                    'completed' => $progress['completed'],
                    'total' => $progress['total'],
                    'percentage' => $progress['percentage'],
                    'target_date' => $procurement->target_completion_date?->toDateString(),
                    'remaining_days' => $this->remainingDays($procurement),
                    'running_days' => $procurement->planning_reviewed_at === null
                        ? 0
                        : (int) CarbonImmutable::parse($procurement->planning_reviewed_at)->diffInDays(CarbonImmutable::now()),
                ];
            })
            ->all();
    }

    /**
     * The most recently finished procurements.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function recentlyCompleted(int $limit = 8): array
    {
        return Procurement::query()
            ->whereNotNull('completed_at')
            ->with('targetUnit')
            ->latest('completed_at')
            ->limit($limit)
            ->get()
            ->map(fn (Procurement $procurement): array => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'name' => $procurement->name,
                'target_unit' => $procurement->targetUnit->name,
                'completed_at' => $procurement->completed_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Every finished procurement, used for the monthly completion series.
     *
     * @return Collection<int, Procurement>
     */
    protected function completedProcurements(): Collection
    {
        return Procurement::query()->whereNotNull('completed_at')->get();
    }

    /**
     * Days left until the target completion date, negative when overdue.
     */
    protected function remainingDays(Procurement $procurement): ?int
    {
        if ($procurement->target_completion_date === null) {
            return null;
        }

        return (int) CarbonImmutable::now()
            ->startOfDay()
            ->diffInDays($procurement->target_completion_date->startOfDay(), false);
    }
}
