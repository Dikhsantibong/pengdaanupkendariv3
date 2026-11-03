<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\PlanningApprovalState;
use App\Enums\ProposalState;
use App\Enums\StatusCategory;
use App\Models\Procurement;
use App\Models\ProcurementActivity;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The dashboard analytics: figures and charts drawn from the jobs a user
 * may see, all narrowed by the same filters.
 */
class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_six_headline_figures_count_the_jobs(): void
    {
        $running = ProgressStatus::factory()->category(StatusCategory::Berjalan)->create();
        $done = ProgressStatus::factory()->category(StatusCategory::Selesai)->create();
        $cancelled = ProgressStatus::factory()->category(StatusCategory::Batal)->create();

        Procurement::factory()->count(2)->create(['progress_status_id' => $running->id, 'hpe_value' => 100_000_000]);
        Procurement::factory()->create(['progress_status_id' => $done->id, 'hpe_value' => 24_000_000, 'completed_at' => now()]);
        Procurement::factory()->create(['progress_status_id' => $cancelled->id, 'hpe_value' => 0]);
        Procurement::factory()->create([
            'progress_status_id' => $running->id,
            'hpe_value' => 0,
            'planning_approval_state' => PlanningApprovalState::MenungguPersetujuan,
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total', 5)
                ->where('analytics.kpis.running', 3)
                ->where('analytics.kpis.completed', 1)
                ->where('analytics.kpis.cancelled', 1)
                ->where('analytics.kpis.awaiting', 1)
                ->where('analytics.kpis.awaitingBreakdown.perencanaan', 1)
                ->where('analytics.kpis.totalHpe', fn ($value) => (float) $value === 224_000_000.0)
                // Every date has no earlier period to compare with.
                ->where('analytics.kpis.previous', null)
                ->where('analytics.stages', fn ($stages) => collect($stages)->pluck('total', 'key')->all() === [
                    'usulan' => 0,
                    'perencanaan' => 3,
                    'pelaksanaan' => 0,
                    'keuangan' => 0,
                    'selesai' => 1,
                    'batal' => 1,
                ])
                ->where('analytics.progress.completed', 1)
                ->where('analytics.progress.active', 4)
                ->has('analytics.trend.months', 12)
                ->where('analytics.budget.target', null)
                ->where('table.total', 5));
    }

    public function test_every_figure_follows_the_filters(): void
    {
        $division = WorkDirector::factory()->create();
        $unit = TargetUnit::factory()->create();
        $planner = User::factory()->planner()->create();

        $match = Procurement::factory()->plannedBy($planner)->create([
            'work_director_id' => $division->id,
            'created_at' => now()->subMonth(),
        ]);
        $match->targetUnits()->attach($unit);

        // Wrong division, too old, or with another PIC: all left out.
        Procurement::factory()->plannedBy($planner)->create(['created_at' => now()->subMonth()]);
        Procurement::factory()->create(['work_director_id' => $division->id, 'created_at' => now()->subYears(2)]);
        Procurement::factory()->create(['work_director_id' => $division->id, 'created_at' => now()->subMonth()]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('dashboard', [
                'period' => '6m',
                'work_director_id' => $division->id,
                'pic_id' => $planner->id,
                'target_unit_id' => $unit->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', '6m')
                ->where('analytics.kpis.total', 1)
                ->where('analytics.kpis.previous.total', 0)
                ->where('analytics.directorates', [['label' => $division->name, 'total' => 1]])
                ->where('analytics.pics', [['label' => $planner->name, 'planner' => 1, 'executor' => 0]])
                ->has('analytics.trend.months', 6)
                ->where('analytics.trend.total', fn ($totals) => collect($totals)->sum() === 1)
                ->where('table.total', 1)
                ->where('table.data.0.id', $match->id));
    }

    public function test_the_stage_filter_and_a_custom_range_narrow_the_jobs(): void
    {
        Procurement::factory()->planningApproved()->create(['created_at' => '2026-03-10']);
        Procurement::factory()->create(['created_at' => '2026-03-12']);
        Procurement::factory()->planningApproved()->create(['created_at' => '2025-03-10']);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('dashboard', [
                'period' => 'custom',
                'from' => '2026-03-31',
                'to' => '2026-01-01',
                'stage' => 'pelaksanaan',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                // A reversed range is read the right way round.
                ->where('filters.from', '2026-01-01')
                ->where('filters.to', '2026-03-31')
                ->where('filters.stage', 'pelaksanaan')
                ->where('analytics.kpis.total', 1)
                ->has('analytics.trend.months', 3));
    }

    public function test_invalid_filters_are_ignored(): void
    {
        Procurement::factory()->count(2)->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('dashboard', [
                'period' => 'forever',
                'stage' => 'unknown',
                'pic_id' => 'abc',
                'sort' => 'password',
                'from' => 'yesterday',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'all')
                ->where('filters.stage', null)
                ->where('filters.pic_id', null)
                ->where('analytics.kpis.total', 2));
    }

    public function test_the_recent_table_searches_sorts_and_pages(): void
    {
        Procurement::factory()->create(['name' => 'Pengadaan Trafo', 'hpe_value' => 300]);
        Procurement::factory()->create(['name' => 'Pengadaan Trafo Cadangan', 'hpe_value' => 100]);
        Procurement::factory()->create(['name' => 'Jasa Kebersihan', 'hpe_value' => 200]);
        Procurement::factory()->count(10)->create(['name' => 'Lainnya']);

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('dashboard', ['search' => 'trafo', 'sort' => 'hpe_value', 'direction' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.total', 2)
                ->where('table.data.0.name', 'Pengadaan Trafo Cadangan')
                ->where('table.data.1.name', 'Pengadaan Trafo')
                ->where('tableQuery.search', 'trafo')
                // Searching the table leaves the charts as they are.
                ->where('analytics.kpis.total', 13));

        $this->actingAs($admin)
            ->get(route('dashboard', ['table_page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.current_page', 2)
                ->has('table.data', 3));
    }

    public function test_the_activity_timeline_shows_the_visible_jobs_only(): void
    {
        $proposer = User::factory()->proposer(WorkDirector::factory()->create())->create();
        $mine = Procurement::factory()->create(['created_by' => $proposer->id, 'proposal_state' => ProposalState::MenungguPersetujuanTl]);
        $other = Procurement::factory()->create();

        ProcurementActivity::query()->create([
            'procurement_id' => $mine->id,
            'user_id' => $proposer->id,
            'type' => ActivityType::UsulanDiajukan,
            'description' => 'Usulan diajukan.',
        ]);
        ProcurementActivity::query()->create([
            'procurement_id' => $other->id,
            'type' => ActivityType::Dibuat,
            'description' => 'Pengadaan lain dibuat.',
        ]);

        $this->actingAs($proposer)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', 'pengusul')
                ->has('analytics.activities', 1)
                ->where('analytics.activities.0.label', 'Usulan Diajukan')
                ->where('analytics.activities.0.user', $proposer->name)
                ->where('table.total', 1)
                ->where('table.data.0.id', $mine->id));
    }

    public function test_each_role_gets_the_sections_it_works_with(): void
    {
        $this->actingAs(User::factory()->proposer(WorkDirector::factory()->create())->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.sections', ['trend', 'stages', 'progress', 'activities'])
                ->missing('analytics.kpis')
                ->missing('analytics.directorates')
                ->missing('analytics.pics')
                ->where('filterOptions.workDirectors', null)
                ->where('filterOptions.pics', null)
                ->has('role.cards'));

        $this->actingAs(User::factory()->planner()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', 'perencana')
                ->missing('analytics.pics')
                ->has('analytics.units'));
    }

    public function test_the_dashboard_no_longer_lists_the_notifications(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('notifications')
                ->has('tasks'));
    }
}
