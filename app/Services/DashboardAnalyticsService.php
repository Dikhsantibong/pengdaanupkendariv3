<?php

namespace App\Services;

use App\Enums\PaymentApprovalState;
use App\Enums\PlanningApprovalState;
use App\Enums\ProposalState;
use App\Enums\StatusCategory;
use App\Enums\SystemStatus;
use App\Models\Procurement;
use App\Models\ProcurementActivity;
use App\Support\DashboardFilters;
use App\Support\DashboardStage;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use stdClass;

/**
 * The figures and charts of the dashboard, drawn from the jobs a user may
 * see and narrowed by the dashboard filters.
 *
 * Every section reads the same filtered set of jobs. A section that fails
 * is reported and comes back as null, so one broken chart never takes the
 * whole dashboard down.
 */
class DashboardAnalyticsService
{
    /**
     * The sections each dashboard profile shows.
     *
     * Management follows every job; a role following its own share skips
     * the comparisons between divisions and people.
     *
     * @var array<string, list<string>>
     */
    public const PROFILE_SECTIONS = [
        'manajemen' => ['kpis', 'trend', 'stages', 'budget', 'methods', 'progress', 'directorates', 'units', 'budgetSources', 'pics', 'statuses', 'upcoming', 'activities'],
        'direksi' => ['trend', 'stages', 'budget', 'methods', 'progress', 'units', 'budgetSources', 'statuses', 'upcoming', 'activities'],
        'keuangan' => ['trend', 'stages', 'budget', 'methods', 'progress', 'budgetSources', 'activities'],
        'perencana' => ['trend', 'stages', 'methods', 'progress', 'units', 'upcoming', 'activities'],
        'pelaksana' => ['trend', 'stages', 'methods', 'progress', 'units', 'upcoming', 'activities'],
        'pengusul' => ['trend', 'stages', 'progress', 'activities'],
        'umum' => ['trend', 'stages', 'progress', 'activities'],
    ];

    /**
     * Rows per page of the recent jobs table.
     */
    private const TABLE_SIZE = 10;

    /**
     * The columns the recent jobs table can be sorted by.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'created_at' => 'created_at',
        'name' => 'name',
        'number' => 'number',
        'hpe_value' => 'hpe_value',
    ];

    /**
     * The scope of jobs, before any filter.
     *
     * @var Closure(): Builder<Procurement>
     */
    private Closure $scope;

    private DashboardFilters $filters;

    /**
     * Build the analytics of a set of jobs.
     *
     * @param  Closure(): Builder<Procurement>  $scope
     * @return array<string, mixed>
     */
    public function build(Closure $scope, DashboardFilters $filters, string $profile): array
    {
        $this->scope = $scope;
        $this->filters = $filters;

        $sections = self::PROFILE_SECTIONS[$profile] ?? self::PROFILE_SECTIONS['umum'];
        $analytics = ['sections' => $sections];

        foreach ($sections as $section) {
            $analytics[$section] = rescue(fn () => $this->{$section}(), null);
        }

        return $analytics;
    }

    /**
     * The jobs of the scope, narrowed by the filters.
     *
     * @return Builder<Procurement>
     */
    protected function base(): Builder
    {
        return $this->filters->apply(($this->scope)());
    }

    /**
     * The six headline figures.
     *
     * @return array<string, mixed>
     */
    protected function kpis(): array
    {
        $categories = $this->categoryCounts($this->base());

        $awaiting = [
            'usulan' => $this->base()
                ->whereIn('proposal_state', [ProposalState::MenungguPersetujuanTl->value, ProposalState::MenungguPersetujuan->value])
                ->whereNotNull('proposal_completed_at')
                ->count(),
            'perencanaan' => $this->base()->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)->count(),
            'pelaksanaan' => $this->base()->where('execution_approval_state', PlanningApprovalState::MenungguPersetujuan->value)->count(),
            'penyerahan' => $this->base()->where('handover_approval_state', PlanningApprovalState::MenungguPersetujuan->value)->count(),
            'pembayaran' => $this->base()->whereIn('payment_approval_state', [PaymentApprovalState::MenungguTl->value, PaymentApprovalState::MenungguAsman->value])->count(),
        ];

