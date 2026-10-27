<?php

namespace Tests\Feature\Procurements;

use App\Enums\FinanceSection;
use App\Enums\PaymentApprovalState;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProcurementAssigned;
use App\Notifications\ProcurementMilestone;
use App\Services\ProcurementService;
use Database\Seeders\FinanceRequirementSeeder;
use Database\Seeders\FinanceStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * After the execution TL Pengadaan approved, the execution PIC files the
 * hand-over (Penyerahan) for TL Pengadaan's approval; then a Keuangan staff
 * member files the payment for TL Keuangan and the Asman of the division,
 * whose approval records the payment and closes the procurement.
 */
class FinanceStageTest extends TestCase
{
    use RefreshDatabase;

    protected User $executor;

    /** A Keuangan staff member, led by TL Keuangan. */
    protected User $finance;

    protected User $financeLead;

    /** The Asman of the Keuangan division (Asman Business Support). */
    protected User $asman;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executor = User::factory()->executor()->create();
        $businessSupport = WorkDirector::factory()->create(['name' => 'Asman Business Support']);
        $this->asman = User::factory()->workDirector($businessSupport)->create();
        $this->financeLead = User::factory()->create(['role' => UserRole::Keuangan, 'work_director_id' => $businessSupport->id]);
        $this->finance = User::factory()->create([
            'role' => UserRole::Keuangan,
            'work_director_id' => $businessSupport->id,
            'team_leader_id' => $this->financeLead->id,
        ]);

        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(2)->create();

        foreach (['SPK', 'PJ', 'SPPL'] as $code) {
            ContractNumberFormat::factory()->create(['code' => $code]);
        }

        $this->seed(FinanceRequirementSeeder::class);

        $this->procurement = Procurement::factory()
            ->planningApproved()
            ->executedBy($this->executor)
            ->create([
                'procurement_method_id' => null,
                'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
                'work_category' => 'jasa',
            ]);

