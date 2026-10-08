<?php

namespace Tests\Feature\Procurements;

use App\Enums\StatusCategory;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Models\User;
use App\Support\MasterDataOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PicWorkloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_options_count_only_unfinished_work(): void
    {
        $planner = User::factory()->planner()->create();

        // Two still in planning, one already handed over to execution.
        Procurement::factory()->count(2)->plannedBy($planner)->create();
        Procurement::factory()->plannedBy($planner)->planningApproved()->create();

        // Finished, cancelled and deleted work is no longer a load.
        Procurement::factory()->plannedBy($planner)->create(['completed_at' => now()]);
        Procurement::factory()->plannedBy($planner)->create([
            'progress_status_id' => ProgressStatus::factory()->category(StatusCategory::Batal),
        ]);
        Procurement::factory()->plannedBy($planner)->create()->delete();

        $option = collect(MasterDataOptions::planners())->firstWhere('value', $planner->id);

        $this->assertSame(['planning' => 2, 'execution' => 0, 'active' => 3], $option['workload']);
    }

    public function test_executor_options_count_execution_and_all_active_roles(): void
    {
        $executor = User::factory()->executor()->create();

        Procurement::factory()->count(2)->executedBy($executor)->planningApproved()->create();
        // Assigned but planning not approved yet: active, not in execution.
        Procurement::factory()->executedBy($executor)->create();
        // Holding both roles on one procurement counts it once.
        Procurement::factory()->plannedBy($executor)->executedBy($executor)->planningApproved()->create();

        $option = collect(MasterDataOptions::executors())->firstWhere('value', $executor->id);

        $this->assertSame(['planning' => 0, 'execution' => 3, 'active' => 4], $option['workload']);
    }

    public function test_the_assignment_screen_shows_each_pic_workload(): void
    {
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $planner = User::factory()->planner()->create();
        Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs($teamLeader)
            ->get(route('pic-assignments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('options.planners.0.value', $planner->id)
                ->where('options.planners.0.workload.planning', 1)
                ->where('options.planners.0.workload.active', 1));
    }
}
