<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\SystemStatus;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\User;
use App\Notifications\PlanningReviewed;
use App\Notifications\PlanningSubmitted;
use App\Notifications\ProcurementAssigned;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_leaders_appointing_a_pic_notifies_them(): void
    {
        Notification::fake();

        $teamLeaderIcc = User::factory()->teamLeaderIcc()->create();
        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->create();

        // TL ICC appoints the planning PIC.
        $this->actingAs($teamLeaderIcc)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => $planner->id,
                'executor_id' => null,
            ])
            ->assertRedirect();

        $this->assertSame($planner->id, $procurement->refresh()->planner_id);
        Notification::assertSentTo($planner, ProcurementAssigned::class);

        // TL Pengadaan appoints the executor, once ICC approved the planning.
        $approved = Procurement::factory()->plannedBy($planner)->planningApproved()->create();

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $approved), ['executor_id' => $executor->id])
            ->assertRedirect();

        $this->assertSame($executor->id, $approved->refresh()->executor_id);
        Notification::assertSentTo($executor, ProcurementAssigned::class);
    }

    public function test_each_team_leader_appoints_only_their_own_pic(): void
    {
        $teamLeaderIcc = User::factory()->teamLeaderIcc()->create();
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();

        $planning = Procurement::factory()->create();
        $approved = Procurement::factory()->plannedBy($planner)->planningApproved()->create();

        // TL Pengadaan no longer appoints the planning PIC ...
        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $planning), ['planner_id' => $planner->id])
            ->assertForbidden();

        // ... nor the executor before the planning is approved.
        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $planning), ['executor_id' => $executor->id])
            ->assertForbidden();

        // TL ICC appoints the planner only.
        $this->actingAs($teamLeaderIcc)
            ->put(route('procurements.pic.update', $approved), ['executor_id' => $executor->id])
            ->assertForbidden();

        $this->assertNull($planning->refresh()->planner_id);
        $this->assertNull($planning->executor_id);
        $this->assertNull($approved->refresh()->executor_id);

        // Sending the other PIC unchanged, as the forms do, is not a change.
        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $approved), [
                'planner_id' => $planner->id,
                'executor_id' => $executor->id,
            ])
            ->assertRedirect();

        $this->assertSame($executor->id, $approved->refresh()->executor_id);
    }

    public function test_the_pic_board_shows_each_team_leader_their_own_column(): void
    {
        $planner = User::factory()->planner()->create();
        Procurement::factory()->create(['name' => 'Masih Direncanakan']);
        $approved = Procurement::factory()->plannedBy($planner)->planningApproved()->create(['name' => 'Siap Dilaksanakan']);

        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->get(route('pic-assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.assignPlanner', true)
                ->where('can.assignExecutor', false)
                ->has('procurements.data', 2));

        // TL Pengadaan only sees jobs whose planning is approved.
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('pic-assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.assignPlanner', false)
                ->where('can.assignExecutor', true)
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $approved->id));
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

        $teamLeader = User::factory()->teamLeaderIcc()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();
        $procurement = $this->procurementWithChecklists($planner, $executor);

        $this->completeRequiredPlanning($procurement);

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
        $teamLeader = User::factory()->teamLeaderIcc()->create();
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

    public function test_planner_without_tl_icc_role_cannot_approve_but_with_tl_icc_role_can_approve(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->planningSubmitted()->create();

        // Normal planner without TL ICC role cannot approve
        $this->actingAs($planner)
            ->put(route('procurements.approval.update', $procurement), ['approved' => true])
            ->assertForbidden();

        // If planner is given the role of Team Leader ICC, they can approve their own planning
        $planner->update(['role' => UserRole::TeamLeaderIcc]);

        $this->actingAs($planner)
            ->put(route('procurements.approval.update', $procurement), ['approved' => true])
            ->assertRedirect();

        $this->assertSame(PlanningApprovalState::Disetujui, $procurement->refresh()->planning_approval_state);
        $this->assertSame($planner->id, $procurement->planning_reviewed_by);
    }

    public function test_cancelling_and_restoring_are_recorded_in_the_activity_history(): void
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        // The job is planned, so it sits on a planning status set by the system.
        $before = $procurement->refresh()->progressStatus->systemStatus();
        $this->assertNotNull($before);
        $this->assertNotSame(SystemStatus::Batal, $before);

        // The status follows the work; a PIC can no longer set it.
        $this->actingAs($planner)
            ->put(route('procurements.status.update', $procurement), ['cancelled' => true, 'note' => 'Batal'])
            ->assertForbidden();

        // Cancelling says why.
        $this->actingAs($teamLeader)
            ->put(route('procurements.status.update', $procurement), ['cancelled' => true])
            ->assertSessionHasErrors('note');

        $this->actingAs($teamLeader)
            ->put(route('procurements.status.update', $procurement), ['cancelled' => true, 'note' => 'Anggaran ditunda'])
            ->assertRedirect();

        $this->assertSame(SystemStatus::Batal, $procurement->refresh()->progressStatus->systemStatus());

        // A cancelled job keeps Batal whatever else changes ...
        $procurement->update(['name' => 'Nama Baru']);
        $this->assertSame(SystemStatus::Batal, $procurement->refresh()->progressStatus->systemStatus());

        // ... until the cancellation is lifted.
        $this->actingAs($teamLeader)
            ->put(route('procurements.status.update', $procurement), ['cancelled' => false])
            ->assertRedirect();

        $this->assertSame($before, $procurement->refresh()->progressStatus->systemStatus());
        $this->assertSame(2, $procurement->activities()->where('type', 'status_diubah')->count());
    }

    public function test_closing_out_waits_for_keuangan_to_pay(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = Procurement::factory()->planningApproved()->create();

        // An executed job is closed by Keuangan's payment, not by hand.
        $this->actingAs($teamLeader)
            ->post(route('procurements.completion.store', $procurement))
            ->assertForbidden();

        $this->assertNull($procurement->refresh()->completed_at);

        $procurement->forceFill(['payment_completed_at' => now()])->save();

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

    /**
     * Tick every mandatory planning step so the stage can be submitted.
     */
    private function completeRequiredPlanning(Procurement $procurement): void
    {
        $procurement->checklists()
            ->where('stage', ProcurementStage::Perencanaan->value)
            ->whereIn('checklist_item_id', ChecklistItem::query()
                ->where('is_optional', false)
                ->select('id'))
            ->update(['is_completed' => true, 'completed_at' => now()]);

        $procurement->unsetRelation('checklists');
    }
}
