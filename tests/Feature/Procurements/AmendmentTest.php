<?php

namespace Tests\Feature\Procurements;

use App\Enums\AmendmentState;
use App\Enums\PlanningApprovalState;
use App\Enums\UserRole;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProcurementMilestone;
use App\Services\AmendmentService;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Contract amendments on their own tab: the proposer requests one (Nota
 * Dinas usulan, Notulen, optionally the partner's letter), their TL and the
 * division's Asman agree, and Laksdan numbers it, picks the changed
 * articles from the contract and issues the document.
 */
class AmendmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $proposer;

    protected User $teamLeader;

    protected User $director;

    protected User $procurementLead;

    protected User $executor;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $division = WorkDirector::query()->firstOrFail();

        $this->teamLeader = User::factory()->proposer($division)->create(['name' => 'TL Operasi']);
        $this->proposer = User::factory()->proposer($division)->create(['team_leader_id' => $this->teamLeader->id]);
        $this->director = User::factory()->workDirector($division)->create(['name' => 'Asman Operasi']);
        $this->procurementLead = User::factory()->teamLeaderPengadaan()->create();
        $this->executor = User::factory()->executor()->create();

        $this->procurement = Procurement::factory()->create([
            'work_director_id' => $division->id,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'PJ')->value('id'),
            'work_category' => 'jasa',
            'number' => 'KDD001.PJ/612/UPKD/2026',
            'partner_name' => 'PT Sultra Alam Perkasa',
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'executor_id' => $this->executor->id,
        ]);
        $this->procurement->forceFill(['created_by' => $this->proposer->id])->save();
    }

    public function test_an_amendment_waits_for_the_contract(): void
    {
        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.store', $this->procurement))
            ->assertForbidden();

        $this->drawContract();

        $this->actingAs($this->proposer)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page->where('can.startAmendment', true)->has('amendments', 0));

        // Only the proposing division starts one.
        $this->actingAs(User::factory()->executor()->create())
            ->post(route('procurements.amendments.store', $this->procurement))
            ->assertForbidden();
    }

    public function test_an_amendment_runs_from_the_proposer_through_tl_and_asman_to_laksdan(): void
    {
        Notification::fake();

        $this->drawContract();

        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.store', $this->procurement))
            ->assertRedirect();

        $amendment = ProcurementAmendment::query()->sole();
        $this->assertSame(1, $amendment->sequence);
        $this->assertSame('I', $amendment->roman());
        // Numbered by Laksdan, not when requested.
        $this->assertNull($amendment->number);

        // Not sendable before the Nota Dinas usulan and the Notulen are in.
        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.submit', [$this->procurement, $amendment]))
            ->assertRedirect();
        $this->assertSame(AmendmentState::Draft, $amendment->refresh()->state);

        $this->fill($amendment)->assertSessionHasNoErrors();

        // The attendance list is no longer asked for.
        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.upload', [$this->procurement, $amendment]), [
                'kind' => 'daftar_hadir',
                'file' => UploadedFile::fake()->create('daftar_hadir.pdf', 40, 'application/pdf'),
            ])
            ->assertStatus(422);

        // The partner's request letter is optional.
        $this->upload($amendment, ['nota_dinas', 'notulen_rapat']);
        $this->assertSame([], $amendment->refresh()->load('files')->missingRequest());

        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.submit', [$this->procurement, $amendment]))
            ->assertSessionHasNoErrors();

        $amendment->refresh();
        $this->assertSame(AmendmentState::MenungguPersetujuanTl, $amendment->state);
        Notification::assertSentTo($this->teamLeader, ProcurementMilestone::class);
        // The document is drawn by Laksdan, not on the request.
        $this->assertNull($amendment->procurement_document_id);

        // The Asman cannot jump the TL; nobody approves their own request.
        $this->decide($this->director, $amendment, true)->assertForbidden();
        $this->decide($this->proposer, $amendment, true)->assertForbidden();

        $this->decide($this->teamLeader, $amendment, true)->assertRedirect();
        $this->assertSame(AmendmentState::MenungguPersetujuanAsman, $amendment->refresh()->state);

        $this->decide($this->director, $amendment, true)->assertRedirect();

        // Straight to Laksdan, numbered in sequence on the Amd format.
        $amendment->refresh();
        $this->assertSame(AmendmentState::MenungguTlPengadaan, $amendment->state);
        $this->assertMatchesRegularExpression('/^KDD001\.Amd\/612\/UPKD\/\d{4}$/', (string) $amendment->number);
        $this->assertNotNull($amendment->amendment_date);
        Notification::assertSentTo($this->executor, ProcurementMilestone::class);

        // Nobody decides any more; the planning side has no say.
        $this->decide($this->procurementLead, $amendment, true)->assertForbidden();
        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->post(route('procurements.amendments.issue', [$this->procurement, $amendment]))
            ->assertForbidden();

        // Laksdan picks the changed article from the contract.
        $this->actingAs($this->executor)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('amendments.0.can.issue', true)
                ->where('amendmentArticles.1.article', 'PASAL 8 HARGA PEKERJAAN')
                ->where('amendmentArticles.1.text', 'Harga pekerjaan sebesar Rp 2.729.592.500,-'));

        $this->actingAs($this->executor)
            ->put(route('procurements.amendments.issue-data', [$this->procurement, $amendment]), [
                'number' => 'KDD054.Amd/612/UPKD/2026',
                'amendment_date' => '2026-08-14',
                'contract_date' => '2026-02-12',
                'changes' => [[
                    'article' => 'Pasal 8 HARGA PEKERJAAN',
                    'original_text' => "Harga pekerjaan sebesar Rp 2.729.592.500,-\n<script>alert(1)</script>",
                    'new_text' => 'Harga pekerjaan sebesar Rp 2.729.590.000,-',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->executor)
            ->post(route('procurements.amendments.issue', [$this->procurement, $amendment]))
            ->assertRedirect();

        $amendment->refresh();
        $this->assertSame(AmendmentState::Disetujui, $amendment->state);
        $this->assertSame($this->executor->id, $amendment->issued_by);

        $document = $amendment->document;
        $this->assertNotNull($document);
        $this->assertSame($amendment->id, $document->procurement_amendment_id);
        $this->assertStringContainsString('AMANDEMEN', $document->rendered_body);
        $this->assertStringContainsString('SURAT PERJANJIAN', $document->rendered_body);
        $this->assertStringContainsString('KDD054.Amd/612/UPKD/2026', $document->rendered_body);
        // The article in bold capitals, its wording laid out as in the contract.
        $this->assertStringContainsString('<b>PASAL 8 HARGA PEKERJAAN</b>', $document->rendered_body);
        $this->assertStringContainsString('<table class="teks">', $document->rendered_body);
        $this->assertStringContainsString('1279/KLH.00.01/GM/2026', $document->rendered_body);
        $this->assertStringContainsString('PIHAK KESATU', $document->rendered_body);
        // No partner letter filed: not cited.
        $this->assertStringNotContainsString('Surat Permohonan Amandemen dari', $document->rendered_body);
        // What was typed is escaped, never markup.
        $this->assertStringContainsString('&lt;script&gt;', $document->rendered_body);
        $this->assertStringNotContainsString('<script>alert', $document->rendered_body);

        $this->assertSame(4, $this->procurement->activities()->where('type', 'like', 'amandemen_%')->count());

        // The next one is Amandemen II, and its number follows on.
        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.store', $this->procurement))
            ->assertRedirect();
        $this->assertSame([1, 2], ProcurementAmendment::query()->orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_articles_are_offered_per_ayat_with_their_lists(): void
    {
        $this->drawContract('<div class="pasal"><div>Pasal 8</div><div>Masa Garansi</div></div>'
            .'<table class="ayat"><tr><td class="no">(1)</td><td>PIHAK KEDUA menjamin barang.</td></tr>'
            .'<tr><td class="no">(2)</td><td>Jaminan tidak berlaku pada hal sebagai berikut:'
            .'<ol class="huruf"><li>Rusak karena modifikasi;</li><li>Rusak karena Force Majeur.</li></ol></td></tr></table>');

        $articles = app(AmendmentService::class)->contractArticles($this->procurement);

        $this->assertSame(['PASAL 8 MASA GARANSI Ayat 1', 'PASAL 8 MASA GARANSI Ayat 2'], array_column($articles, 'article'));
        $this->assertSame(
            "2. Jaminan tidak berlaku pada hal sebagai berikut:\n   a. Rusak karena modifikasi;\n   b. Rusak karena Force Majeur.",
            $articles[1]['text'],
        );
    }

    public function test_the_partner_letter_is_cited_when_filed(): void
    {
        $this->drawContract();
        $amendment = $this->readyAmendment();
        $this->upload($amendment, ['surat_permohonan']);

        $this->actingAs($this->proposer)->post(route('procurements.amendments.submit', [$this->procurement, $amendment]));
        $this->decide($this->teamLeader, $amendment, true);
        $this->decide($this->director, $amendment, true);

        $this->actingAs($this->executor)
            ->put(route('procurements.amendments.issue-data', [$this->procurement, $amendment]), [
                'number' => $amendment->refresh()->number,
                'amendment_date' => '2026-08-14',
                'changes' => [['article' => 'Pasal 3', 'original_text' => 'Lama', 'new_text' => 'Baru']],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->executor)->post(route('procurements.amendments.issue', [$this->procurement, $amendment]));

        $this->assertStringContainsString('Surat Permohonan Amandemen dari', $amendment->refresh()->document->rendered_body);
    }

    public function test_a_returned_amendment_is_corrected_and_sent_again(): void
    {
        $this->drawContract();
        $amendment = $this->readyAmendment();

        $this->actingAs($this->proposer)->post(route('procurements.amendments.submit', [$this->procurement, $amendment]));

        // A return needs a note.
        $this->decide($this->teamLeader, $amendment, false)->assertSessionHasErrors('note');

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.amendments.decide', [$this->procurement, $amendment]), [
                'approved' => false,
                'note' => 'Lampirkan notulen yang sudah ditandatangani.',
            ])
            ->assertRedirect();

        $this->assertSame(AmendmentState::Dikembalikan, $amendment->refresh()->state);

        // While waiting nothing could be edited; returned, the proposer can.
        $this->fill($amendment, ['memo_number' => '1300/KLH/2026'])->assertSessionHasNoErrors();

        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.submit', [$this->procurement, $amendment]))
            ->assertSessionHasNoErrors();

        $amendment->refresh();
        $this->assertSame(AmendmentState::MenungguPersetujuanTl, $amendment->state);
        $this->assertSame(1, $amendment->revision);
        $this->assertSame('1300/KLH/2026', $amendment->memo_number);
    }

    public function test_a_waiting_amendment_cannot_be_changed_and_a_second_cannot_be_started(): void
    {
        $this->drawContract();
        $amendment = $this->readyAmendment();

        $this->actingAs($this->proposer)->post(route('procurements.amendments.submit', [$this->procurement, $amendment]));

        $this->fill($amendment, ['memo_number' => 'Diubah'])->assertForbidden();

        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.store', $this->procurement))
            ->assertForbidden();
    }

    public function test_the_papers_are_private_to_whoever_sees_the_procurement(): void
    {
        $this->drawContract();
        $amendment = $this->readyAmendment();
        $file = $amendment->files()->firstOrFail();

        $this->actingAs($this->proposer)
            ->get(route('procurements.amendments.file', [$this->procurement, $amendment, $file]))
            ->assertOk();

        $this->actingAs(User::factory()->executor()->create())
            ->get(route('procurements.amendments.file', [$this->procurement, $amendment, $file]))
            ->assertForbidden();

        // A file of another procurement is not reached through this one.
        $other = Procurement::factory()->create();

        $this->actingAs($this->proposer)
            ->get(route('procurements.amendments.file', [$other, $amendment, $file]))
            ->assertNotFound();
    }

    public function test_the_amendment_format_is_not_a_contract_format(): void
    {
        $this->actingAs($this->procurementLead)
            ->get(route('procurements.create'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'options.contractNumberFormats',
                fn ($formats) => collect($formats)->pluck('label')->doesntContain('Amd'),
            ));

        $this->assertNotNull(ContractNumberFormat::forAmendments());
    }

    /**
     * Draw the contract of the procurement.
     */
    public function test_in_k3_and_lingkungan_the_team_leader_approves_once(): void
    {
        // As in K3 KAM and Lingkungan: the TL is the division's Direksi
        // Pekerjaan, with no Asman above.
        $this->director->forceFill(['is_active' => false])->save();
        $this->teamLeader->forceFill(['role' => UserRole::DireksiPekerjaan])->save();
        $this->drawContract();

        $amendment = $this->readyAmendment();
        $this->actingAs($this->proposer)->post(route('procurements.amendments.submit', [$this->procurement, $amendment]));

        // Not asked twice: straight to the TL as the division's approver.
        $this->assertSame(AmendmentState::MenungguPersetujuanAsman, $amendment->refresh()->state);
        $this->decide($this->teamLeader, $amendment, true)->assertSessionHasNoErrors();

        $this->assertSame(AmendmentState::MenungguTlPengadaan, $amendment->refresh()->state);
        $this->assertNotNull($amendment->number);
    }

    public function test_a_team_leader_of_a_division_without_an_asman_account_approves_once(): void
    {
        // The TL holds no approval right and no Asman account is active.
        $this->director->forceFill(['is_active' => false])->save();
        $this->drawContract();

        $amendment = $this->readyAmendment();
        $this->actingAs($this->proposer)->post(route('procurements.amendments.submit', [$this->procurement, $amendment]));
        $this->assertSame(AmendmentState::MenungguPersetujuanTl, $amendment->refresh()->state);

        $this->decide($this->teamLeader, $amendment, true)->assertSessionHasNoErrors();

        // On to Laksdan, not left waiting on an Asman who is not there.
        $amendment->refresh();
        $this->assertSame(AmendmentState::MenungguTlPengadaan, $amendment->state);
        $this->assertSame($this->teamLeader->id, $amendment->director_reviewed_by);
    }

    public function test_an_amendment_already_waiting_on_a_missing_asman_is_decided_by_the_team_leader(): void
    {
        $this->drawContract();
        $amendment = $this->readyAmendment();
        $amendment->forceFill(['state' => AmendmentState::MenungguPersetujuanAsman, 'submitted_at' => now()])->save();

        // With an Asman account, the TL does not decide that level.
        $this->decide($this->teamLeader, $amendment, true)->assertForbidden();

        $this->director->forceFill(['is_active' => false])->save();

        $this->actingAs($this->teamLeader)
            ->get(route('approvals.index', ['queue' => 'amendment']))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.id', $amendment->id));

        $this->decide($this->teamLeader, $amendment, true)->assertSessionHasNoErrors();
        $this->assertSame(AmendmentState::MenungguTlPengadaan, $amendment->refresh()->state);
    }

    protected function drawContract(?string $body = null): void
    {
        $type = DocumentType::query()->where('code', 'spk')->firstOrFail();

        $this->procurement->documents()->create([
            'document_type_id' => $type->id,
            'title' => 'Surat Perjanjian',
            'file_name' => 'pj.html',
            'template_version' => 1,
            'rendered_body' => $body ?? '<div class="pasal"><div>Pasal 1</div><div>LINGKUP PEKERJAAN</div></div><p>Lingkup pekerjaan.</p>'
                .'<div class="pasal"><div>Pasal 8</div><div>HARGA PEKERJAAN</div></div><p>Harga pekerjaan sebesar <span data-field="nilai">Rp 2.729.592.500,-</span></p>',
            'generated_by' => $this->procurementLead->id,
            'generated_at' => now()->subMonth(),
        ]);
    }

    /**
     * An amendment with everything filled in, ready to send.
     */
    protected function readyAmendment(): ProcurementAmendment
    {
        $this->actingAs($this->proposer)->post(route('procurements.amendments.store', $this->procurement));
        $amendment = ProcurementAmendment::query()->latest('id')->firstOrFail();

        $this->fill($amendment);
        $this->upload($amendment, ['nota_dinas', 'notulen_rapat']);

        return $amendment->refresh();
    }

    /**
     * File papers of the request.
     *
     * @param  array<int, string>  $kinds
     */
    protected function upload(ProcurementAmendment $amendment, array $kinds): void
    {
        foreach ($kinds as $kind) {
            $this->actingAs($this->proposer)
                ->post(route('procurements.amendments.upload', [$this->procurement, $amendment]), [
                    'kind' => $kind,
                    'file' => UploadedFile::fake()->create("{$kind}.pdf", 40, 'application/pdf'),
                ])
                ->assertSessionHasNoErrors();
        }
    }

    /**
     * Save the proposer's request: the Nota Dinas usulan and the Notulen date.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function fill(ProcurementAmendment $amendment, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->proposer)
            ->put(route('procurements.amendments.update', [$this->procurement, $amendment]), [
                'memo_number' => '1279/KLH.00.01/GM/2026',
                'memo_date' => '2026-08-10',
                'meeting_date' => '2026-08-14',
                ...$overrides,
            ]);
    }

    /**
     * Decide on the amendment as a user.
     */
    protected function decide(User $user, ProcurementAmendment $amendment, bool $approved): TestResponse
    {
        return $this->actingAs($user)
            ->post(route('procurements.amendments.decide', [$this->procurement, $amendment]), ['approved' => $approved]);
    }
}