        $previous = $this->filters->previousRange();
        $previousBase = fn (): Builder => $this->filters->apply(($this->scope)(), $previous);

        return [
            'total' => $this->base()->count(),
            'running' => $categories[StatusCategory::Berjalan->value],
            'pending' => $categories[StatusCategory::Pending->value],
            'completed' => $categories[StatusCategory::Selesai->value],
            'cancelled' => $categories[StatusCategory::Batal->value],
            'awaiting' => array_sum($awaiting),
            'awaitingBreakdown' => $awaiting,
            'totalHpe' => (float) $this->base()->sum('hpe_value'),
            'runningHpe' => (float) $this->base()->whereNull('completed_at')
                ->whereHas('progressStatus', fn (Builder $status) => $status->where('category', '!=', StatusCategory::Batal->value))
                ->sum('hpe_value'),
            // Only a dated period has an earlier one to compare with.
            'previous' => $previous === null ? null : [
                'total' => $previousBase()->count(),
                'totalHpe' => (float) $previousBase()->sum('hpe_value'),
                'completed' => $this->categoryCounts($previousBase())[StatusCategory::Selesai->value],
            ],
        ];
    }

    /**
     * Jobs registered each month, by where their status stands now.
     *
     * @return array{months: list<array{key: string, label: string}>, total: list<int>, running: list<int>, completed: list<int>, cancelled: list<int>}
     */
    protected function trend(): array
    {
        $months = $this->filters->months();
        $rows = $this->monthlyRows($months, ['progress_statuses.category as category']);

        $series = ['total' => [], 'running' => [], 'completed' => [], 'cancelled' => []];

        foreach ($months as $month) {
            $inMonth = $rows->get($month->format('Y-m'), collect());

            $series['total'][] = $inMonth->count();
            $series['running'][] = $inMonth->where('category', StatusCategory::Berjalan->value)->count();
            $series['completed'][] = $inMonth->where('category', StatusCategory::Selesai->value)->count();
            $series['cancelled'][] = $inMonth->where('category', StatusCategory::Batal->value)->count();
        }

        return [
            'months' => $this->monthLabels($months),
            'total' => $series['total'],
            'running' => $series['running'],
            'completed' => $series['completed'],
            'cancelled' => $series['cancelled'],
        ];
    }

    /**
     * The value of the jobs registered each month: all of them, those still
     * running and those completed. HPE is the engineer's estimate, not what
     * was paid.
     *
     * @return array<string, mixed>
     */
    protected function budget(): array
    {
        $months = $this->filters->months();
        $rows = $this->monthlyRows($months, ['progress_statuses.category as category', 'procurements.hpe_value as hpe']);

        $series = ['hpe' => [], 'running' => [], 'completed' => []];

        foreach ($months as $month) {
            $inMonth = $rows->get($month->format('Y-m'), collect());

            $series['hpe'][] = (float) $inMonth->sum('hpe');
            $series['running'][] = (float) $inMonth
                ->whereNotIn('category', [StatusCategory::Selesai->value, StatusCategory::Batal->value])
                ->sum('hpe');
            $series['completed'][] = (float) $inMonth->where('category', StatusCategory::Selesai->value)->sum('hpe');
        }

        return [
            'months' => $this->monthLabels($months),
            ...$series,
            // There is no budget ceiling in the data yet.
            'target' => null,
        ];
    }

    /**
     * How many jobs are at each stage.
     *
     * @return list<array{key: string, label: string, total: int}>
     */
    protected function stages(): array
    {
        $counts = DashboardStage::counts($this->base());

        return array_map(
            fn (string $key): array => ['key' => $key, 'label' => DashboardStage::LABELS[$key], 'total' => $counts[$key]],
            array_keys(DashboardStage::LABELS),
        );
    }

    /**
     * How far the jobs have come on the whole.
     *
     * Each running job scores the share of the journey it has covered
     * (usulan 0, perencanaan ¼, pelaksanaan ½, penyerahan & pembayaran ¾,
     * selesai 1); cancelled jobs are left out.
     *
     * @return array<string, int|float>
     */
    protected function progress(): array
    {
        $stages = DashboardStage::counts($this->base());
        $weights = ['usulan' => 0, 'perencanaan' => 1, 'pelaksanaan' => 2, 'keuangan' => 3, 'selesai' => 4];

        $active = array_sum(array_intersect_key($stages, $weights));
        $score = array_sum(array_map(fn (string $key): int => $stages[$key] * $weights[$key], array_keys($weights)));

        $bySystemStatus = fn (SystemStatus $status): int => $this->base()
            ->whereHas('progressStatus', fn (Builder $query) => $query->where('system_key', $status->value))
            ->count();

        return [
            'percent' => $active === 0 ? 0 : round($score / ($active * 4) * 100, 1),
            'completion' => $active === 0 ? 0 : round($stages['selesai'] / $active * 100, 1),
            'active' => $active,
            'completed' => $stages['selesai'],
            'inExecution' => $stages['pelaksanaan'],
            'purchaseOrder' => $bySystemStatus(SystemStatus::PenyusunanSuratPesanan),
            'contracted' => $bySystemStatus(SystemStatus::Terkontrak),
        ];
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    protected function methods(): array
    {
        return $this->groupedCounts('procurement_methods', 'procurement_method_id');
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    protected function directorates(): array
    {
        return $this->groupedCounts('work_directors', 'work_director_id');
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    protected function budgetSources(): array
    {
        return $this->groupedCounts('budget_sources', 'budget_source_id');
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    protected function statuses(): array
    {
        return $this->groupedCounts('progress_statuses', 'progress_status_id');
    }

    /**
     * Jobs per target unit; a job serving several units counts for each.
     *
     * @return array<int, array{label: string, total: int}>
     */
    protected function units(): array
    {
        return $this->base()
            ->toBase()
            ->join('procurement_target_unit', 'procurement_target_unit.procurement_id', '=', 'procurements.id')
            ->join('target_units', 'target_units.id', '=', 'procurement_target_unit.target_unit_id')
            ->selectRaw('target_units.name as label, count(*) as total')
            ->groupBy('target_units.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => ['label' => (string) $row->label, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    /**
     * How many jobs each PIC holds, as planner and as executor. A count of
     * assignments, not a measure of anyone's performance.
     *
     * @return array<int, array{label: string, planner: int, executor: int}>
     */
    protected function pics(): array
    {
        $planners = collect($this->groupedCounts('users', 'planner_id'))->pluck('total', 'label');
        $executors = collect($this->groupedCounts('users', 'executor_id'))->pluck('total', 'label');

        return $planners->keys()->merge($executors->keys())->unique()
            ->map(fn (string $name): array => [
                'label' => $name,
                'planner' => (int) ($planners[$name] ?? 0),
                'executor' => (int) ($executors[$name] ?? 0),
            ])
            ->sortByDesc(fn (array $row): int => $row['planner'] + $row['executor'])
            ->values()
            ->all();
    }

    /**
     * The running jobs closest to their target date.
     *
     * @return array<int, array{id: int, number: string, name: string, date: string|null}>
     */
    protected function upcoming(): array
    {
        return $this->base()
            ->whereNotNull('target_completion_date')
            ->whereNull('completed_at')
            ->orderBy('target_completion_date')
            ->limit(6)
            ->get(['id', 'number', 'name', 'target_completion_date'])
            ->map(fn (Procurement $procurement): array => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'name' => $procurement->name,
                'date' => $procurement->target_completion_date?->toDateString(),
            ])
            ->all();
    }

    /**
     * The latest things that happened to the jobs.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function activities(): array
    {
        return ProcurementActivity::query()
            ->whereIn('procurement_id', $this->base()->select('procurements.id'))
            ->with(['user:id,name', 'procurement:id,name,number'])
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (ProcurementActivity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->type->value,
                'label' => $activity->type->label(),
                'outcome' => $activity->type->approvalOutcome(),
                'description' => $activity->description,
                'user' => $activity->user?->name,
                'procurement' => $activity->procurement === null ? null : [
                    'id' => $activity->procurement->id,
                    'name' => $activity->procurement->name,
                    'number' => $activity->procurement->number,
                ],
                'at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The recent jobs table: searched, sorted and paged.
     *
     * @param  Closure(): Builder<Procurement>  $scope
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function table(Closure $scope, DashboardFilters $filters, Request $request): LengthAwarePaginator
    {
        $sort = self::SORTABLE[(string) $request->query('sort')] ?? 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->query('search', ''));

        return $filters->apply($scope())
            ->with(['targetUnits:id,name', 'procurementMethod:id,name', 'planner:id,name', 'executor:id,name', 'progressStatus'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(self::TABLE_SIZE, ['*'], 'table_page')
            ->withQueryString()
            ->through(fn (Procurement $procurement): array => $this->tableRow($procurement));
    }

    /**
     * One row of the recent jobs table.
     *
     * @return array<string, mixed>
     */
    protected function tableRow(Procurement $procurement): array
    {
        return [
            'id' => $procurement->id,
            'number' => $procurement->number,
            'name' => $procurement->name,
            'units' => $procurement->targetUnits->pluck('name')->implode(', '),
            'method' => $procurement->procurementMethod?->name,
            'planner' => $procurement->planner?->name,
            'executor' => $procurement->executor?->name,
            'hpe_value' => (float) $procurement->hpe_value,
            'stage' => $procurement->currentStage(),
            'status' => [
                'name' => $procurement->progressStatus->name,
                'category' => $procurement->progressStatus->category->value,
            ],
            'created_at' => $procurement->created_at?->toDateString(),
        ];
    }

    /**
     * How many jobs carry each status category, every category listed.
     *
     * @param  Builder<Procurement>  $query
     * @return array<string, int>
     */
    protected function categoryCounts(Builder $query): array
    {
        $counts = $query->toBase()
            ->join('progress_statuses', 'progress_statuses.id', '=', 'procurements.progress_status_id')
            ->selectRaw('progress_statuses.category as category, count(*) as total')
            ->groupBy('progress_statuses.category')
            ->pluck('total', 'category');

        $categories = [];

        foreach (StatusCategory::cases() as $category) {
            $categories[$category->value] = (int) ($counts[$category->value] ?? 0);
        }

        return $categories;
    }

    /**
     * Count the jobs grouped by a master data name, highest first.
     *
     * @param  literal-string  $table
     * @param  literal-string  $foreignKey
     * @return array<int, array{label: string, total: int}>
     */
    protected function groupedCounts(string $table, string $foreignKey): array
    {
        return $this->base()
            ->toBase()
            ->join($table, "{$table}.id", '=', "procurements.{$foreignKey}")
            ->selectRaw("{$table}.name as label, count(*) as total")
            ->groupBy("{$table}.name")
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => ['label' => (string) $row->label, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    /**
     * The filtered jobs registered within the months, grouped by month.
     *
     * Read row by row and grouped here, so the same code runs on every
     * database.
     *
     * @param  list<CarbonImmutable>  $months
     * @param  list<string>  $columns
     * @return Collection<array-key, Collection<int, stdClass>>
     */
    protected function monthlyRows(array $months, array $columns): Collection
    {
        if ($months === []) {
            return collect();
        }

        return $this->base()
            ->toBase()
            ->join('progress_statuses', 'progress_statuses.id', '=', 'procurements.progress_status_id')
            ->whereBetween('procurements.created_at', [$months[0], end($months)->endOfMonth()])
            ->get(['procurements.created_at as created_at', ...$columns])
            ->groupBy(fn (object $row): string => CarbonImmutable::parse((string) $row->created_at)->format('Y-m'));
    }

    /**
     * @param  list<CarbonImmutable>  $months
     * @return list<array{key: string, label: string}>
     */
    protected function monthLabels(array $months): array
    {
        return array_map(fn (CarbonImmutable $month): array => [
            'key' => $month->format('Y-m'),
            'label' => $month->translatedFormat('M Y'),
        ], $months);
    }
}
