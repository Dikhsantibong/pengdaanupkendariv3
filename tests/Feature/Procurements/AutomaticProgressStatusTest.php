<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProposalState;
use App\Enums\SystemStatus;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The progress status follows the job by itself: the proposal's papers and
 * approvals, the planning PIC and papers, ICC's decision, the execution PIC
 * and papers, TL Pengadaan's decision, then Keuangan.
 */
class AutomaticProgressStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->user = User::factory()->administrator()->create();
    }

    public function test_a_proposal_moves_through_its_papers_and_approval(): void
    {
        $procurement = Procurement::factory()->create([
            'proposal_submitted_at' => now(),
            'proposal_state' => ProposalState::MenungguPersetujuanTl,
        ]);

        $this->assertStatus(SystemStatus::PenyusunanTor, $procurement);

        // Drafted but not yet signed: still Penyusunan TOR.
        $tor = $this->document($procurement, 'tor');
        $this->assertStatus(SystemStatus::PenyusunanTor, $procurement);

        $this->sign($tor);
        $this->assertStatus(SystemStatus::DraftNodisUsulan, $procurement);

        $this->sign($this->document($procurement, 'nota-dinas-usulan'));
        $this->assertStatus(SystemStatus::ApprovalUsulan, $procurement);

        $procurement->forceFill(['proposal_state' => ProposalState::Dikembalikan])->save();
        $this->assertStatus(SystemStatus::RevisiUsulan, $procurement);
    }

    public function test_an_spk_planning_follows_its_pic_papers_and_icc(): void
    {
        $procurement = $this->onFormat('SPK', ['proposal_submitted_at' => now()]);

        $this->assertStatus(SystemStatus::InisiasiRendan, $procurement);

        $procurement->forceFill(['planner_id' => User::factory()->planner()->create()->id])->save();
        $this->assertStatus(SystemStatus::PenyusunanRks, $procurement);

        foreach (['rks', 'upb', 'hpe'] as $code) {
            $this->document($procurement, $code);
        }

        // The RAB still has to be filed.
        $this->assertStatus(SystemStatus::PenyusunanRks, $procurement);

        $this->sign($this->document($procurement, 'rab'));
        $this->assertStatus(SystemStatus::MenungguPenawaran, $procurement);

        $this->sign($this->document($procurement, 'penawaran'));
        $this->assertStatus(SystemStatus::DraftNodisPengadaan, $procurement);

        $procurement->forceFill(['planning_approval_state' => PlanningApprovalState::MenungguPersetujuan])->save();
        $this->assertStatus(SystemStatus::ApprovalIcc, $procurement);

        $procurement->forceFill(['planning_approval_state' => PlanningApprovalState::Ditolak])->save();
        $this->assertStatus(SystemStatus::RevisiPerencanaan, $procurement);
    }

    public function test_an_sppl_planning_waits_on_its_nota_dinas_ke_laksdan(): void
    {
        $procurement = $this->onFormat('SPPL');

        $this->assertStatus(SystemStatus::InisiasiRendan, $procurement);

        $procurement->forceFill(['planner_id' => User::factory()->planner()->create()->id])->save();
        $this->assertStatus(SystemStatus::DraftNodisLaksdan, $procurement);
    }

    public function test_an_spk_execution_runs_to_terkontrak_and_selesai(): void
    {
        $procurement = $this->onFormat('SPK', ['planning_approval_state' => PlanningApprovalState::Disetujui]);

        $this->assertStatus(SystemStatus::InisiasiLaksdan, $procurement);

        $procurement->forceFill(['executor_id' => User::factory()->executor()->create()->id])->save();
        $this->assertStatus(SystemStatus::PenyusunanHps, $procurement);

        $this->sign($this->document($procurement, 'penyusunan-hps'));
        $this->assertStatus(SystemStatus::ProsesSmartScm, $procurement);

        $this->sign($this->document($procurement, 'proses-smart-scm'));
        // Every Berita Acara has to be filed too.
        $this->assertStatus(SystemStatus::ProsesSmartScm, $procurement);

        foreach (['ba-aanwijzing', 'lampiran-bapp', 'ba-evaluasi-teknis', 'ba-evaluasi-harga', 'ba-hasil-evaluasi', 'ba-klarifikasi'] as $code) {
            $this->sign($this->document($procurement, $code));
        }

        $this->assertStatus(SystemStatus::PenyusunanKontrak, $procurement);

        $procurement->forceFill(['execution_approval_state' => PlanningApprovalState::MenungguPersetujuan])->save();
        $this->assertStatus(SystemStatus::ApprovalTlPengadaan, $procurement);

        $procurement->forceFill(['execution_approval_state' => PlanningApprovalState::Ditolak])->save();
        $this->assertStatus(SystemStatus::RevisiPelaksanaan, $procurement);

        $procurement->forceFill([
            'execution_approval_state' => PlanningApprovalState::Disetujui,
            'finance_submitted_at' => now(),
        ])->save();
        $this->assertStatus(SystemStatus::Terkontrak, $procurement);

        $procurement->forceFill(['payment_completed_at' => now(), 'completed_at' => now()])->save();
        $this->assertStatus(SystemStatus::Selesai, $procurement);
    }

    public function test_an_sppl_execution_drafts_its_surat_pesanan(): void
    {
        $procurement = $this->onFormat('SPPL', [
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'executor_id' => User::factory()->executor()->create()->id,
        ]);

        $this->assertStatus(SystemStatus::PenyusunanSuratPesanan, $procurement);
    }

    public function test_removing_a_signed_copy_moves_the_status_back(): void
    {
        $procurement = $this->onFormat('SPK', [
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'executor_id' => User::factory()->executor()->create()->id,
        ]);

        $hps = $this->document($procurement, 'penyusunan-hps');
        $upload = $this->sign($hps);
        $this->assertStatus(SystemStatus::ProsesSmartScm, $procurement);

        $hps->signedUploads()->whereKey($upload)->first()?->delete();
        $this->assertStatus(SystemStatus::PenyusunanHps, $procurement);
    }

    public function test_the_form_no_longer_asks_for_a_status(): void
    {
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('procurements.create'))
            ->assertInertia(fn ($page) => $page
                ->missing('options.progressStatuses')
                ->missing('options.defaultProgressStatusId'));
    }

    /**
     * A procurement on a seeded contract format, with its checklist.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function onFormat(string $code, array $attributes = []): Procurement
    {
        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $code)->value('id'),
            'procurement_method_id' => null,
            'work_category' => $code === 'SPPL' ? null : 'jasa',
            ...$attributes,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement->refresh());

        return $procurement;
    }

    /**
     * Draw a document of a type for the procurement.
     */
    protected function document(Procurement $procurement, string $code): ProcurementDocument
    {
        $type = DocumentType::query()->where('code', $code)->firstOrFail();

        return $procurement->documents()->create([
            'document_type_id' => $type->id,
            'title' => $type->name,
            'file_name' => "{$code}.pdf",
            'template_version' => 1,
            'rendered_body' => '<p>Isi</p>',
            'generated_by' => $this->user->id,
            'generated_at' => now(),
        ]);
    }

    /**
     * File a signed copy of a document; returns the upload id.
     */
    protected function sign(ProcurementDocument $document): int
    {
        return $document->signedUploads()->create([
            'path' => "documents/signed/{$document->id}/ttd.pdf",
            'file_name' => 'ttd.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $this->user->id,
        ])->id;
    }

    protected function assertStatus(SystemStatus $expected, Procurement $procurement): void
    {
        $this->assertSame($expected, $procurement->refresh()->progressStatus->systemStatus());
    }
}
