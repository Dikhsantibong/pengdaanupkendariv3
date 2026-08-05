<?php

namespace App\Http\Controllers;

use App\Enums\PlanningApprovalState;
use App\Enums\StatusCategory;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the procurement dashboard for the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $base = fn () => Procurement::query()->visibleTo($user);

        $byCategory = $base()
            ->toBase()
            ->join('progress_statuses', 'progress_statuses.id', '=', 'procurements.progress_status_id')
            ->selectRaw('progress_statuses.category as category, count(*) as total')
            ->groupBy('progress_statuses.category')
            ->pluck('total', 'category');

        return Inertia::render('dashboard', [
            'summary' => [
                'total' => $base()->count(),
                'running' => (int) ($byCategory[StatusCategory::Berjalan->value] ?? 0),
                'completed' => (int) ($byCategory[StatusCategory::Selesai->value] ?? 0),
                'pending' => (int) ($byCategory[StatusCategory::Pending->value] ?? 0),
                'cancelled' => (int) ($byCategory[StatusCategory::Batal->value] ?? 0),
                'awaitingApproval' => $base()
                    ->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                    ->count(),
                'totalHpe' => (float) $base()->sum('hpe_value'),
            ],
            'byStatus' => $this->groupedCounts($base(), 'progress_statuses', 'progress_status_id', 'name'),
            'byWorkDirector' => $this->groupedCounts($base(), 'work_directors', 'work_director_id', 'name'),
            'byTargetUnit' => $this->groupedCounts($base(), 'target_units', 'target_unit_id', 'name'),
            'byProcurementMethod' => $this->groupedCounts($base(), 'procurement_methods', 'procurement_method_id', 'name'),
            'byBudgetSource' => $this->groupedCounts($base(), 'budget_sources', 'budget_source_id', 'name'),
            'byPlanner' => $this->groupedCounts($base(), 'users', 'planner_id', 'name'),
            'byExecutor' => $this->groupedCounts($base(), 'users', 'executor_id', 'name'),
            'recent' => ProcurementResource::collection(
                $base()
                    ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor'])
                    ->latest('created_at')
                    ->limit(8)
                    ->get(),
            )->resolve(),
            'upcoming' => ProcurementResource::collection(
                $base()
                    ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor'])
                    ->whereNotNull('target_completion_date')
                    ->whereNull('completed_at')
                    ->orderBy('target_completion_date')
                    ->limit(6)
                    ->get(),
            )->resolve(),
            'statusOrder' => ProgressStatus::query()->active()->ordered()->pluck('name'),
        ]);
    }

    /**
     * Count the visible procurements grouped by a related master data label.
     *
     * @param  Builder<Procurement>  $query
     * @param  literal-string  $table
     * @param  literal-string  $foreignKey
     * @param  literal-string  $labelColumn
     * @return array<int, array{label: string, total: int}>
     */
    protected function groupedCounts(Builder $query, string $table, string $foreignKey, string $labelColumn): array
    {
        return $query
            ->toBase()
            ->join($table, "{$table}.id", '=', "procurements.{$foreignKey}")
            ->selectRaw("{$table}.{$labelColumn} as label, count(*) as total")
            ->groupBy("{$table}.{$labelColumn}")
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
