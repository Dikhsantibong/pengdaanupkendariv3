<?php

namespace Tests\Feature;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reviewer_sees_the_submissions_waiting_for_approval(): void
    {
        $reviewer = User::factory()->teamLeaderIcc()->create();
        $planner = User::factory()->planner()->create(['name' => 'Himatullah']);
        $waiting = Procurement::factory()->plannedBy($planner)->planningSubmitted()->create();
        Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.approvals.total', 1)
                ->where('tasks.approvals.items.0.id', $waiting->id)
                ->where('tasks.approvals.items.0.note', 'Diajukan oleh Himatullah'));
    }

    public function test_the_assigner_sees_procurements_missing_a_pic(): void
    {
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();

        $unassigned = Procurement::factory()->create();
        Procurement::factory()->plannedBy($planner)->executedBy($executor)->create();
        Procurement::factory()->create(['completed_at' => now()]);

        $this->actingAs($teamLeader)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.assignments.total', 1)
                ->where('tasks.assignments.items.0.id', $unassigned->id)
                ->where('tasks.assignments.items.0.note', 'Belum ada PIC Perencana & PIC Pelaksana'));
    }

    public function test_a_pic_sees_their_own_planning_and_execution_work_only(): void
    {
        $planner = User::factory()->planner()->create();
        $other = User::factory()->planner()->create();

        $returned = Procurement::factory()->plannedBy($planner)->create([
            'planning_approval_state' => 'ditolak',
        ]);
        Procurement::factory()->plannedBy($planner)->planningSubmitted()->create();
        Procurement::factory()->plannedBy($other)->create();

        $this->actingAs($planner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.approvals', null)
                ->where('tasks.assignments', null)
                ->where('tasks.planning.total', 1)
                ->where('tasks.planning.items.0.id', $returned->id)
                ->where('tasks.planning.items.0.note', 'Dikembalikan untuk revisi')
                ->where('tasks.execution.total', 0));
    }

    public function test_an_executor_sees_approved_procurements_handed_to_them(): void
    {
        $executor = User::factory()->executor()->create();
        $ready = Procurement::factory()->executedBy($executor)->planningApproved()->create();
        Procurement::factory()->executedBy($executor)->create();

        $this->actingAs($executor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.execution.total', 1)
                ->where('tasks.execution.items.0.id', $ready->id));
    }
}
