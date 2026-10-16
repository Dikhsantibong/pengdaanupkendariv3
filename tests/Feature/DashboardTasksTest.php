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

    public function test_tl_icc_sees_approved_proposals_missing_a_planner(): void
    {
        $teamLeaderIcc = User::factory()->teamLeaderIcc()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();

        $unassigned = Procurement::factory()->create();
        Procurement::factory()->plannedBy($planner)->create();
        // The executor is not TL ICC's to appoint.
        Procurement::factory()->plannedBy($planner)->planningApproved()->create();
        Procurement::factory()->plannedBy($planner)->executedBy($executor)->create();
        Procurement::factory()->create(['completed_at' => now()]);

        $this->actingAs($teamLeaderIcc)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.assignments.total', 1)
                ->where('tasks.assignments.items.0.id', $unassigned->id)
                ->where('tasks.assignments.items.0.note', 'Belum ada PIC Perencana'));
    }

    public function test_tl_pengadaan_sees_approved_plannings_missing_an_executor(): void
    {
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();

        // Still being planned: no executor is appointed yet.
        Procurement::factory()->create();
        Procurement::factory()->plannedBy($planner)->create();
        $approved = Procurement::factory()->plannedBy($planner)->planningApproved()->create();
        Procurement::factory()->plannedBy($planner)->executedBy($executor)->planningApproved()->create();

        $this->actingAs($teamLeader)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.assignments.total', 1)
                ->where('tasks.assignments.items.0.id', $approved->id)
                ->where('tasks.assignments.items.0.note', 'Perencanaan disetujui, belum ada PIC Pelaksana'));
    }

    public function test_tl_pengadaan_sees_the_executions_waiting_for_approval(): void
    {
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $executor = User::factory()->executor()->create(['name' => 'Fadli']);

        $waiting = Procurement::factory()->executedBy($executor)->planningApproved()->create([
            'execution_approval_state' => 'menunggu_persetujuan',
            'execution_submitted_at' => now(),
        ]);
        Procurement::factory()->executedBy($executor)->planningApproved()->create();

        $this->actingAs($teamLeader)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.approvals', null)
                ->where('tasks.executionApprovals.total', 1)
                ->where('tasks.executionApprovals.items.0.id', $waiting->id)
                ->where('tasks.executionApprovals.items.0.note', 'Diajukan oleh Fadli'));

        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('tasks.executionApprovals', null));
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
