<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProcurementMilestone;
use App\Notifications\ProposalReviewed;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * After the Asman's approval the job goes to TL ICC, who appoints the
 * planner and approves the planning; TL Pengadaan then appoints the executor
 * and approves the execution before it reaches Penyerahan and Pembayaran.
 */
class ExecutionApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $executor;

    protected User $teamLeader;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executor = User::factory()->executor()->create(['name' => 'Fadli']);
        $this->teamLeader = User::factory()->teamLeaderPengadaan()->create();

        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(2)->create();

        $this->procurement = Procurement::factory()->planningApproved()->executedBy($this->executor)->create();

        app(ProcurementService::class)->syncChecklists($this->procurement);
    }

    public function test_a_returned_execution_reopens_for_the_executor_and_is_resubmitted(): void
    {
        $this->tick();
        $this->submit();

        // A return needs a note saying what to fix.
        $this->actingAs($this->teamLeader)
            ->put(route('procurements.execution-approval.update', $this->procurement), ['approved' => false])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->teamLeader)
            ->put(route('procurements.execution-approval.update', $this->procurement), [
                'approved' => false,
                'note' => 'Lampirkan BAST yang sudah ditandatangani.',
            ])
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PlanningApprovalState::Ditolak, $this->procurement->execution_approval_state);
        $this->assertNull($this->procurement->finance_submitted_at);
        $this->assertTrue($this->executor->can('updateExecutionChecklist', $this->procurement));

        $this->submit();

        $this->procurement->refresh();
        $this->assertSame(PlanningApprovalState::MenungguPersetujuan, $this->procurement->execution_approval_state);
        $this->assertSame(1, $this->procurement->execution_revision);
        // The note being answered stays visible.
        $this->assertSame('Lampirkan BAST yang sudah ditandatangani.', $this->procurement->execution_review_note);

        $this->actingAs($this->teamLeader)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.reviewExecution', true)
                ->where('detail.finance.execution_approval.state', 'menunggu_persetujuan')
                ->where('detail.finance.execution_approval.revision', 1)
                ->where('approvalHistory.waiting', 'Pelaksanaan menunggu approval TL Pengadaan')
                ->where('approvalHistory.rows', fn ($rows) => collect($rows)
                    ->where('stage', 'Pelaksanaan')
                    ->pluck('step')
                    ->values()
                    ->all() === ['Pelaksanaan Diajukan', 'Pelaksanaan Ditolak', 'Pelaksanaan Diajukan']));
    }

    public function test_only_the_appointed_executor_submits_and_only_tl_pengadaan_decides(): void
    {
        $this->tick();

        foreach ([$this->teamLeader, User::factory()->executor()->create(), User::factory()->administrator()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('procurements.execution-approval.store', $this->procurement))
                ->assertForbidden();
        }

        $this->submit();

        foreach ([$this->executor, User::factory()->teamLeaderIcc()->create()] as $user) {
            $this->actingAs($user)
                ->put(route('procurements.execution-approval.update', $this->procurement), ['approved' => true])
                ->assertForbidden();
        }

        $this->assertNull($this->procurement->refresh()->finance_submitted_at);

        // The administrator may stand in for TL Pengadaan.
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('procurements.execution-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->assertTrue($this->procurement->refresh()->isInFinance());
    }

    public function test_the_approval_queue_lists_the_executions_waiting_for_tl_pengadaan(): void
    {
        $this->tick();
        $this->submit();

        $this->actingAs($this->teamLeader)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('procurements.data', 0)
                ->has('executions.data', 1)
                ->where('executions.data.0.id', $this->procurement->id)
                ->where('executions.data.0.execution_approval_state', 'menunggu_persetujuan'));
    }

    public function test_the_asman_approval_goes_to_tl_icc_and_the_planning_approval_to_tl_pengadaan(): void
    {
        Notification::fake();

        $division = WorkDirector::factory()->create();
        $director = User::factory()->workDirector($division)->create();
        $teamLeaderIcc = User::factory()->teamLeaderIcc()->create();
        $planner = User::factory()->planner()->create();

        $proposal = Procurement::factory()->create([
            'work_director_id' => $division->id,
            'proposal_state' => ProposalState::MenungguPersetujuan,
            'proposal_submitted_at' => now(),
        ]);

        app(ProcurementService::class)->decideProposal($proposal, $director, true);

        Notification::assertSentTo($teamLeaderIcc, ProposalReviewed::class);
        Notification::assertNotSentTo($this->teamLeader, ProposalReviewed::class);

        $planned = Procurement::factory()->plannedBy($planner)->create([
            'planning_approval_state' => PlanningApprovalState::MenungguPersetujuan,
        ]);

        app(ProcurementService::class)->reviewPlanning($planned, $teamLeaderIcc, true);

        // Nobody is executing it yet, so TL Pengadaan is asked to appoint someone.
        Notification::assertSentTo($this->teamLeader, ProcurementMilestone::class);
    }

    /**
     * The executor submits the execution.
     */
    protected function submit(): void
    {
        $this->actingAs($this->executor)
            ->post(route('procurements.execution-approval.store', $this->procurement))
            ->assertRedirect();

        $this->procurement->refresh();
    }

    /**
     * Tick every execution step.
     */
    protected function tick(): void
    {
        $this->procurement->checklists()
            ->where('stage', ProcurementStage::Pelaksanaan)
            ->get()
            ->each(fn (ProcurementChecklist $checklist) => $checklist->update(['is_completed' => true]));

        $this->procurement->unsetRelation('checklists');
    }
}
