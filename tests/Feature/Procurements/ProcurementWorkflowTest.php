<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Models\User;
use App\Notifications\PlanningReviewed;
use App\Notifications\PlanningSubmitted;
use App\Notifications\ProcurementAssigned;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_leader_appointing_a_pic_notifies_them(): void
    {
        Notification::fake();

        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->create();

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => $planner->id,
                'executor_id' => $executor->id,
            ])
            ->assertRedirect();

        $procurement->refresh();

        $this->assertSame($planner->id, $procurement->planner_id);
        $this->assertSame($executor->id, $procurement->executor_id);

        Notification::assertSentTo($planner, ProcurementAssigned::class);
        Notification::assertSentTo($executor, ProcurementAssigned::class);
    }

    public function test_pic_cannot_appoint_themselves(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->create();

        $this->actingAs($planner)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => $planner->id,
                'executor_id' => null,
            ])
            ->assertForbidden();

        $this->assertNull($procurement->refresh()->planner_id);
    }

    public function test_planner_can_tick_planning_checklist_items(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = $this->procurementWithChecklists($planner);
        $checklist = $procurement->checklists()->where('stage', ProcurementStage::Perencanaan->value)->firstOrFail();

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
                'notes' => 'Dokumen sudah lengkap',
            ])
            ->assertRedirect();

        $checklist->refresh();

        $this->assertTrue($checklist->is_completed);
        $this->assertSame($planner->id, $checklist->completed_by);
        $this->assertSame('Dokumen sudah lengkap', $checklist->notes);
    }

    public function test_execution_checklist_stays_locked_until_planning_is_approved(): void
    {
        $executor = User::factory()->executor()->create();
        $procurement = $this->procurementWithChecklists(null, $executor);
        $checklist = $procurement->checklists()->where('stage', ProcurementStage::Pelaksanaan->value)->firstOrFail();

        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertForbidden();

        $this->assertFalse($checklist->refresh()->is_completed);
    }

    public function test_planner_submits_and_team_leader_approves_the_planning_stage(): void
    {
        Notification::fake();

        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();
        $procurement = $this->procurementWithChecklists($planner, $executor);

        $this->actingAs($planner)
            ->post(route('procurements.approval.store', $procurement))
            ->assertRedirect();

        $procurement->refresh();

        $this->assertSame(PlanningApprovalState::MenungguPersetujuan, $procurement->planning_approval_state);
        Notification::assertSentTo($teamLeader, PlanningSubmitted::class);

        $this->actingAs($teamLeader)
            ->put(route('procurements.approval.update', $procurement), [
                'approved' => true,
                'note' => null,
            ])
            ->assertRedirect();

        $procurement->refresh();

        $this->assertSame(PlanningApprovalState::Disetujui, $procurement->planning_approval_state);
        $this->assertSame($teamLeader->id, $procurement->planning_reviewed_by);
        Notification::assertSentTo($planner, PlanningReviewed::class);

        $checklist = $procurement->checklists()->where('stage', ProcurementStage::Pelaksanaan->value)->firstOrFail();

        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($checklist->refresh()->is_completed);
    }

    public function test_rejecting_the_planning_stage_requires_a_note(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->planningSubmitted()->create();

        $this->actingAs($teamLeader)
            ->from(route('procurements.show', $procurement))
            ->put(route('procurements.approval.update', $procurement), ['approved' => false])
            ->assertSessionHasErrors('note');

        $this->actingAs($teamLeader)
            ->put(route('procurements.approval.update', $procurement), [
                'approved' => false,
                'note' => 'RKS belum lengkap.',
            ])
            ->assertRedirect();

        $procurement->refresh();

        $this->assertSame(PlanningApprovalState::Ditolak, $procurement->planning_approval_state);
        $this->assertSame('RKS belum lengkap.', $procurement->planning_review_note);
    }

    public function test_planner_cannot_approve_their_own_planning(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->planningSubmitted()->create();

        $this->actingAs($planner)
            ->put(route('procurements.approval.update', $procurement), ['approved' => true])
            ->assertForbidden();
    }

    public function test_status_changes_are_recorded_in_the_activity_history(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $newStatus = ProgressStatus::factory()->create(['name' => 'Penyusunan RKS']);

        $this->actingAs($planner)
            ->put(route('procurements.status.update', $procurement), [
                'progress_status_id' => $newStatus->id,
                'note' => 'Mulai penyusunan',
            ])
            ->assertRedirect();

        $this->assertSame($newStatus->id, $procurement->refresh()->progress_status_id);
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => 'status_diubah',
        ]);
    }

    public function test_team_leader_can_close_out_an_approved_procurement(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = Procurement::factory()->planningApproved()->create();

        $this->actingAs($teamLeader)
            ->post(route('procurements.completion.store', $procurement))
            ->assertRedirect();

        $this->assertNotNull($procurement->refresh()->completed_at);
    }

    /**
     * Create a procurement with checklist rows for both stages.
     */
    private function procurementWithChecklists(?User $planner = null, ?User $executor = null): Procurement
    {
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(2)->create();
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(2)->create();

        $procurement = Procurement::factory()
            ->when($planner !== null, fn ($factory) => $factory->plannedBy($planner))
            ->when($executor !== null, fn ($factory) => $factory->executedBy($executor))
            ->create();

        app(ProcurementService::class)->syncChecklists($procurement);

        return $procurement->refresh();
    }
}
