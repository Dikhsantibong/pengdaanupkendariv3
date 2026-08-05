<?php

namespace App\Support;

use App\Enums\ProcurementStage;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Aggregations shared by the public planning and execution boards.
 *
 * Every helper works on an already loaded collection so a board only hits the
 * database once, which keeps the polled endpoints cheap.
 */
class BoardMetrics
{
    /**
     * Count the procurements sitting on each progress status.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array{name: string, category: string, total: int}>
     */
    public function statusDistribution(Collection $procurements): array
    {
        return $procurements
            ->groupBy(fn (Procurement $procurement): int => $procurement->progress_status_id)
            ->map(fn (Collection $group): array => [
                'name' => $group->first()->progressStatus->name,
                'category' => $group->first()->progressStatus->category->value,
                'total' => $group->count(),
                'sort' => $group->first()->progressStatus->sort_order,
            ])
            ->sortBy('sort')
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'category' => $row['category'],
                'total' => $row['total'],
            ])
            ->values()
            ->all();
    }

    /**
     * Summarise volume and average stage progress per target unit.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array{name: string, total: int, percentage: int}>
     */
    public function unitProgress(Collection $procurements, ProcurementStage $stage): array
    {
        return $procurements
            ->groupBy(fn (Procurement $procurement): int => $procurement->target_unit_id)
            ->map(fn (Collection $group): array => [
                'name' => $group->first()->targetUnit->name,
                'total' => $group->count(),
                'percentage' => $this->averageProgress($group, $stage),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Count the procurements directed by each official.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array{name: string, total: int, percentage: int}>
     */
    public function directorDistribution(Collection $procurements): array
    {
        return $procurements
            ->groupBy(fn (Procurement $procurement): int => $procurement->work_director_id)
            ->map(fn (Collection $group): array => [
                'name' => $group->first()->workDirector->name,
                'total' => $group->count(),
                'percentage' => 0,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Report how many procurements have ticked each checklist item of a stage.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array{name: string, is_optional: bool, completed: int, total: int, percentage: int}>
     */
    public function checklistBreakdown(Collection $procurements, ProcurementStage $stage): array
    {
        return $procurements
            ->flatMap(fn (Procurement $procurement): Collection => $procurement->checklists->where('stage', $stage))
            ->groupBy(fn (ProcurementChecklist $checklist): int => $checklist->checklist_item_id)
            ->map(function (Collection $group): array {
                $item = $group->first()->checklistItem;
                $completed = $group->where('is_completed', true)->count();

                return [
                    'name' => $item->name,
                    'is_optional' => $item->is_optional,
                    'completed' => $completed,
                    'total' => $group->count(),
                    'percentage' => $group->count() > 0
                        ? (int) round($completed / $group->count() * 100)
                        : 0,
                    'sort' => $item->sort_order,
                ];
            })
            ->sortBy('sort')
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'is_optional' => $row['is_optional'],
                'completed' => $row['completed'],
                'total' => $row['total'],
                'percentage' => $row['percentage'],
            ])
            ->values()
            ->all();
    }

    /**
     * Build a month-by-month count for a date attribute.
     *
     * @param  Collection<int, Procurement>  $procurements
     * @return array<int, array{label: string, total: int}>
     */
    public function monthlySeries(Collection $procurements, string $attribute, int $months = 12): array
    {
        $buckets = [];
        $cursor = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        for ($index = 0; $index < $months; $index++) {
            $month = $cursor->addMonths($index);
            $buckets[$month->format('Y-m')] = [
                'label' => $month->translatedFormat('M y'),
                'total' => 0,
            ];
        }

        foreach ($procurements as $procurement) {
            $value = $procurement->getAttribute($attribute);

            if ($value === null) {
                continue;
            }

            $key = CarbonImmutable::parse($value)->format('Y-m');

            if (isset($buckets[$key])) {
                $buckets[$key]['total']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * Average checklist completion of a stage across a set of procurements.
     *
     * @param  Collection<int, Procurement>  $procurements
     */
    public function averageProgress(Collection $procurements, ProcurementStage $stage): int
    {
        if ($procurements->isEmpty()) {
            return 0;
        }

        $total = $procurements->sum(
            fn (Procurement $procurement): int => $procurement->checklistProgress($stage)['percentage'],
        );

        return (int) round($total / $procurements->count());
    }

    /**
     * Average number of days elapsed since a date attribute.
     *
     * @param  Collection<int, Procurement>  $procurements
     */
    public function averageAgeInDays(Collection $procurements, string $attribute): int
    {
        $values = $procurements
            ->map(fn (Procurement $procurement) => $procurement->getAttribute($attribute))
            ->filter()
            ->map(fn ($value): int => (int) CarbonImmutable::parse($value)->diffInDays(CarbonImmutable::now()));

        if ($values->isEmpty()) {
            return 0;
        }

        return (int) round($values->avg() ?? 0);
    }
}
