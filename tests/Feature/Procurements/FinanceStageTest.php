<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Notifications\ProcurementAssigned;
use App\Notifications\ProcurementMilestone;
use App\Services\ProcurementService;
use Database\Seeders\FinanceStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * After execution the job goes to Keuangan, who pays the partner and closes
 * the procurement.
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

        $this->procurement = Procurement::factory()
            ->planningApproved()
            ->executedBy($this->executor)
            ->create(['procurement_method_id' => null]);

        app(ProcurementService::class)->syncChecklists($this->procurement);
    }

    public function test_the_default_keuangan_steps_are_master_data(): void
    {
        $names = ChecklistItem::query()
            ->forStage(ProcurementStage::Keuangan)
            ->ordered()
            ->pluck('name')
            ->all();

        $this->assertSame(array_column(FinanceStageSeeder::STEPS, 0), $names);

        // Seeding again adds nothing new.
        $this->seed(FinanceStageSeeder::class);
        $this->assertSame(count(FinanceStageSeeder::STEPS), ChecklistItem::query()->forStage(ProcurementStage::Keuangan)->count());
    }

    public function test_the_executor_hands_over_only_a_finished_execution(): void
    {
        Notification::fake();

        $this->actingAs($this->executor)
            ->post(route('procurements.finance.submit', $this->procurement))
            ->assertForbidden();

        $this->tick(ProcurementStage::Pelaksanaan);

        $this->actingAs($this->executor)
            ->post(route('procurements.finance.submit', $this->procurement))
            ->assertRedirect();

        $this->procurement->refresh();
        $this->assertTrue($this->procurement->isInFinance());
        $this->assertSame($this->executor->id, $this->procurement->finance_submitted_by);

        Notification::assertSentTo($this->finance, ProcurementMilestone::class);

        // Execution is final now; nobody hands it over twice.
        $this->assertFalse($this->executor->can('updateExecutionChecklist', $this->procurement));
        $this->actingAs($this->executor)
            ->post(route('procurements.finance.submit', $this->procurement))
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
                ->has('checklists.keuangan', count(FinanceStageSeeder::STEPS)));

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

        // Optional steps (Faktur Pajak) do not hold the payment back.
        $this->procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan)
            ->whereHas('checklistItem', fn ($item) => $item->where('is_optional', false))
            ->update(['is_completed' => true]);

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
     * Finish execution and hand the job to Keuangan.
     */
    protected function handOver(): void
    {
        $this->tick(ProcurementStage::Pelaksanaan);

        $this->actingAs($this->executor)
            ->post(route('procurements.finance.submit', $this->procurement))
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
