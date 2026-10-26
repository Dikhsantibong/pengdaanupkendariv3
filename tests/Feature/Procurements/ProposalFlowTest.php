<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\ProposalState;
use App\Models\BudgetSource;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProposalReviewed;
use App\Notifications\ProposalSubmitted;
use App\Support\ProposalDocuments;
use Database\Seeders\DivisionUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staff propose work, the Direksi Pekerjaan of their division approves it,
 * and only then does it reach the procurement planners.
 */
class ProposalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected WorkDirector $division;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->division = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
    }

    public function test_a_proposer_sees_the_proposal_form(): void
    {
        $proposer = User::factory()->proposer($this->division)->create();

        $this->actingAs($proposer)
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('procurements/create')
                ->where('proposal.enabled', true)
                ->where('proposal.workDirectorId', $this->division->id)
            );
    }

    public function test_a_proposal_waits_for_the_division_director(): void
    {
        Notification::fake();

        $proposer = User::factory()->proposer($this->division)->create();
        $director = User::factory()->workDirector($this->division)->create();
        $otherDirector = User::factory()->workDirector(WorkDirector::factory()->create())->create();

        $this->actingAs($proposer)
            ->post(route('procurements.store'), [
                ...$this->proposalPayload(),
                // A proposer cannot raise work for another division.
                'work_director_id' => WorkDirector::factory()->create()->id,
                'attachments' => [
                    UploadedFile::fake()->create('spesifikasi.pdf', 120, 'application/pdf'),
                    UploadedFile::fake()->image('foto-kondisi.jpg'),
                ],
            ])
            ->assertRedirect();

        $procurement = Procurement::query()->firstOrFail();

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->proposal_state);
        $this->assertSame($this->division->id, $procurement->work_director_id);
        $this->assertNotNull($procurement->proposal_submitted_at);
        $this->assertCount(2, $procurement->attachments);
        Storage::disk('local')->assertExists($procurement->attachments->first()->path);

        Notification::assertSentTo($director, ProposalSubmitted::class);
        Notification::assertNotSentTo($otherDirector, ProposalSubmitted::class);

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::UsulanDiajukan->value,
        ]);
    }

    public function test_a_proposal_asks_for_every_listed_field_except_sr_and_wo(): void
    {
        $proposer = User::factory()->proposer($this->division)->create();

        $this->actingAs($proposer)
            ->post(route('procurements.store'), [
                ...$this->proposalPayload(),
                'prk_number' => '',
                'pr_po_number' => '',
                'coa_number' => '',
                'proposal_memo_number' => '',
                'proposal_memo_date' => '',
                'budget_source_id' => null,
            ])
            ->assertSessionHasErrors([
                'prk_number', 'pr_po_number', 'coa_number',
                'proposal_memo_number', 'proposal_memo_date', 'budget_source_id',
            ])
            ->assertSessionDoesntHaveErrors(['sr_number', 'wo_number', 'procurement_method_id', 'hpe_value']);

        $this->assertDatabaseCount('procurements', 0);

        // Method and value are left to procurement.
        $this->actingAs($proposer)
            ->post(route('procurements.store'), $this->proposalPayload())
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->firstOrFail();
        $this->assertNull($procurement->procurement_method_id);
        $this->assertSame('0.00', (string) $procurement->hpe_value);
    }

    public function test_reference_numbers_may_hold_several_values(): void
    {
        $this->actingAs(User::factory()->proposer($this->division)->create())
            ->post(route('procurements.store'), [
                ...$this->proposalPayload(),
                'prk_number' => ['2026.AO.01.001', ' 2026.AO.01.002 ', '', '2026.AO.01.001'],
                'pr_po_number' => '1000012345, 1000012346',
                'sr_number' => 'SR-1; SR-2',
                'wo_number' => '',
            ])
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->firstOrFail();

        $this->assertSame('2026.AO.01.001, 2026.AO.01.002', $procurement->prk_number);
        $this->assertSame('1000012345, 1000012346', $procurement->pr_po_number);
        $this->assertSame('SR-1, SR-2', $procurement->sr_number);
        $this->assertNull($procurement->wo_number);
    }

    public function test_the_tor_is_generated_with_the_proposal_and_written_by_the_proposer(): void
    {
        $tor = $this->torTemplate();
        $proposer = User::factory()->proposer($this->division)->create();

        $this->actingAs($proposer)
            ->post(route('procurements.store'), [...$this->proposalPayload(), 'sr_number' => 'SR-9'])
            ->assertRedirect();

        $procurement = Procurement::query()->firstOrFail();
        $document = $procurement->documents()->where('document_type_id', $tor->id)->sole();

        $this->assertStringContainsString('SR-9', strip_tags($document->rendered_body));

        $this->actingAs($proposer)
            ->get(route('proposals.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('proposal.documents', 1)
                ->where('proposal.documents.0.id', $document->id)
                ->where('can.editDocuments', true)
            );

        $this->actingAs($proposer)
            ->get(route('procurements.documents.edit', [$procurement, $document]))
            ->assertOk();

        $this->actingAs(User::factory()->workDirector($this->division)->create())
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve']);

        // Once approved the TOR belongs to procurement.
        $this->actingAs($proposer)
            ->get(route('procurements.documents.edit', [$procurement, $document]))
            ->assertForbidden();
    }

    public function test_a_pending_proposal_stays_out_of_planning(): void
    {
        $procurement = $this->proposal();
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();

        $this->actingAs($teamLeader)
            ->get(route('pic-assignments.index'))
            ->assertOk()
            ->assertDontSee($procurement->name);

        $this->actingAs($teamLeader)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertDontSee($procurement->name);

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => User::factory()->planner()->create()->id,
            ])
            ->assertForbidden();
    }

    public function test_the_director_reviews_and_approves_the_proposal(): void
    {
        Notification::fake();

        $procurement = $this->proposal();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->get(route('proposals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('proposals/index')
                ->where('canApprove', true)
                ->has('awaiting', 1)
                ->where('awaiting.0.id', $procurement->id)
            );

        $this->actingAs($director)
            ->get(route('proposals.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('proposals/show')
                ->where('can.approve', true)
                ->where('can.revise', false)
            );

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertRedirect(route('proposals.index'));

        $procurement->refresh();

        $this->assertSame(ProposalState::Disetujui, $procurement->proposal_state);
        $this->assertSame($director->id, $procurement->proposal_reviewed_by);
        Notification::assertSentTo($procurement->creator, ProposalReviewed::class);

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('pic-assignments.index'))
            ->assertOk()
            ->assertSee($procurement->name);
    }

    public function test_a_returned_proposal_needs_a_note_and_can_be_resubmitted(): void
    {
        Notification::fake();

        $procurement = $this->proposal();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'return'])
            ->assertSessionHasErrors('note');

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), [
                'decision' => 'return',
                'note' => 'Lengkapi spesifikasi teknis.',
            ])
            ->assertRedirect();

        $procurement->refresh();
        $this->assertSame(ProposalState::Dikembalikan, $procurement->proposal_state);
        $this->assertSame('Lengkapi spesifikasi teknis.', $procurement->proposal_review_note);

        $proposer = $procurement->creator;

        $this->actingAs($proposer)
            ->post(route('proposals.attachments.store', $procurement), [
                'attachments' => [UploadedFile::fake()->image('foto.jpg')],
            ])
            ->assertRedirect();

        $this->assertCount(1, $procurement->attachments()->get());

        $this->actingAs($proposer)
            ->post(route('proposals.resubmit', $procurement))
            ->assertRedirect();

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->refresh()->proposal_state);
        Notification::assertSentTo($director, ProposalSubmitted::class);
    }

    public function test_a_director_of_another_division_cannot_decide(): void
    {
        $procurement = $this->proposal();
        $outsider = User::factory()->workDirector(WorkDirector::factory()->create())->create();

        $this->actingAs($outsider)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('proposals.show', $procurement))
            ->assertForbidden();

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->refresh()->proposal_state);
    }

    public function test_the_proposer_cannot_approve_their_own_proposal(): void
    {
        $procurement = $this->proposal();

        $this->actingAs($procurement->creator)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_a_director_proposing_for_their_division_is_approved_at_once(): void
    {
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->post(route('procurements.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(ProposalState::Disetujui, Procurement::query()->firstOrFail()->proposal_state);
    }

    public function test_procurement_staff_still_create_procurements_directly(): void
    {
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->post(route('procurements.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(ProposalState::Disetujui, Procurement::query()->firstOrFail()->proposal_state);
    }

    public function test_the_dashboard_lists_the_proposals_waiting_on_the_director(): void
    {
        $procurement = $this->proposal();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tasks.proposals.total', 1)
                ->where('tasks.proposals.items.0.id', $procurement->id)
            );

        $this->actingAs($procurement->creator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tasks.proposals', null)
                ->where('tasks.myProposals.total', 1)
            );
    }

    public function test_the_division_seeder_creates_proposers_and_directors_once(): void
    {
        $this->seed(DivisionUserSeeder::class);

        $count = User::query()->count();
        $this->assertSame(count(DivisionUserSeeder::emails()), $count);

        $director = User::query()->where('email', 'asman.operasi@upkendari.go.id')->firstOrFail();
        $proposer = User::query()->where('email', 'akmal.hidayat@upkendari.go.id')->firstOrFail();

        $this->assertSame($director->work_director_id, $proposer->work_director_id);
        $this->assertTrue($proposer->can('create', Procurement::class));

        $this->seed(DivisionUserSeeder::class);

        $this->assertSame($count, User::query()->count());
    }

    public function test_a_proposal_is_only_approved_once_its_tor_and_nota_dinas_are_filed(): void
    {
        $this->proposalDocumentTypes();
        $proposer = User::factory()->proposer($this->division)->create();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($proposer)
            ->post(route('procurements.store'), collect($this->proposalPayload())->except(['tor_file', 'memo_file'])->all())
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->sole();

        $this->actingAs($director)
            ->get(route('proposals.show', $procurement))
            ->assertInertia(fn ($page) => $page
                ->where('can.approve', false)
                ->where('proposal.missing_documents', ['TOR', 'Nota Dinas Usulan']));

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();

        // The proposer uploads the Nota Dinas, then the signed TOR.
        $memo = DocumentType::query()->where('code', 'nota-dinas-usulan')->firstOrFail();

        $this->actingAs($proposer)
            ->post(route('procurements.documents.upload', $procurement), [
                'document_type_id' => $memo->id,
                'files' => [UploadedFile::fake()->create('nota-dinas.pdf', 40, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['TOR'], ProposalDocuments::missing($procurement->refresh()));

        app(ProposalDocuments::class)->file(
            $procurement,
            UploadedFile::fake()->create('tor-ttd.pdf', 80, 'application/pdf'),
            null,
            $proposer,
        );

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement->refresh()), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertTrue($procurement->refresh()->isProposalApproved());
    }

    public function test_the_proposal_documents_have_a_tab_named_after_the_proposer(): void
    {
        $this->proposalDocumentTypes();
        $procurement = $this->proposal();
        $proposer = $procurement->creator;

        $this->actingAs(User::factory()->workDirector($this->division)->create())
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve']);

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('detail.proposal_documents.proposer', $proposer->name)
                ->has('detail.proposal_documents.documents', 2)
                ->where('detail.proposal_documents.documents.0.type_name', 'TOR')
                ->where('detail.proposal_documents.documents.0.is_signed', true)
                ->where('detail.proposal_documents.documents.1.type_name', 'Nota Dinas Usulan')
                ->where('detail.proposal_documents.documents.1.is_signed', true));

        // A procurement registered directly files them there too, under the
        // name of whoever registered it.
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->post(route('procurements.store'), $this->payload());

        $direct = Procurement::query()->latest('id')->firstOrFail();

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('procurements.show', $direct))
            ->assertInertia(fn ($page) => $page
                ->where('detail.proposal_documents.proposer', $direct->creator->name)
                ->has('detail.proposal_documents.documents', 2));
    }

    public function test_the_proposer_files_supporting_documents_beside_the_tor_and_nota_dinas(): void
    {
        $proposer = User::factory()->proposer($this->division)->create();

        $this->actingAs($proposer)
            ->post(route('procurements.store'), [
                ...$this->proposalPayload(),
                'attachments' => [UploadedFile::fake()->create('spesifikasi.pdf', 120, 'application/pdf')],
            ])
            ->assertRedirect();

        $procurement = Procurement::query()->latest('id')->firstOrFail();

        // More can be added later, Word and Excel included.
        $this->actingAs($proposer)
            ->post(route('proposals.attachments.store', $procurement), [
                'attachments' => [
                    UploadedFile::fake()->create('rab.xlsx', 30, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                    UploadedFile::fake()->image('foto-kondisi.jpg'),
                ],
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', '2 dokumen pendukung ditambahkan.');

        $this->assertCount(3, $procurement->attachments()->get());

        $this->actingAs($proposer)
            ->get(route('proposals.show', $procurement))
            ->assertInertia(fn ($page) => $page
                ->has('proposal.attachments', 3)
                ->where('proposal.attachments.0.file_name', 'spesifikasi.pdf')
                ->where('proposal.attachments.0.previewable', true)
                ->where('proposal.attachments.0.uploaded_by', $proposer->name)
                ->where('can.revise', true));

        // The procurement tab of the proposer lists them too, but only the
        // proposer manages them.
        $this->actingAs($proposer)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn ($page) => $page
                ->has('detail.proposal_documents.supporting', 3)
                ->where('detail.proposal_documents.can_manage_supporting', true));

        $this->actingAs(User::factory()->workDirector($this->division)->create())
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn ($page) => $page
                ->has('detail.proposal_documents.supporting', 3)
                ->where('detail.proposal_documents.can_manage_supporting', false));

        $this->actingAs($proposer)
            ->delete(route('proposals.attachments.destroy', [$procurement, $procurement->attachments()->firstOrFail()]))
            ->assertRedirect();

        $this->assertCount(2, $procurement->attachments()->get());
    }

    public function test_supporting_documents_are_optional_limited_and_closed_after_approval(): void
    {
        $procurement = $this->proposal();
        $proposer = $procurement->creator;

        $this->assertCount(0, $procurement->attachments);
        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->proposal_state);

        $this->actingAs($proposer)
            ->post(route('proposals.attachments.store', $procurement), [
                'attachments' => [UploadedFile::fake()->create('program.exe', 10, 'application/octet-stream')],
            ])
            ->assertSessionHasErrors('attachments.0');

        $this->actingAs(User::factory()->proposer($this->division)->create())
            ->post(route('proposals.attachments.store', $procurement), [
                'attachments' => [UploadedFile::fake()->image('foto.jpg')],
            ])
            ->assertForbidden();

        $this->actingAs(User::factory()->workDirector($this->division)->create())
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve']);

        $this->actingAs($proposer)
            ->post(route('proposals.attachments.store', $procurement), [
                'attachments' => [UploadedFile::fake()->image('foto.jpg')],
            ])
            ->assertForbidden();

        $this->assertCount(0, $procurement->attachments()->get());
    }

    public function test_planning_is_submitted_only_once_the_tor_and_nota_dinas_are_filed(): void
    {
        $this->proposalDocumentTypes();

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        // No checklist step asks for them any more, yet they are still needed.
        $this->assertFalse($planner->can('submitPlanning', $procurement));

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn ($page) => $page->where('detail.pending_required_planning', [
                'TOR (tab dokumen pengguna)',
                'Nota Dinas Usulan (tab dokumen pengguna)',
            ]));

        app(ProposalDocuments::class)->file(
            $procurement,
            UploadedFile::fake()->create('tor-ttd.pdf', 80, 'application/pdf'),
            UploadedFile::fake()->create('nota-dinas.pdf', 40, 'application/pdf'),
            $planner,
        );

        $this->assertTrue($planner->can('submitPlanning', $procurement->refresh()));
    }

    /**
     * The TOR and Nota Dinas Usulan document types, as the master data has them.
     */
    protected function proposalDocumentTypes(): void
    {
        DocumentType::factory()->create(['code' => 'tor', 'name' => 'TOR (Term of Reference)']);
        DocumentType::factory()->create(['code' => 'nota-dinas-usulan', 'name' => 'Nota Dinas Usulan', 'upload_only' => true]);
    }

    /**
     * A pending proposal raised by a proposer of the test division.
     */
    protected function proposal(): Procurement
    {
        $proposer = User::factory()->proposer($this->division)->create();

        $this->actingAs($proposer)->post(route('procurements.store'), $this->proposalPayload());

        return Procurement::query()->latest('id')->firstOrFail();
    }

    /**
     * A valid create payload.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'name' => 'Penggantian Pompa Pendingin',
            'work_director_id' => $this->division->id,
            'target_unit_ids' => [TargetUnit::factory()->create()->id],
            'procurement_method_id' => ProcurementMethod::factory()->create()->id,
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'hpe_value' => 25_000_000,
            'progress_status_id' => ProgressStatus::factory()->asDefault()->create()->id,
            'prk_number' => '2026.AO.01.001',
            'pr_po_number' => '1000012345',
            'coa_number' => '5110100000',
            'proposal_memo_number' => 'ND-021/USL/2026',
            'proposal_memo_date' => '2026-10-13',
            // The two documents a proposal files.
            'tor_file' => UploadedFile::fake()->create('tor-ttd.pdf', 80, 'application/pdf'),
            'memo_file' => UploadedFile::fake()->create('nota-dinas.pdf', 40, 'application/pdf'),
        ];
    }

    /**
     * Only what the division fills in on the proposal form.
     *
     * @return array<string, mixed>
     */
    protected function proposalPayload(): array
    {
        return collect($this->payload())->except(['procurement_method_id', 'hpe_value'])->all();
    }

    /**
     * An active TOR template for the generated proposal TOR.
     */
    protected function torTemplate(): DocumentType
    {
        $tor = DocumentType::factory()->create(['code' => 'tor', 'name' => 'TOR (Term of Reference)']);

        DocumentTemplate::factory()->create([
            'document_type_id' => $tor->id,
            'procurement_method_id' => null,
            'body' => '<h1>TOR {{nama_pengadaan}}</h1><p>PRK {{nomor_prk}} SR {{nomor_sr}}</p>',
        ]);

        return $tor;
    }
}
