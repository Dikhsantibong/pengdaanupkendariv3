<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\UserRole;
use App\Models\BudgetSource;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\FinanceRequirementSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The whole journey of one job with the real master data and templates:
 * a staff member proposes it, their TL and then the Asman approve it,
 * TL ICC appoints the planner and approves the planning, TL Pengadaan
 * appoints the executor and approves the execution, Keuangan pays the
 * partner and the job is closed.
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(MasterDataSeeder::class);
        $this->seed(FinanceRequirementSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);
    }

    public function test_a_proposed_spk_job_runs_from_proposal_to_payment(): void
    {
        $division = WorkDirector::query()->where('name', 'Asman Operasi')->firstOrFail();

        $teamLeaderOfProposer = User::factory()->proposer($division)->create(['name' => 'Akmal Hidayat', 'position' => 'TL Operasi']);
        $proposer = User::factory()->proposer($division)->create(['name' => 'Rohmat Hidayat', 'team_leader_id' => $teamLeaderOfProposer->id]);
        $director = User::factory()->workDirector($division)->create(['name' => 'Asman Operasi']);
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $icc = User::factory()->teamLeaderIcc()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();
        $finance = User::factory()->create(['role' => UserRole::Keuangan, 'name' => 'Nova Sang Jaya']);

        // 1. A staff member proposes the work; its TOR is drafted from the template.
        $this->actingAs($proposer)
            ->post(route('procurements.store'), [
                'name' => 'Jasa Pemeliharaan Pompa Pendingin',
                'work_director_id' => $division->id,
                'budget_source_id' => BudgetSource::query()->firstOrFail()->id,
                'target_unit_ids' => [TargetUnit::query()->firstOrFail()->id],
                'prk_number' => '2026.AO.01.001, 2026.AO.01.002',
                'pr_po_number' => '1000012345',
                'coa_number' => '5110100000',
                'proposal_memo_number' => 'ND-021/USL/2026',
                'proposal_memo_date' => '2026-10-13',
                'progress_status_id' => ProgressStatus::defaultStatus()?->id,
                'tor_file' => UploadedFile::fake()->create('tor-ttd.pdf', 80, 'application/pdf'),
                'memo_file' => UploadedFile::fake()->create('nota-dinas.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->sole();
        $this->assertSame(ProposalState::MenungguPersetujuanTl, $procurement->proposal_state);
        $this->assertStringStartsWith('USL/', $procurement->number);
        $this->assertTrue($procurement->documents()->whereHas('documentType', fn ($q) => $q->where('code', 'tor'))->exists());

        // 2. Their TL approves first; only then may the Asman.
        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($teamLeaderOfProposer)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->refresh()->proposal_state);

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($procurement->refresh()->isProposalApproved());

        // 3. The approved proposal goes straight to TL ICC, who appoints the
        // planning PIC; TL Pengadaan may not. Documents wait for the
        // procurement data.
        $this->assertTrue($icc->notifications()->exists());

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), ['planner_id' => $planner->id])
            ->assertForbidden();

        $this->actingAs($icc)
            ->put(route('procurements.pic.update', $procurement), ['planner_id' => $planner->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($planner->id, $procurement->refresh()->planner_id);

        // Only the contract format is awaited: the method follows it.
        $this->assertSame(['Jenis Kontrak'], $procurement->refresh()->missingPlanningData());

        $hpe = DocumentType::query()->where('code', 'hpe')->firstOrFail();

        $this->actingAs($planner)
            ->post(route('procurements.documents.store', $procurement), ['document_type_id' => $hpe->id]);

        $this->assertFalse($procurement->documents()->where('document_type_id', $hpe->id)->exists());

        // 4. Procurement completes the data the division does not know.
        $spk = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();

        $this->actingAs($teamLeader)
            ->put(route('procurements.update', $procurement), [
                'contract_number_format_id' => $spk->id,
                'contract_type_id' => ContractType::query()->where('code', 'jasa')->firstOrFail()->id,
                'number' => 'KDD001.SPK/612/UPKD/2026',
                'name' => $procurement->name,
                'work_director_id' => $division->id,
                'target_unit_ids' => [$procurement->target_unit_id],
                'budget_source_id' => $procurement->budget_source_id,
                'prk_number' => $procurement->prk_number,
                'pr_po_number' => $procurement->pr_po_number,
                'coa_number' => $procurement->coa_number,
                'proposal_memo_number' => $procurement->proposal_memo_number,
                'proposal_memo_date' => '2026-10-13',
                'hpe_value' => 150_000_000,
                'progress_status_id' => $procurement->progress_status_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $procurement->refresh()->missingPlanningData());
        // The form no longer asks the method: the SPK format brings it.
        $this->assertSame('spk', $procurement->procurementMethod?->code);

        // 5. The planner sets the target date, completes every step and submits.
        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), ['target_completion_date' => '2026-12-31'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-12-31', $procurement->refresh()->target_completion_date?->toDateString());

        // The planner lists the calon mitra and their offers.
        foreach (['PT Mitra Pompa Sejahtera', 'CV Teknik Pendingin'] as $name) {
            $this->actingAs($planner)
                ->post(route('procurements.partner-candidates.store', $procurement), [
                    'name' => $name,
                    'director_name' => 'Direktur '.$name,
                    'address' => 'Jl. Ahmad Yani, Kendari',
                    'quotation_number' => '012/SPH/2026',
                    'quotation_date' => '2026-09-01',
                    'quotation_value' => 245_000_000,
                    'quotation_file' => UploadedFile::fake()->create('penawaran.pdf', 40, 'application/pdf'),
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertCount(2, $procurement->partnerCandidates()->get());

        $this->completeStage($procurement, ProcurementStage::Perencanaan, $planner);

        $this->actingAs($planner)
            ->post(route('procurements.approval.store', $procurement))
            ->assertSessionHasNoErrors();

        $this->assertSame(PlanningApprovalState::MenungguPersetujuan, $procurement->refresh()->planning_approval_state);

        // 6. ICC approves the planning; only then does TL Pengadaan appoint
        // the execution PIC.
        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), ['executor_id' => $executor->id])
            ->assertForbidden();

        $this->actingAs($icc)
            ->put(route('procurements.approval.update', $procurement), ['approved' => true])
            ->assertSessionHasNoErrors();

        $this->actingAs($icc)
            ->put(route('procurements.pic.update', $procurement), ['executor_id' => $executor->id])
            ->assertForbidden();

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => $planner->id,
                'executor_id' => $executor->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($executor->id, $procurement->refresh()->executor_id);

        // 7. The executor cannot submit before every execution step is done.
        $this->actingAs($executor)
            ->post(route('procurements.execution-approval.store', $procurement))
            ->assertForbidden();

        $this->completeStage($procurement->refresh(), ProcurementStage::Pelaksanaan, $executor);

        $this->actingAs($executor)
            ->post(route('procurements.execution-approval.store', $procurement))
            ->assertSessionHasNoErrors();

        $this->assertSame(PlanningApprovalState::MenungguPersetujuan, $procurement->refresh()->execution_approval_state);
        $this->assertFalse($procurement->isInFinance());

        // 8. TL Pengadaan approves the execution into Penyerahan and Pembayaran.
        $this->actingAs($executor)
            ->put(route('procurements.execution-approval.update', $procurement), ['approved' => true])
            ->assertForbidden();

        $this->actingAs($teamLeader)
            ->put(route('procurements.execution-approval.update', $procurement), ['approved' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($procurement->refresh()->isInFinance());
        $this->assertSame('CV Teknik Pendingin', $procurement->partner_name);

        // 9. Keuangan files the invoice papers, records the payment and closes it.
        $this->actingAs($finance)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('waiting', 1));

        $this->actingAs($finance)
            ->post(route('procurements.finance.complete', $procurement))
            ->assertForbidden();

        $this->completeStage($procurement, ProcurementStage::Keuangan, $finance);

        $this->actingAs($finance)
            ->post(route('procurements.finance.complete', $procurement))
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertNotNull($procurement->payment_completed_at);
        $this->assertNotNull($procurement->completed_at);
        $this->assertSame('Selesai', $procurement->progressStatus->name);
        $this->assertSame('selesai', $procurement->currentStage()['key']);

        // The proposing division heard about each stage.
        $this->assertGreaterThanOrEqual(3, $proposer->notifications()->count());

        // Everyone involved can still follow it.
        foreach ([$proposer, $teamLeaderOfProposer, $director, $planner, $executor, $finance] as $user) {
            $this->actingAs($user)->get(route('procurements.show', $procurement))->assertOk();
        }
    }

    /**
     * File every document of each step of a stage, fill its data and tick it.
     */
    protected function completeStage(Procurement $procurement, ProcurementStage $stage, User $pic): void
    {
        $checklists = $procurement->checklists()
            ->where('stage', $stage)
            ->with('checklistItem.documentTypes')
            ->get();

        $this->assertNotEmpty($checklists, "Tahap {$stage->value} tidak punya checklist.");

        foreach ($checklists as $checklist) {
            $this->fileDocuments($procurement, $checklist, $pic);
            $this->fillInput($procurement, $checklist, $pic);

            // A Penyerahan / Pembayaran requirement is an upload.
            if ($checklist->needsOwnUpload()) {
                $this->actingAs($pic)
                    ->post(route('procurements.checklists.files.store', [$procurement, $checklist]), [
                        'files' => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')],
                    ])
                    ->assertSessionHasNoErrors();
            }

            $this->actingAs($pic)
                ->put(route('procurements.checklists.update', [$procurement, $checklist]), ['is_completed' => true])
                ->assertSessionHasNoErrors();

            $this->assertTrue($checklist->refresh()->is_completed, "{$checklist->checklistItem->name} belum tercentang.");
        }
    }

    /**
     * Upload, or generate and sign, every document a step asks for.
     */
    protected function fileDocuments(Procurement $procurement, ProcurementChecklist $checklist, User $pic): void
    {
        foreach ($checklist->checklistItem->documentTypes as $type) {
            /** @var DocumentType $type */
            $scan = [UploadedFile::fake()->create("{$type->code}.pdf", 50, 'application/pdf')];

            if ($type->upload_only) {
                $this->actingAs($pic)
                    ->post(route('procurements.documents.upload', $procurement), [
                        'document_type_id' => $type->id,
                        'files' => $scan,
                    ])
                    ->assertSessionHasNoErrors();

                continue;
            }

            $document = $procurement->documents()->where('document_type_id', $type->id)->latest('id')->first();

            if ($document === null) {
                $this->actingAs($pic)
                    ->post(route('procurements.documents.store', $procurement), ['document_type_id' => $type->id])
                    ->assertSessionHasNoErrors();

                $document = $procurement->documents()->where('document_type_id', $type->id)->latest('id')->first();
            }

            $this->assertNotNull($document, "Dokumen {$type->name} gagal digenerate (template tidak ada?).");

            $this->actingAs($pic)
                ->post(route('procurements.documents.signed.store', [$procurement, $document]), ['files' => $scan])
                ->assertSessionHasNoErrors();
        }
    }

    /**
     * Fill in the data a step asks for.
     */
    protected function fillInput(Procurement $procurement, ProcurementChecklist $checklist, User $pic): void
    {
        $values = match ($checklist->checklistItem->input_kind?->value) {
            'contract_period' => ['execution_start_date' => '2026-11-01', 'execution_duration_days' => 60],
            'warranty' => ['warranty_months' => 6],
            'procurement_memo' => ['icc_memo_number' => 'ND-031/ICC/2026', 'icc_memo_date' => '2026-09-10'],
            // The number of each planning document.
            'rks_number' => ['rks_number' => '031.RKS-SPK/612/UPKD/2026'],
            'hpe_number' => ['hpe_number' => 'ND-031/UPKD/2026-HPE(A)'],
            'upb_number' => ['upb_number' => 'ND-031/UPKD/2026'],
            // The executor picks the partner from the calon mitra.
            'bank_account' => [
                'partner_candidate_id' => $procurement->partnerCandidates()->where('name', 'CV Teknik Pendingin')->value('id'),
                'bank_account_number' => '123-456-789',
                'bank_name' => 'Bank Mandiri',
                'bank_account_holder' => 'CV Teknik Pendingin',
            ],
            default => null,
        };

        if ($values === null) {
            return;
        }

        $this->actingAs($pic)
            ->put(route('procurements.checklists.input', [$procurement, $checklist]), $values)
            ->assertSessionHasNoErrors();
    }
}
