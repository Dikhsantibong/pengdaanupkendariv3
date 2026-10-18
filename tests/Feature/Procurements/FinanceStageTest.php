<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Notifications\ProcurementAssigned;
use App\Notifications\ProcurementMilestone;
use App\Services\ProcurementService;
use Database\Seeders\FinanceRequirementSeeder;
use Database\Seeders\FinanceStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * After execution, approved by TL Pengadaan, the job goes to Keuangan, who
 * pays the partner and closes the procurement.
 */
class FinanceStageTest extends TestCase
{
    use RefreshDatabase;

    protected User $executor;

    protected User $finance;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executor = User::factory()->executor()->create();
        $this->finance = User::factory()->create(['role' => UserRole::Keuangan]);

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
        $this->assertSame($teamLeader->id, $this->procurement->finance_submitted_by);

        Notification::assertSentTo($this->finance, ProcurementMilestone::class);

        // Execution is final now; nobody submits it twice.
        $this->assertFalse($this->executor->can('updateExecutionChecklist', $this->procurement));
        $this->actingAs($this->executor)
            ->post(route('procurements.execution-approval.store', $this->procurement))
            ->assertForbidden();
    }

    public function test_keuangan_only_sees_and_works_on_jobs_handed_to_it(): void
    {
        $this->actingAs($this->finance)
            ->get(route('procurements.show', $this->procurement))
            ->assertForbidden();

        $this->assertFalse($this->finance->can('updateFinanceChecklist', $this->procurement));

        $this->handOver();

        $this->actingAs($this->finance)
            ->get(route('procurements.show', $this->procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.updateFinanceChecklist', true)
                ->where('can.completePayment', false)
                ->has('checklists.keuangan', $this->spkJasaRequirementCount()));

        $this->actingAs($this->finance)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('waiting', 1)
                ->where('waiting.0.id', $this->procurement->id));

        // The executor cannot tick the Keuangan steps.
        $step = $this->procurement->checklists()->where('stage', ProcurementStage::Keuangan)->firstOrFail();

        $this->actingAs($this->executor)
            ->put(route('procurements.checklists.update', [$this->procurement, $step]), ['is_completed' => true])
            ->assertForbidden();
    }

    public function test_payment_waits_for_the_mandatory_steps_and_closes_the_job(): void
    {
        Notification::fake();

        $this->handOver();

        $this->actingAs($this->finance)
            ->post(route('procurements.finance.complete', $this->procurement))
            ->assertForbidden();

        // Optional steps (those "apabila ada") do not hold the payment back.
        $required = $this->procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan)
            ->whereHas('checklistItem', fn ($item) => $item->where('is_optional', false));

        (clone $required)->update(['is_completed' => true]);

        // Ticked without their papers, they still hold it back.
        $this->actingAs($this->finance)
            ->post(route('procurements.finance.complete', $this->procurement))
            ->assertForbidden();

        (clone $required)->get()->each(fn (ProcurementChecklist $step) => $step->files()->create([
            'path' => "checklist-berkas/{$step->id}/bukti.pdf",
            'file_name' => 'bukti.pdf',
        ]));

        $this->actingAs($this->finance)
            ->post(route('procurements.finance.complete', $this->procurement))
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertNotNull($this->procurement->payment_completed_at);
        $this->assertNotNull($this->procurement->completed_at);
        $this->assertSame(FinanceStageSeeder::CLOSED_STATUS, $this->procurement->progressStatus->name);
        $this->assertSame('selesai', $this->procurement->currentStage()['key']);

        Notification::assertSentTo($this->executor, ProcurementMilestone::class);

        $this->actingAs($this->finance)
            ->get(route('finance.index'))
            ->assertInertia(fn ($page) => $page->has('waiting', 0)->has('paid', 1));
    }

    public function test_only_keuangan_records_the_payment(): void
    {
        $this->handOver();
        $this->tick(ProcurementStage::Keuangan);

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->post(route('procurements.finance.complete', $this->procurement))
            ->assertForbidden();
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
