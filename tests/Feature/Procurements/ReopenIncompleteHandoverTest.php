<?php

namespace Tests\Feature\Procurements;

use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\FinanceRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contracted jobs counted as handed over when the Penyerahan approval came,
 * without their hand-over papers, go back to Penyerahan: the execution PIC
 * files them, and Pembayaran waits for TL Pengadaan's approval.
 */
class ReopenIncompleteHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SPK', 'PJ', 'SPPL'] as $code) {
            ContractNumberFormat::factory()->create(['code' => $code]);
        }

        $this->seed(FinanceRequirementSeeder::class);
    }

    public function test_a_job_without_its_handover_papers_goes_back_to_penyerahan(): void
    {
        $executor = User::factory()->executor()->create();
        $incomplete = $this->contracted($executor);
        $complete = $this->contracted($executor);
        $this->file($complete);
        $submitted = $this->contracted($executor, ['payment_approval_state' => 'menunggu_tl']);

        $this->migration()->up();

        $incomplete->refresh();
        $this->assertSame(PlanningApprovalState::BelumDiajukan, $incomplete->handover_approval_state);
        $this->assertNull($incomplete->handover_reviewed_at);
        $this->assertFalse($incomplete->isInPayment());

        // The executor files and submits it; Keuangan waits.
        $this->assertTrue($executor->can('updateHandoverChecklist', $incomplete));
        $this->assertFalse(User::factory()->create(['role' => 'keuangan'])->can('updatePaymentChecklist', $incomplete));

        // Complete papers, or a payment already submitted, stay as they were.
        $this->assertSame(PlanningApprovalState::Disetujui, $complete->refresh()->handover_approval_state);
        $this->assertSame(PlanningApprovalState::Disetujui, $submitted->refresh()->handover_approval_state);
    }

    /**
     * A contracted SPK Jasa job counted as handed over.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function contracted(User $executor, array $attributes = []): Procurement
    {
        $procurement = Procurement::factory()->planningApproved()->executedBy($executor)->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'work_category' => 'jasa',
            'execution_approval_state' => 'disetujui',
            'finance_submitted_at' => now(),
            'handover_approval_state' => 'disetujui',
            'handover_reviewed_at' => now(),
            ...$attributes,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        return $procurement;
    }

    /**
     * File every mandatory hand-over paper.
     */
    protected function file(Procurement $procurement): void
    {
        $procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan)
            ->with('checklistItem')
            ->get()
            ->filter(fn (ProcurementChecklist $step): bool => $step->financeSection() === FinanceSection::Penyerahan && ! $step->checklistItem->is_optional)
            ->each(fn (ProcurementChecklist $step) => $step->files()->create([
                'path' => "checklist-berkas/{$step->id}/bast.pdf",
                'file_name' => 'bast.pdf',
            ]));
    }

    /**
     * The data migration under test.
     */
    protected function migration(): object
    {
        return require database_path('migrations/2026_10_27_135016_reopen_incomplete_handovers_of_running_jobs.php');
    }
}
