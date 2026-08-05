<?php

namespace Tests\Feature;

use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_boards_are_reachable_without_signing_in(): void
    {
        $this->get(route('public-monitoring.planning'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public-monitoring/planning')
                ->has('summary')
                ->has('sCurve')
                ->has('generatedAt'),
            );

        $this->get(route('public-monitoring.execution'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public-monitoring/execution')
                ->has('summary')
                ->has('sCurve'),
            );
    }

    public function test_the_bare_url_redirects_to_the_planning_board(): void
    {
        $this->get('/monitoring-publik')
            ->assertRedirect('/monitoring-publik/perencanaan');
    }

    public function test_each_board_only_lists_procurements_of_its_own_stage(): void
    {
        $running = ProgressStatus::factory()->category(StatusCategory::Berjalan)->create();

        Procurement::factory()->count(2)->create(['progress_status_id' => $running->id]);
        Procurement::factory()->planningSubmitted()->create(['progress_status_id' => $running->id]);
        Procurement::factory()->planningApproved()->count(2)->create(['progress_status_id' => $running->id]);

        $this->get(route('public-monitoring.planning'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 3)
                ->where('summary.menungguPersetujuan', 1)
                ->where('summary.disetujui', 2)
                ->has('rows', 3),
            );

        $this->get(route('public-monitoring.execution'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 2)
                ->has('rows', 2),
            );
    }

    public function test_the_planning_board_reports_checklist_completion(): void
    {
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(4)->create();

        $procurement = Procurement::factory()->create();
        app(ProcurementService::class)->syncChecklists($procurement);
        $procurement->checklists()->limit(2)->update(['is_completed' => true]);

        $this->get(route('public-monitoring.planning'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.percentage', 50)
                ->has('checklistBreakdown', 4)
                ->where('summary.rataRataProgres', 50),
            );
    }

    public function test_the_s_curve_plots_plan_against_actual_progress(): void
    {
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(4)->create();

        $procurement = Procurement::factory()->create([
            'created_at' => now()->subDays(60),
            'target_completion_date' => now()->addDays(30),
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        $procurement->checklists()->limit(2)->update([
            'is_completed' => true,
            'completed_at' => now()->subDays(20),
        ]);

        $this->get(route('public-monitoring.planning'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sCurve.includedCount', 1)
                ->where('sCurve.totalItems', 4)
                ->where('sCurve.currentActual', 50)
                ->has('sCurve.points', 18),
            );
    }

    public function test_procurements_without_a_target_date_are_reported_as_excluded_from_the_curve(): void
    {
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(2)->create();

        $procurement = Procurement::factory()->create(['target_completion_date' => null]);
        app(ProcurementService::class)->syncChecklists($procurement);

        $this->get(route('public-monitoring.planning'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sCurve.includedCount', 0)
                ->where('sCurve.excludedCount', 1)
                ->has('sCurve.points', 0),
            );
    }

    public function test_the_execution_board_flags_overdue_procurements(): void
    {
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(2)->create();

        $late = Procurement::factory()->planningApproved()->create([
            'target_completion_date' => now()->subDays(10),
        ]);
        Procurement::factory()->planningApproved()->create([
            'target_completion_date' => now()->addDays(5),
        ]);
        Procurement::factory()->planningApproved()->create([
            'target_completion_date' => now()->addDays(90),
        ]);

        $this->get(route('public-monitoring.execution'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.terlambat', 1)
                ->where('summary.mendekatiTarget', 1)
                ->where('rows.0.number', $late->number)
                ->where('rows.0.remaining_days', -10),
            );
    }

    public function test_the_boards_never_expose_budget_or_personnel_data(): void
    {
        $planner = User::factory()->planner()->create(['name' => 'Nama PIC Rahasia']);
        Procurement::factory()->plannedBy($planner)->create(['hpe_value' => 987_654_321]);
        Procurement::factory()->plannedBy($planner)->planningApproved()->create([
            'hpe_value' => 987_654_321,
        ]);

        foreach (['planning', 'execution'] as $board) {
            $response = $this->get(route("public-monitoring.{$board}"))->assertOk();

            $response->assertDontSee('987654321');
            $response->assertDontSee('987.654.321');
            $response->assertDontSee('Nama PIC Rahasia');
        }
    }

    public function test_cancelled_and_completed_procurements_leave_the_running_boards(): void
    {
        $cancelled = ProgressStatus::factory()->category(StatusCategory::Batal)->create();
        $running = ProgressStatus::factory()->category(StatusCategory::Berjalan)->create();

        Procurement::factory()->create(['progress_status_id' => $cancelled->id]);
        Procurement::factory()->planningApproved()->create([
            'progress_status_id' => $running->id,
            'completed_at' => now(),
        ]);
        Procurement::factory()->planningApproved()->create(['progress_status_id' => $running->id]);

        $this->get(route('public-monitoring.planning'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));

        $this->get(route('public-monitoring.execution'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->has('completed', 1),
            );
    }

    public function test_the_login_screen_carries_the_rotating_showcase(): void
    {
        TargetUnit::factory()->count(3)->create();
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(5)->create();
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(4)->create();

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/login')
                ->where('showcase.unitCount', 3)
                ->has('showcase.planningSteps', 5)
                ->has('showcase.executionSteps', 4)
                ->has('showcase.units', 3)
                ->has('showcase.summary')
                ->has('showcase.statuses'),
            );
    }
}