        app(ProcurementService::class)->syncChecklists($this->procurement);
    }

    public function test_the_generic_keuangan_steps_are_switched_off(): void
    {
        // Laid out by the earlier migration, replaced by the requirements.
        $this->assertSame(
            0,
            ChecklistItem::query()
                ->forStage(ProcurementStage::Keuangan)
                ->active()
                ->whereIn('name', array_column(FinanceStageSeeder::STEPS, 0))
                ->count(),
        );

        // Seeding again adds nothing new.
        $count = ChecklistItem::query()->forStage(ProcurementStage::Keuangan)->count();
        $this->seed(FinanceRequirementSeeder::class);
        $this->assertSame($count, ChecklistItem::query()->forStage(ProcurementStage::Keuangan)->count());
    }

    public function test_the_executor_submits_only_a_finished_execution_and_tl_pengadaan_hands_it_over(): void
    {
        Notification::fake();

        $teamLeader = User::factory()->teamLeaderPengadaan()->create();

        $this->actingAs($this->executor)
            ->post(route('procurements.execution-approval.store', $this->procurement))
            ->assertForbidden();

        $this->tick(ProcurementStage::Pelaksanaan);

        $this->actingAs($this->executor)
            ->post(route('procurements.execution-approval.store', $this->procurement))
            ->assertRedirect();

        // Submitting is not handing over: TL Pengadaan decides first, and
        // execution is held while it is reviewed.
        $this->procurement->refresh();
        $this->assertFalse($this->procurement->isInFinance());
        $this->assertFalse($this->executor->can('updateExecutionChecklist', $this->procurement));
        Notification::assertSentTo($teamLeader, ProcurementMilestone::class);
        Notification::assertNotSentTo($this->finance, ProcurementMilestone::class);

        $this->actingAs($teamLeader)
            ->put(route('procurements.execution-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertTrue($this->procurement->isInFinance());
        $this->assertTrue($this->procurement->isInHandover());
        $this->assertSame($teamLeader->id, $this->procurement->finance_submitted_by);

        // The hand-over comes first: the executor hears of it, Keuangan not yet.
        Notification::assertSentTo($this->executor, ProcurementMilestone::class);
        Notification::assertNotSentTo($this->finance, ProcurementMilestone::class);

        // Execution is final now; nobody submits it twice.
        $this->assertFalse($this->executor->can('updateExecutionChecklist', $this->procurement));
        $this->actingAs($this->executor)
            ->post(route('procurements.execution-approval.store', $this->procurement))
            ->assertForbidden();
    }

    public function test_keuangan_sees_every_job_but_pays_only_after_the_handover(): void
    {
        // Keuangan follows every job, read-only.
        $this->actingAs($this->finance)
            ->get(route('procurements.show', $this->procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.updatePaymentChecklist', false)
                ->where('can.update', false));

        $this->handOver();

        // Penyerahan is the execution side's: the executor files it,
        // Keuangan cannot.
        $handoverStep = $this->step(FinanceSection::Penyerahan);
        $paymentStep = $this->step(FinanceSection::Pembayaran);

        $this->assertTrue($this->executor->can('updateHandoverChecklist', $this->procurement));
        $this->assertFalse($this->finance->can('updateFinanceStep', [$this->procurement, $handoverStep]));
        $this->assertFalse($this->finance->can('updatePaymentChecklist', $this->procurement));

        $this->actingAs($this->finance)
            ->put(route('procurements.checklists.update', [$this->procurement, $handoverStep]), ['is_completed' => true])
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('section', 'pembayaran')->where('counts.waiting', 0));

        $this->actingAs($this->executor)
            ->get(route('handover.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('section', 'penyerahan')
                ->where('counts.waiting', 1)->has('rows.data', 1)
                ->where('rows.data.0.id', $this->procurement->id));

        $this->approveHandover();

        // Then Pembayaran is Keuangan's, and the executor's papers are final.
        $this->actingAs($this->finance)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn ($page) => $page
                ->where('can.updatePaymentChecklist', true)
                ->where('can.updateHandoverChecklist', false)
                ->has('checklists.keuangan', $this->spkJasaRequirementCount()));

        $this->actingAs($this->finance)
            ->get(route('finance.index'))
            ->assertInertia(fn ($page) => $page
                ->where('counts.waiting', 1)->has('rows.data', 1)
                ->where('rows.data.0.id', $this->procurement->id));

        $this->actingAs($this->executor)
            ->put(route('procurements.checklists.update', [$this->procurement, $paymentStep]), ['is_completed' => true])
            ->assertForbidden();
        $this->assertFalse($this->executor->can('updateHandoverChecklist', $this->procurement->refresh()));
    }

    public function test_the_handover_needs_its_papers_and_tl_pengadaan_may_return_it(): void
    {
        Notification::fake();

        $this->handOver();
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();

        $this->actingAs($this->executor)
            ->post(route('procurements.handover-approval.store', $this->procurement))
            ->assertForbidden();

        $this->file(FinanceSection::Penyerahan);

        $this->actingAs($this->executor)
            ->post(route('procurements.handover-approval.store', $this->procurement))
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PlanningApprovalState::MenungguPersetujuan, $this->procurement->handover_approval_state);
        Notification::assertSentTo($teamLeader, ProcurementMilestone::class);

        // Held while it is reviewed.
        $this->assertFalse($this->executor->can('updateHandoverChecklist', $this->procurement));

        // A return needs a note, and reopens the papers to the executor.
        $this->actingAs($teamLeader)
            ->put(route('procurements.handover-approval.update', $this->procurement), ['approved' => false])
            ->assertSessionHasErrors('note');

        $this->actingAs($teamLeader)
            ->put(route('procurements.handover-approval.update', $this->procurement), ['approved' => false, 'note' => 'Lengkapi foto serah terima.'])
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PlanningApprovalState::Ditolak, $this->procurement->handover_approval_state);
        $this->assertTrue($this->executor->can('updateHandoverChecklist', $this->procurement));
        $this->assertFalse($this->procurement->isInPayment());

        $this->actingAs($this->executor)
            ->post(route('procurements.handover-approval.store', $this->procurement))
            ->assertRedirect();

        $this->assertSame(1, $this->procurement->refresh()->handover_revision);

        // Only who holds the right decides.
        $this->actingAs($this->finance)
            ->put(route('procurements.handover-approval.update', $this->procurement), ['approved' => true])
            ->assertForbidden();

        $this->actingAs($teamLeader)
            ->put(route('procurements.handover-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->assertTrue($this->procurement->refresh()->isInPayment());
        Notification::assertSentTo($this->finance, ProcurementMilestone::class);
    }

    public function test_the_payment_goes_through_tl_keuangan_and_the_asman_and_closes_the_job(): void
    {
        Notification::fake();

        $this->handOver();
        $this->approveHandover();

        $this->actingAs($this->finance)
            ->post(route('procurements.payment-approval.store', $this->procurement))
            ->assertForbidden();

        // Optional steps (those "apabila ada") do not hold the payment back;
        // ticked without their papers, the others still do.
        $required = $this->procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan)
            ->whereHas('checklistItem', fn ($item) => $item->where('is_optional', false)->where('section', FinanceSection::Pembayaran->value));
        (clone $required)->update(['is_completed' => true]);
        $this->procurement->unsetRelation('checklists');

        $this->actingAs($this->finance)
            ->post(route('procurements.payment-approval.store', $this->procurement))
            ->assertForbidden();

        $this->file(FinanceSection::Pembayaran);

        $this->actingAs($this->finance)
            ->post(route('procurements.payment-approval.store', $this->procurement))
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PaymentApprovalState::MenungguTl, $this->procurement->payment_approval_state);
        $this->assertSame($this->financeLead->id, $this->procurement->payment_team_leader_id);
        Notification::assertSentTo($this->financeLead, ProcurementMilestone::class);

        // Locked while it is decided; the Asman waits for the TL.
        $this->assertFalse($this->finance->can('updatePaymentChecklist', $this->procurement));
        $this->actingAs($this->asman)
            ->put(route('procurements.payment-approval.update', $this->procurement), ['approved' => true])
            ->assertForbidden();

        $this->actingAs($this->financeLead)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn ($page) => $page->where('can.decidePayment', true));

        $this->actingAs($this->financeLead)
            ->put(route('procurements.payment-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PaymentApprovalState::MenungguAsman, $this->procurement->payment_approval_state);
        $this->assertFalse($this->procurement->isPaid());
        Notification::assertSentTo($this->asman, ProcurementMilestone::class);

        // The Asman sees the job, and approving records the payment.
        $this->actingAs($this->asman)
            ->get(route('procurements.show', $this->procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.decidePayment', true));

        $this->actingAs($this->asman)
            ->put(route('procurements.payment-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PaymentApprovalState::Disetujui, $this->procurement->payment_approval_state);
        $this->assertNotNull($this->procurement->payment_completed_at);
        $this->assertSame($this->asman->id, $this->procurement->payment_completed_by);
        $this->assertNotNull($this->procurement->completed_at);
        $this->assertSame(FinanceStageSeeder::CLOSED_STATUS, $this->procurement->progressStatus->name);
        $this->assertSame('selesai', $this->procurement->currentStage()['key']);

        Notification::assertSentTo($this->executor, ProcurementMilestone::class);

        $this->actingAs($this->finance)
            ->get(route('finance.index'))
            ->assertInertia(fn ($page) => $page->where('counts.waiting', 0)->where('counts.done', 1));
    }

    public function test_only_the_deciders_approve_and_a_returned_payment_reopens(): void
    {
        $this->handOver();
        $this->approveHandover();
        $this->file(FinanceSection::Pembayaran);

        // TL Pengadaan does not pay.
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->post(route('procurements.payment-approval.store', $this->procurement))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->post(route('procurements.payment-approval.store', $this->procurement))
            ->assertRedirect();

        // Not the submitter, another staff member, or another TL.
        $colleague = User::factory()->create(['role' => UserRole::Keuangan, 'team_leader_id' => $this->financeLead->id]);

        foreach ([$this->finance, $colleague, User::factory()->teamLeaderIcc()->create()] as $outsider) {
            $this->actingAs($outsider)
                ->put(route('procurements.payment-approval.update', $this->procurement), ['approved' => true])
                ->assertForbidden();
        }

        $this->actingAs($this->financeLead)
            ->put(route('procurements.payment-approval.update', $this->procurement), ['approved' => false, 'note' => 'Faktur pajak belum ada.'])
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PaymentApprovalState::Dikembalikan, $this->procurement->payment_approval_state);
        $this->assertTrue($this->finance->can('updatePaymentChecklist', $this->procurement));

        $this->actingAs($this->finance)
            ->post(route('procurements.payment-approval.store', $this->procurement))
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(1, $this->procurement->payment_revision);
        $this->assertSame(PaymentApprovalState::MenungguTl, $this->procurement->payment_approval_state);
    }

    public function test_a_finance_member_without_a_team_leader_goes_straight_to_the_asman(): void
    {
        $this->handOver();
        $this->approveHandover();
        $this->file(FinanceSection::Pembayaran);

        $this->actingAs($this->financeLead)
            ->post(route('procurements.payment-approval.store', $this->procurement))
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertSame(PaymentApprovalState::MenungguAsman, $this->procurement->payment_approval_state);
        $this->assertNull($this->procurement->payment_team_leader_id);

        $this->actingAs($this->asman)
            ->put(route('procurements.payment-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->assertTrue($this->procurement->refresh()->isPaid());
    }

    public function test_the_payment_queue_shows_in_the_approval_menu(): void
    {
        $this->handOver();
        $this->approveHandover();
        $this->file(FinanceSection::Pembayaran);

        $this->actingAs($this->finance)
            ->post(route('procurements.payment-approval.store', $this->procurement));

        $this->actingAs($this->financeLead)
            ->get(route('approvals.index', ['queue' => 'payment']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('queue', 'payment')
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $this->procurement->id));

        // The Asman waits for the TL.
        $this->actingAs($this->asman)
            ->get(route('approvals.index', ['queue' => 'payment']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('procurements.data', 0));
    }

    public function test_the_executor_hears_of_the_job_when_planning_is_approved(): void
    {
        Notification::fake();

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()
            ->plannedBy($planner)
            ->executedBy($this->executor)
            ->create(['planning_approval_state' => 'menunggu_persetujuan']);

        app(ProcurementService::class)->reviewPlanning($procurement, User::factory()->teamLeaderIcc()->create(), true);

        Notification::assertSentTo($this->executor, ProcurementAssigned::class);
    }

    /**
     * How many Penyerahan and Pembayaran requirements the SPK Jasa carries.
     */
    protected function spkJasaRequirementCount(): int
    {
        $items = FinanceRequirementSeeder::SOURCES['SPK Jasa']['items'];

        return count($items['penyerahan']) + count($items['pembayaran']);
    }

    /**
     * Finish execution, submit it and have TL Pengadaan approve it, which
     * hands the job to Keuangan.
     */
    protected function handOver(): void
    {
        $this->tick(ProcurementStage::Pelaksanaan);

        $this->actingAs($this->executor)
            ->post(route('procurements.execution-approval.store', $this->procurement))
            ->assertRedirect();

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->put(route('procurements.execution-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->procurement->refresh();
    }

    /**
     * TL Pengadaan approves the hand-over the executor filed and submitted.
     */
    protected function approveHandover(): void
    {
        $this->file(FinanceSection::Penyerahan);

        $this->actingAs($this->executor)
            ->post(route('procurements.handover-approval.store', $this->procurement))
            ->assertRedirect();

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->put(route('procurements.handover-approval.update', $this->procurement), ['approved' => true])
            ->assertRedirect();

        $this->procurement->refresh();
    }

    /**
     * Tick every mandatory step of a Keuangan tab with its uploaded paper.
     */
    protected function file(FinanceSection $section): void
    {
        $this->procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan)
            ->with('checklistItem')
            ->get()
            ->filter(fn (ProcurementChecklist $step): bool => $step->financeSection() === $section && ! $step->checklistItem->is_optional)
            ->each(function (ProcurementChecklist $step): void {
                $step->update(['is_completed' => true]);
                $step->files()->create([
                    'path' => "checklist-berkas/{$step->id}/bukti.pdf",
                    'file_name' => 'bukti.pdf',
                ]);
            });

        $this->procurement->unsetRelation('checklists');
    }

    /**
     * The first step of a Keuangan tab.
     */
    protected function step(FinanceSection $section): ProcurementChecklist
    {
        return $this->procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan)
            ->with('checklistItem')
            ->get()
            ->first(fn (ProcurementChecklist $step): bool => $step->financeSection() === $section);
    }

    /**
     * Tick every step of a stage.
     */
    protected function tick(ProcurementStage $stage): void
    {
        $this->procurement->checklists()
            ->where('stage', $stage)
            ->get()
            ->each(fn (ProcurementChecklist $checklist) => $checklist->update(['is_completed' => true]));

        $this->procurement->unsetRelation('checklists');
    }
}
