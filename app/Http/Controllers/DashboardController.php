<?php

namespace App\Http\Controllers;

use App\Enums\PlanningApprovalState;
use App\Enums\StatusCategory;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Services\DashboardAnalyticsService;
use App\Support\DashboardFilters;
use App\Support\DashboardStage;
use App\Support\DashboardTasks;
use App\Support\FilterPeriod;
use App\Support\RoleDashboard;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the procurement dashboard for the current user.
     */
    public function index(Request $request, DashboardAnalyticsService $analytics): Response
    {
        $user = $request->user();

        $base = fn () => Procurement::query()->visibleTo($user);

        // Each role gets its own dashboard; only those overseeing every
        // procurement get the management figures and comparisons.
        $profile = RoleDashboard::profile($user);

        // The analytics read the jobs the user may see (a proposer: the
        // ones they proposed), narrowed by the dashboard filters.
        $scope = $profile === 'pengusul'
            ? fn () => Procurement::query()->where('procurements.created_by', $user->id)
            : $base;
        $filters = DashboardFilters::fromRequest($request);
        $card = $request->string('card')->trim()->value() ?: null;
        $tableScope = $profile !== 'manajemen' && $card !== null
            ? fn () => RoleDashboard::applyCardFilter($scope(), $user, $profile, $card)
            : $scope;

        $shared = [
            'profile' => $profile,
            'summary' => fn () => $this->summary($base),
            'tasks' => fn () => DashboardTasks::for($user),
            'account' => [
                'role' => $user->role->label(),
                'division' => $user->workDirector?->name,
            ],
            'filters' => $filters->toArray(),
            'filterOptions' => fn () => $this->filterOptions($profile, $scope),
            'analytics' => fn () => $analytics->build($scope, $filters, $profile),
            'table' => fn () => $analytics->table($tableScope, $filters, $request),
            'tableQuery' => [
                'search' => (string) $request->query('search', ''),
                'sort' => (string) $request->query('sort', 'created_at'),
                'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
            ],
        ];

        if ($profile !== 'manajemen') {
            return Inertia::render('dashboard', [
                ...$shared,
                'role' => fn () => RoleDashboard::for($user, $profile, $card),
            ]);
        }

        return Inertia::render('dashboard', [
            ...$shared,
            'stages' => fn () => RoleDashboard::stages($base()),
        ]);
    }

    /**
     * The headline counts of every job the user may see, unfiltered.
     *
     * @param  Closure(): Builder<Procurement>  $base
     * @return array<string, int|float>
     */
    protected function summary(Closure $base): array
    {
        $byCategory = $base()
            ->toBase()
            ->join('progress_statuses', 'progress_statuses.id', '=', 'procurements.progress_status_id')
            ->selectRaw('progress_statuses.category as category, count(*) as total')
            ->groupBy('progress_statuses.category')
            ->pluck('total', 'category');

        return [
            'total' => $base()->count(),
            'running' => (int) ($byCategory[StatusCategory::Berjalan->value] ?? 0),
            'completed' => (int) ($byCategory[StatusCategory::Selesai->value] ?? 0),
            'pending' => (int) ($byCategory[StatusCategory::Pending->value] ?? 0),
            'cancelled' => (int) ($byCategory[StatusCategory::Batal->value] ?? 0),
            'awaitingApproval' => $base()
                ->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                ->count(),
            // A rejected planning stage is work waiting on its PIC, so it
            // needs to be as visible as work waiting on an approver.
            'needsRevision' => $base()
                ->where('planning_approval_state', PlanningApprovalState::Ditolak->value)
                ->count(),
            'totalHpe' => (float) $base()->sum('hpe_value'),
        ];
    }

    /**
     * The choices of the dashboard filters.
     *
     * Divisions and PICs are only offered to those comparing them, and the
     * PICs listed are those holding a job of the scope.
     *
     * @param  Closure(): Builder<Procurement>  $scope
     * @return array<string, mixed>
     */
    protected function filterOptions(string $profile, Closure $scope): array
    {
        $option = fn (WorkDirector|TargetUnit|ProcurementMethod|User $model): array => [
            'id' => $model->id,
            'name' => $model->name,
        ];
        $isManagement = $profile === 'manajemen';

        $picIds = $isManagement
            ? $scope()->toBase()->whereNotNull('planner_id')->distinct()->pluck('planner_id')
                ->merge($scope()->toBase()->whereNotNull('executor_id')->distinct()->pluck('executor_id'))
                ->unique()
            : collect();

        return [
            'periods' => collect(DashboardFilters::PERIODS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values(),
            'years' => FilterPeriod::years('procurements'),
            'stages' => collect(DashboardStage::LABELS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values(),
            'workDirectors' => $isManagement
                ? WorkDirector::query()->orderBy('name')->get(['id', 'name'])->map($option)
                : null,
            'targetUnits' => TargetUnit::query()->active()->ordered()->get(['id', 'name'])->map($option),
            'methods' => ProcurementMethod::query()->orderBy('name')->get(['id', 'name'])->map($option),
            'pics' => $isManagement
                ? User::query()->whereIn('id', $picIds)->orderBy('name')->get(['id', 'name'])->map($option)
                : null,
        ];
    }
}
