<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\User;
use App\Notifications\PlanningReviewed;
use App\Notifications\PlanningSubmitted;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A rejection sends the planning stage back to its PIC rather than ending it.
 */
class PlanningRevisionLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_rejection_note_survives_the_resubmission(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'TOR belum mencantumkan spesifikasi teknis.',
        ]);

        $this->assertSame(
            'TOR belum mencantumkan spesifikasi teknis.',
            $procurement->refresh()->planning_review_note,
        );

        $this->actingAs($planner)->post(route('procurements.approval.store', $procurement));

        $procurement->refresh();

        // The reviewer of the previous round is cleared, but what they asked
        // for stays readable for the PIC and for whoever reviews round two.
        $this->assertSame(
            'TOR belum mencantumkan spesifikasi teknis.',
            $procurement->planning_review_note,
        );
        $this->assertNull($procurement->planning_reviewed_at);
        $this->assertNull($procurement->planning_reviewed_by);
    }

    public function test_each_round_trip_increments_the_revision_counter(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->assertSame(0, $procurement->refresh()->planning_revision);

        foreach ([1, 2] as $round) {
            $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
                'approved' => false,
                'note' => "Perbaikan putaran {$round}.",
            ]);

            $this->actingAs($planner)->post(route('procurements.approval.store', $procurement));

            $this->assertSame($round, $procurement->refresh()->planning_revision);
        }

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => true,
            'note' => null,
        ]);

        $procurement->refresh();

        $this->assertSame(PlanningApprovalState::Disetujui, $procurement->planning_approval_state);
        $this->assertSame(2, $procurement->planning_revision);
    }

    public function test_the_resubmission_notifies_the_approvers_again(): void
    {
        Notification::fake();

        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'Mohon dilengkapi.',
        ]);

        Notification::assertSentTo($planner, PlanningReviewed::class);

        $this->actingAs($planner)->post(route('procurements.approval.store', $procurement));

        Notification::assertSentTo($teamLeader, PlanningSubmitted::class);

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::PerencanaanDiajukan->value,
            'description' => 'Dokumen perencanaan diajukan ulang (revisi ke-1).',
        ]);
    }

    public function test_the_detail_screen_hands_the_follow_up_to_the_planner(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'RAB perlu dikoreksi.',
        ]);

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('procurement.planning_approval_state', 'ditolak')
                ->where('detail.planning_review_note', 'RAB perlu dikoreksi.')
                ->where('detail.is_planner', true)
                ->where('can.submitPlanning', true)
            );

        // A supervisor sees the same state, but the action is not theirs.
        $this->actingAs($teamLeader)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('detail.is_planner', false)
                ->where('detail.planner_name', $planner->name)
                ->where('can.submitPlanning', false)
                ->where('can.reviewPlanning', false)
            );
    }

    public function test_a_returned_stage_can_be_worked_on_again(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'Lengkapi TOR.',
        ]);

        $checklist = $procurement->checklists()
            ->where('stage', ProcurementStage::Perencanaan->value)
            ->firstOrFail();

        // Ticking stays open while the stage is back with its PIC, which is
        // the whole point of returning it.
        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
                'notes' => 'TOR sudah dilengkapi.',
            ])
            ->assertRedirect();

        $this->assertSame('TOR sudah dilengkapi.', $checklist->refresh()->notes);
    }

    public function test_the_dashboard_counts_what_is_waiting_on_a_revision(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($planner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.needsRevision', 0));

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'Belum lengkap.',
        ]);

        $this->actingAs($planner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.needsRevision', 1));
    }

    public function test_the_planning_list_can_be_filtered_to_the_returned_ones(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'Belum lengkap.',
        ]);

        // A second procurement that is not waiting on a revision.
        $other = Procurement::factory()->plannedBy($planner)->create();
        app(ProcurementService::class)->syncChecklists($other);

        $this->actingAs($planner)
            ->get(route('planning.index', ['approval_state' => 'ditolak']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.approval_state', 'ditolak')
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $procurement->id)
            );
    }

    public function test_an_unknown_approval_state_filter_is_ignored(): void
    {
        [$procurement, $planner] = $this->submitted();

        $this->actingAs($planner)
            ->get(route('planning.index', ['approval_state' => 'entah']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.approval_state', null)
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $procurement->id)
            );
    }

    public function test_a_supervisor_can_withdraw_a_rejection_made_in_error(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'Salah tekan.',
        ]);

        $this->actingAs($teamLeader)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.revertPlanningRejection', true));

        $this->actingAs($teamLeader)
            ->delete(route('procurements.approval.destroy', $procurement))
            ->assertRedirect();

        $procurement->refresh();

        // Back in the queue for a decision, without anyone having resubmitted.
        $this->assertSame(PlanningApprovalState::MenungguPersetujuan, $procurement->planning_approval_state);
        $this->assertNull($procurement->planning_review_note);
        $this->assertNull($procurement->planning_reviewed_by);
        $this->assertSame(0, $procurement->planning_revision);

        $this->actingAs($teamLeader)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.reviewPlanning', true)
                ->where('can.revertPlanningRejection', false)
            );

        $this->assertSame($planner->id, $procurement->planner_id);
    }

    public function test_withdrawing_a_rejection_is_refused_to_a_pic(): void
    {
        [$procurement, $planner, $teamLeader] = $this->submitted();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'Perlu revisi.',
        ]);

        $this->actingAs($planner)
            ->delete(route('procurements.approval.destroy', $procurement))
            ->assertForbidden();

        $this->assertSame(
            PlanningApprovalState::Ditolak,
            $procurement->refresh()->planning_approval_state,
        );
    }

    public function test_a_rejection_cannot_be_withdrawn_once_it_is_no_longer_rejected(): void
    {
        [$procurement, , $teamLeader] = $this->submitted();

        // Still awaiting approval, nothing to withdraw.
        $this->actingAs($teamLeader)
            ->delete(route('procurements.approval.destroy', $procurement))
            ->assertForbidden();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => true,
            'note' => null,
        ]);

        $this->actingAs($teamLeader)
            ->delete(route('procurements.approval.destroy', $procurement))
            ->assertForbidden();

        $this->assertSame(
            PlanningApprovalState::Disetujui,
            $procurement->refresh()->planning_approval_state,
        );
    }

    public function test_reassigning_the_pic_hands_the_revision_to_someone_else(): void
    {
        [$procurement, , $teamLeader] = $this->submitted();
        $replacement = User::factory()->planner()->create();

        $this->actingAs($teamLeader)->put(route('procurements.approval.update', $procurement), [
            'approved' => false,
            'note' => 'Perlu revisi.',
        ]);

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => $replacement->id,
                'executor_id' => null,
            ])
            ->assertRedirect();

        $this->actingAs($replacement)
            ->post(route('procurements.approval.store', $procurement))
            ->assertRedirect();

        $this->assertSame(
            PlanningApprovalState::MenungguPersetujuan,
            $procurement->refresh()->planning_approval_state,
        );
        $this->assertSame(1, $procurement->planning_revision);
    }

    /**
     * A procurement whose planning stage is already awaiting approval.
     *
     * @return array{0: Procurement, 1: User, 2: User}
     */
    protected function submitted(): array
    {
        $planner = User::factory()->planner()->create();
        $teamLeader = User::factory()->teamLeader()->create();

        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(2)->create();
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(1)->create();

        $procurement = Procurement::factory()->plannedBy($planner)->create();

        app(ProcurementService::class)->syncChecklists($procurement);

        $procurement->checklists()->update(['is_completed' => true, 'completed_at' => now()]);

        $this->actingAs($planner)->post(route('procurements.approval.store', $procurement->refresh()));

        return [$procurement->refresh(), $planner, $teamLeader];
    }
}
