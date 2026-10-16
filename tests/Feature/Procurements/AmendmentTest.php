<?php

namespace Tests\Feature\Procurements;

use App\Enums\AmendmentState;
use App\Enums\PlanningApprovalState;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProcurementMilestone;
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
 * Contract amendments on their own tab: the proposer files the papers and
 * the changed articles, their TL, the division's Asman and TL Pengadaan
 * approve, and the amendment document is drawn from its template.
 */
class AmendmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $proposer;

    protected User $teamLeader;

    protected User $director;

    protected User $procurementLead;

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

        $this->procurement = Procurement::factory()->create([
            'work_director_id' => $division->id,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'PJ')->value('id'),
            'work_category' => 'jasa',
            'number' => 'KDD001.PJ/612/UPKD/2026',
            'partner_name' => 'PT Sultra Alam Perkasa',
            'planning_approval_state' => PlanningApprovalState::Disetujui,
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

    public function test_an_amendment_runs_from_the_proposer_through_tl_asman_and_tl_pengadaan(): void
    {
        Notification::fake();

        $this->drawContract();

        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.store', $this->procurement))
            ->assertRedirect();

        $amendment = ProcurementAmendment::query()->sole();
        $this->assertSame(1, $amendment->sequence);
        $this->assertSame('I', $amendment->roman());
        // Numbered automatically on the Amd format, still correctable.
        $this->assertMatchesRegularExpression('/^KDD\d{3}\.Amd\/612\/UPKD\/\d{4}$/', (string) $amendment->number);

        // Not sendable before the papers and the changes are in.
        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.submit', [$this->procurement, $amendment]))
            ->assertRedirect();
        $this->assertSame(AmendmentState::Draft, $amendment->refresh()->state);

        $this->fill($amendment, ['number' => 'KDD054.Amd/612/UPKD/2026']);
        $this->assertSame('KDD054.Amd/612/UPKD/2026', $amendment->refresh()->number);

        foreach (['notulen_rapat', 'daftar_hadir', 'nota_dinas', 'surat_permohonan'] as $kind) {
            $this->actingAs($this->proposer)
                ->post(route('procurements.amendments.upload', [$this->procurement, $amendment]), [
                    'kind' => $kind,
                    'file' => UploadedFile::fake()->create("{$kind}.pdf", 40, 'application/pdf'),
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertCount(4, $amendment->files()->get());

        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.submit', [$this->procurement, $amendment]))
            ->assertSessionHasNoErrors();

        $amendment->refresh();
        $this->assertSame(AmendmentState::MenungguPersetujuanTl, $amendment->state);
        Notification::assertSentTo($this->teamLeader, ProcurementMilestone::class);

        // The document is drawn for the approvers to read.
        $document = $amendment->document;
        $this->assertNotNull($document);
        $this->assertSame($amendment->id, $document->procurement_amendment_id);
        $this->assertStringContainsString('AMANDEMEN', $document->rendered_body);
        $this->assertStringContainsString('SURAT PERJANJIAN', $document->rendered_body);
        $this->assertStringContainsString('KDD054.Amd/612/UPKD/2026', $document->rendered_body);
        $this->assertStringContainsString('PASAL 8 HARGA PEKERJAAN Ayat 1', $document->rendered_body);
        // What the proposer typed is escaped, never markup.
        $this->assertStringContainsString('&lt;script&gt;', $document->rendered_body);
        $this->assertStringNotContainsString('<script>alert', $document->rendered_body);

        // The Asman cannot jump the TL, nor TL Pengadaan the Asman.
        $this->decide($this->director, $amendment, true)->assertForbidden();
        $this->decide($this->procurementLead, $amendment, true)->assertForbidden();
        // Nobody approves their own amendment.
        $this->decide($this->proposer, $amendment, true)->assertForbidden();

        $this->decide($this->teamLeader, $amendment, true)->assertRedirect();
        $this->assertSame(AmendmentState::MenungguPersetujuanAsman, $amendment->refresh()->state);

        $this->decide($this->director, $amendment, true)->assertRedirect();
        $this->assertSame(AmendmentState::MenungguTlPengadaan, $amendment->refresh()->state);
        Notification::assertSentTo($this->procurementLead, ProcurementMilestone::class);

        // Not through the planning side: TL ICC has no say.
        $this->decide(User::factory()->teamLeaderIcc()->create(), $amendment, true)->assertForbidden();

        $this->decide($this->procurementLead, $amendment, true)->assertRedirect();
        $this->assertSame(AmendmentState::Disetujui, $amendment->refresh()->state);

        $this->assertSame(4, $this->procurement->activities()->where('type', 'like', 'amandemen_%')->count());

        // The next one is Amandemen II.
        $this->actingAs($this->proposer)
            ->post(route('procurements.amendments.store', $this->procurement))
            ->assertRedirect();
        $this->assertSame([1, 2], ProcurementAmendment::query()->orderBy('sequence')->pluck('sequence')->all());
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
                'note' => 'Perbaiki nilai pada Pasal 8.',
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
        $this->assertStringContainsString('1300/KLH/2026', $amendment->document->rendered_body);
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
    protected function drawContract(): void
    {
        $type = DocumentType::query()->where('code', 'spk')->firstOrFail();

        $this->procurement->documents()->create([
            'document_type_id' => $type->id,
            'title' => 'Surat Perjanjian',
            'file_name' => 'pj.html',
            'template_version' => 1,
            'rendered_body' => '<p>Kontrak</p>',
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

        foreach (['notulen_rapat', 'daftar_hadir', 'nota_dinas', 'surat_permohonan'] as $kind) {
            $this->actingAs($this->proposer)->post(route('procurements.amendments.upload', [$this->procurement, $amendment]), [
                'kind' => $kind,
                'file' => UploadedFile::fake()->create("{$kind}.pdf", 40, 'application/pdf'),
            ]);
        }

        return $amendment->refresh();
    }

    /**
     * Save the numbers, dates and changed articles.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function fill(ProcurementAmendment $amendment, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->proposer)
            ->put(route('procurements.amendments.update', [$this->procurement, $amendment]), [
                'number' => $amendment->number,
                'amendment_date' => '2026-08-14',
                'contract_date' => '2026-02-12',
                'meeting_date' => '2026-08-14',
                'memo_number' => '1279/KLH.00.01/GM/2026',
                'memo_date' => '2026-08-10',
                'request_number' => '021/SAP/VIII/2026',
                'request_date' => '2026-08-05',
                'changes' => [[
                    'article' => 'PASAL 8 HARGA PEKERJAAN Ayat 1',
                    'original_text' => "Harga sebesar Rp 2.729.592.500,-\n<script>alert(1)</script>",
                    'new_text' => 'Harga sebesar Rp 2.729.590.000,-',
                ]],
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
