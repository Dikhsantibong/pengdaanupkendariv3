<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMethod;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PjTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RksPjJasaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A PJ procurement for services with its RKS generated.
     *
     * @return array{0: Procurement, 1: ProcurementDocument}
     */
    protected function generateRks(): array
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(PjTemplateSeeder::class);

        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();
        $procurement = Procurement::factory()->create([
            'number' => 'KDD017.PJ/612/UPKD/2026',
            'name' => 'Pekerjaan Jasa Pendukung Teknis 11 Site Pembangkit',
            'contract_number_format_id' => $pj->id,
            'work_category' => 'jasa',
        ]);

        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();
        $document = app(DocumentGenerator::class)
            ->generate($procurement, $rks, User::factory()->administrator()->create());

        return [$procurement, $document];
    }

    public function test_a_pj_for_services_gets_the_full_rks(): void
    {
        [, $document] = $this->generateRks();
        $text = strip_tags($document->rendered_body);

        foreach ([
            'RENCANA KERJA DAN SYARAT-SYARAT',
            'UNIT PEMBANGKITAN KENDARI',
            '017.RKS-PJ/612/UPKD/2026',
            'BAB I',
            'INSTRUKSI KEPADA PESERTA PELELANGAN',
            'SYARAT-SYARAT PERJANJIAN',
            'KEPATUHAN TERHADAP HUKUM DAN ANTI PENYUAPAN',
            'PAKTA INTEGRITAS',
            'KETENTUAN BLACK LIST',
            'Pekerjaan Jasa Pendukung Teknis 11 Site Pembangkit',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }

        $this->assertStringContainsString('class="cover"', $document->rendered_body);
    }

    public function test_the_format_template_wins_over_a_method_specific_one(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(PjTemplateSeeder::class);

        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();
        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();
        $method = ProcurementMethod::query()->firstOrFail();

        DocumentTemplate::factory()->create([
            'document_type_id' => $rks->id,
            'procurement_method_id' => $method->id,
            'name' => 'RKS khusus metode',
        ]);

        $resolved = DocumentTemplate::resolveFor($rks->id, $method->id, 'jasa', $pj->id);

        $this->assertSame('RKS PJ Jasa (Penunjukan Langsung) - Template Standar UP Kendari', $resolved?->name);
        // Other formats keep the method-specific RKS.
        $this->assertSame('RKS khusus metode', DocumentTemplate::resolveFor($rks->id, $method->id, 'jasa', null)?->name);
    }

    public function test_every_format_gets_the_same_latest_upb(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(PjTemplateSeeder::class);

        $upb = DocumentType::query()->where('code', 'upb')->firstOrFail();
        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();
        $spk = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();
        $author = User::factory()->administrator()->create();

        foreach (['barang', 'jasa'] as $category) {
            $procurement = Procurement::factory()->create([
                'name' => 'Actuator Governor Mesin Mirrlees PLTD Poasia',
                'contract_number_format_id' => $pj->id,
                'work_category' => $category,
                'proposal_memo_number' => '0001/UPKD/ENJ/COMMON/P/AI-2026/2/2026',
            ]);

            $text = strip_tags(app(DocumentGenerator::class)->generate($procurement, $upb, $author)->rendered_body);

            $this->assertStringContainsString('USULAN PENGADAAN BARANG / JASA', $text);
            $this->assertStringContainsString('0001/UPKD/ENJ/COMMON/P/AI-2026/2/2026', $text);
            $this->assertStringContainsString('No. Requisisi', $text);
            $this->assertStringContainsString('OFFICER INVENTORI KONTROL', $text);
        }

        // One UPB for every format: the SPK gets the very same layout.
        $spkProcurement = Procurement::factory()->create(['contract_number_format_id' => $spk->id, 'work_category' => 'barang']);
        $this->assertStringContainsString(
            'USULAN PENGADAAN BARANG / JASA',
            strip_tags(app(DocumentGenerator::class)->generate($spkProcurement, $upb, $author)->rendered_body),
        );
    }

    public function test_an_unedited_document_moves_to_a_better_fitting_template(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);

        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();
        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();
        $procurement = Procurement::factory()->create(['contract_number_format_id' => $pj->id, 'work_category' => 'jasa']);
        $author = User::factory()->administrator()->create();
        $generator = app(DocumentGenerator::class);

        // Generated before the PJ templates existed: the standard RKS.
        $unedited = $generator->generate($procurement, $rks, $author);
        $edited = $generator->generate($procurement, $rks, $author);
        $generator->saveEdit($edited, $author, $edited->title, $edited->rendered_body.'<p>Catatan.</p>');
        $this->assertStringNotContainsString('INSTRUKSI KEPADA PESERTA', $unedited->rendered_body);

        $this->seed(PjTemplateSeeder::class);

        $this->assertTrue($generator->refreshDocument($unedited->refresh()));
        $this->assertStringContainsString('INSTRUKSI KEPADA PESERTA', $unedited->refresh()->rendered_body);
        $this->assertSame('RKS PJ Jasa (Penunjukan Langsung) - Template Standar UP Kendari', $unedited->documentTemplate->name);

        // A hand-edited document keeps the template it was written on.
        $generator->refreshDocument($edited->refresh());
        $this->assertStringNotContainsString('INSTRUKSI KEPADA PESERTA', $edited->refresh()->rendered_body);
    }

    public function test_the_rks_number_is_derived_from_the_contract_number(): void
    {
        $generator = app(DocumentGenerator::class);

        $this->assertSame('017.RKS-PJ/612/UPKD/2026', $generator->rksNumber('KDD017.PJ/612/UPKD/2026'));
        $this->assertSame('075.RKS-SPK/612/UPKD/2026', $generator->rksNumber('KDD075.SPK/612/UPKD/2026'));
        $this->assertSame('NOMOR-BEBAS', $generator->rksNumber('NOMOR-BEBAS'));
    }

    public function test_rebuilding_an_unedited_document_keeps_its_issue_date(): void
    {
        Carbon::setTestNow('2026-06-12 09:00:00');
        [$procurement, $document] = $this->generateRks();

        Carbon::setTestNow('2026-10-13 09:00:00');
        $procurement->update(['name' => 'Nama Paket Baru']);

        $body = strip_tags($document->refresh()->rendered_body);
        $this->assertStringContainsString('Nama Paket Baru', $body);
        $this->assertStringContainsString('12 Juni 2026', $body);
        $this->assertStringNotContainsString('13 Oktober 2026', $body);

        Carbon::setTestNow();
    }

    public function test_an_unchanged_document_is_not_rebuilt_on_every_poll(): void
    {
        [, $document] = $this->generateRks();
        $generator = app(DocumentGenerator::class);

        $generator->refreshDocument($document->refresh());
        $updatedAt = $document->refresh()->updated_at;

        $this->assertFalse($generator->refreshDocument($document));
        $this->assertEquals($updatedAt, $document->refresh()->updated_at);
    }

    public function test_the_editor_poll_carries_only_a_fingerprint(): void
    {
        [$procurement, $document] = $this->generateRks();
        $administrator = User::factory()->administrator()->create();

        $page = $this->actingAs($administrator)
            ->get(route('procurements.documents.edit', [$procurement, $document]))
            ->assertInertia(fn (Assert $page) => $page->has('liveVersion')->has('document.body'));

        $version = $page->viewData('page')['version'];

        $this->actingAs($administrator)
            ->get(route('procurements.documents.edit', [$procurement, $document]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'procurements/document-editor',
                'X-Inertia-Partial-Data' => 'liveVersion',
            ])
            ->assertOk()
            ->assertJsonPath('props.liveVersion', fn (string $value): bool => strlen($value) === 40)
            ->assertJsonMissingPath('props.document');
    }
}
