<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submitting the planning stage and approving it are separate duties.
 *
 * Only the appointed planning PIC submits, and only once every mandatory
 * planning step is done. Supervisors approve, and never submit.
 */
class PlanningSubmissionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_cannot_submit_the_planning_stage(): void
    {
        $administrator = User::factory()->administrator()->create();
        $planner = User::factory()->planner()->create();
        $procurement = $this->readyProcurement($planner);

        $this->actingAs($administrator)
            ->post(route('procurements.approval.store', $procurement))
            ->assertForbidden();

        $this->assertSame(
            PlanningApprovalState::BelumDiajukan,
            $procurement->refresh()->planning_approval_state,
        );
    }

    public function test_a_team_leader_cannot_submit_the_planning_stage(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();
        $procurement = $this->readyProcurement($planner);

        $this->actingAs($teamLeader)
            ->post(route('procurements.approval.store', $procurement))
            ->assertForbidden();
    }

    public function test_the_submit_action_is_hidden_from_an_administrator(): void
    {
        $administrator = User::factory()->administrator()->create();
        $procurement = $this->readyProcurement(User::factory()->planner()->create());

        $this->actingAs($administrator)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.submitPlanning', false)
                ->where('can.reviewPlanning', false)
                ->where('detail.is_planner', false)
            );
    }

    public function test_the_assigned_planner_submits_once_the_required_steps_are_done(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = $this->readyProcurement($planner);

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.submitPlanning', true)
                ->where('detail.is_planner', true)
                ->where('detail.pending_required_planning', [])
            );

        $this->actingAs($planner)
            ->post(route('procurements.approval.store', $procurement))
            ->assertRedirect();

        $this->assertSame(
            PlanningApprovalState::MenungguPersetujuan,
            $procurement->refresh()->planning_approval_state,
        );
    }

    public function test_submission_is_refused_while_a_mandatory_step_is_outstanding(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = $this->readyProcurement($planner);

        $required = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->where('is_optional', false)
            ->firstOrFail();

        $procurement->checklists()
            ->where('checklist_item_id', $required->id)
            ->update(['is_completed' => false, 'completed_at' => null]);

        $this->actingAs($planner)
            ->post(route('procurements.approval.store', $procurement))
            ->assertForbidden();

        $this->assertSame(
            PlanningApprovalState::BelumDiajukan,
            $procurement->refresh()->planning_approval_state,
        );
    }

    public function test_the_planner_is_told_which_mandatory_steps_are_missing(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = $this->readyProcurement($planner);

        $required = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->where('is_optional', false)
            ->firstOrFail();

        $procurement->checklists()
            ->where('checklist_item_id', $required->id)
            ->update(['is_completed' => false, 'completed_at' => null]);

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.submitPlanning', false)
                ->where('detail.pending_required_planning', [$required->name])
            );
    }

    public function test_an_outstanding_optional_step_does_not_block_submission(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = $this->readyProcurement($planner);

        $optional = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->where('is_optional', true)
            ->firstOrFail();

        $procurement->checklists()
            ->where('checklist_item_id', $optional->id)
            ->update(['is_completed' => false, 'completed_at' => null]);

        $this->actingAs($planner)
            ->post(route('procurements.approval.store', $procurement))
            ->assertRedirect();

        $this->assertSame(
            PlanningApprovalState::MenungguPersetujuan,
            $procurement->refresh()->planning_approval_state,
        );
    }

    public function test_a_procurement_without_a_planner_cannot_be_submitted_by_anyone(): void
    {
        $procurement = $this->readyProcurement(null);

        foreach ([
            User::factory()->administrator()->create(),
            User::factory()->teamLeader()->create(),
            User::factory()->planner()->create(),
        ] as $user) {
            $this->actingAs($user)
                ->post(route('procurements.approval.store', $procurement))
                ->assertForbidden();
        }
    }

    public function test_a_supervisor_still_approves_what_the_planner_submitted(): void
    {
        $planner = User::factory()->planner()->create();
        $administrator = User::factory()->administrator()->create();
        $procurement = $this->readyProcurement($planner);

        $this->actingAs($planner)->post(route('procurements.approval.store', $procurement));

        $this->actingAs($administrator)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.submitPlanning', false)
                ->where('can.reviewPlanning', true)
            );

        $this->actingAs($administrator)
            ->put(route('procurements.approval.update', $procurement), [
                'approved' => true,
                'note' => null,
            ])
            ->assertRedirect();

        $this->assertSame(
            PlanningApprovalState::Disetujui,
            $procurement->refresh()->planning_approval_state,
        );
    }

    public function test_a_rejected_stage_can_be_resubmitted_by_the_planner_only(): void
    {
        $planner = User::factory()->planner()->create();
        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = $this->readyProcurement($planner);

        $this->actingAs($planner)->post(route('procurements.approval.store', $procurement));

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'TOR belum sesuai.',
        ]);

        $this->assertSame(
            PlanningApprovalState::Ditolak,
            $procurement->refresh()->planning_approval_state,
        );

        $this->actingAs($teamLeader)
            ->post(route('procurements.approval.store', $procurement))
            ->assertForbidden();

        $this->actingAs($planner)
            ->post(route('procurements.approval.store', $procurement))
            ->assertRedirect();

        $this->assertSame(
            PlanningApprovalState::MenungguPersetujuan,
            $procurement->refresh()->planning_approval_state,
        );
    }

    /**
     * A procurement whose mandatory planning steps are all ticked.
     */
    protected function readyProcurement(?User $planner): Procurement
    {
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(3)->create();
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->optional()->count(2)->create();
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(2)->create();

        $procurement = Procurement::factory()
            ->when($planner !== null, fn ($factory) => $factory->plannedBy($planner))
            ->create();

        app(ProcurementService::class)->syncChecklists($procurement);

        $procurement->checklists()->update(['is_completed' => true, 'completed_at' => now()]);

        return $procurement->refresh();
    }
}
