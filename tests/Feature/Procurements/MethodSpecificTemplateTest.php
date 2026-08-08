<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMethod;
use App\Models\User;
use App\Support\MasterDataOptions;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RksSpkTemplateSeeder;
use Database\Seeders\RksTenderTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Templates may be written for a specific procurement method, with the general
 * template acting as the fallback for every other method.
 */
class MethodSpecificTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_method_specific_template_wins_over_the_general_one(): void
    {
        $spk = ProcurementMethod::factory()->create(['name' => 'SPK', 'code' => 'spk']);
        $documentType = DocumentType::factory()->create();

        $general = DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'body' => '<p>Template umum</p>',
        ]);
        $specific = DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => $spk->id,
            'body' => '<p>Template SPK</p>',
        ]);

        $this->assertSame(
            $specific->id,
            DocumentTemplate::resolveFor($documentType->id, $spk->id)?->id,
        );

        $this->assertSame(
            $general->id,
            DocumentTemplate::resolveFor($documentType->id, null)?->id,
        );
    }

    public function test_other_methods_fall_back_to_the_general_template(): void
    {
        $spk = ProcurementMethod::factory()->create(['name' => 'SPK', 'code' => 'spk']);
        $tender = ProcurementMethod::factory()->create(['name' => 'Tender', 'code' => 'tender']);
        $documentType = DocumentType::factory()->create();

        $general = DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
        ]);
        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => $spk->id,
        ]);

        $this->assertSame(
            $general->id,
            DocumentTemplate::resolveFor($documentType->id, $tender->id)?->id,
        );
    }

    public function test_generating_uses_the_template_of_the_procurement_method(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $spk = ProcurementMethod::factory()->create(['name' => 'SPK', 'code' => 'spk']);
        $documentType = DocumentType::factory()->create();

        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'body' => '<p>Template umum</p>',
        ]);
        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => $spk->id,
            'body' => '<p>Template khusus {{metode_pengadaan}}</p>',
        ]);

        $procurement = Procurement::factory()->create(['procurement_method_id' => $spk->id]);

        $this->actingAs($teamLeader)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $documentType->id,
            ])
            ->assertRedirect();

        $document = ProcurementDocument::query()->firstOrFail();

        $this->assertStringContainsString('Template khusus SPK', $document->rendered_body);
        $this->assertStringNotContainsString('Template umum', $document->rendered_body);
    }

    public function test_activating_a_method_template_leaves_the_general_one_active(): void
    {
        $administrator = User::factory()->administrator()->create();
        $spk = ProcurementMethod::factory()->create(['name' => 'SPK', 'code' => 'spk']);
        $documentType = DocumentType::factory()->create();

        $general = DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
        ]);

        $this->actingAs($administrator)
            ->post(route('master-data.document-templates.store'), [
                'document_type_id' => $documentType->id,
                'procurement_method_id' => $spk->id,
                'name' => 'RKS Khusus SPK',
                'body' => '<p>{{nama_pengadaan}}</p>',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($general->refresh()->is_active);
        $this->assertTrue(
            DocumentTemplate::query()->where('procurement_method_id', $spk->id)->firstOrFail()->is_active,
        );
    }

    public function test_versions_are_numbered_per_document_type_and_method(): void
    {
        $administrator = User::factory()->administrator()->create();
        $spk = ProcurementMethod::factory()->create(['name' => 'SPK', 'code' => 'spk']);
        $documentType = DocumentType::factory()->create();

        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'version' => 3,
        ]);

        $this->actingAs($administrator)
            ->post(route('master-data.document-templates.store'), [
                'document_type_id' => $documentType->id,
                'procurement_method_id' => $spk->id,
                'name' => 'RKS SPK',
                'body' => '<p>body</p>',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            DocumentTemplate::query()->where('procurement_method_id', $spk->id)->firstOrFail()->version,
        );
    }

    public function test_the_seeded_spk_rks_renders_the_official_structure(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(RksSpkTemplateSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $spk = ProcurementMethod::query()->where('code', 'spk')->firstOrFail();
        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();

        $procurement = Procurement::factory()->create([
            'name' => 'Pengadaan Uji Dokumen',
            'procurement_method_id' => $spk->id,
            'hpe_value' => 250_000_000,
        ]);

        $this->actingAs($teamLeader)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $rks->id,
            ])
            ->assertRedirect();

        $document = ProcurementDocument::query()->firstOrFail();
        $body = $document->rendered_body;

        foreach ([
            'DOKUMEN RENCANA KERJA DAN SYARAT-SYARAT (RKS)',
            'SURAT PERINTAH KERJA (SPK)',
            'BAB I<br>UMUM',
            'INSTRUKSI KEPADA PESERTA',
            'KEPATUHAN TERHADAP HUKUM DAN ANTI PENYUAPAN',
            'PENUTUP',
            'PAKTA INTEGRITAS',
            'KETENTUAN BLACKLIST',
            'Lampiran 13',
            'Pengadaan Uji Dokumen',
            'Dua ratus lima puluh juta rupiah',
        ] as $needle) {
            $this->assertStringContainsString($needle, $body, "Bagian [{$needle}] tidak ditemukan pada RKS.");
        }

        $this->assertDoesNotMatchRegularExpression('/\{\{[a-z0-9_]+\}\}/', $body);
    }

    public function test_the_seeded_tender_rks_renders_the_official_structure(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(RksTenderTemplateSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();
        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();

        $procurement = Procurement::factory()->create([
            'name' => 'Pengadaan Jasa Angkut BBM',
            'procurement_method_id' => $tender->id,
            'hpe_value' => 2_270_000_000,
        ]);

        $this->actingAs($teamLeader)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $rks->id,
            ])
            ->assertRedirect();

        $body = ProcurementDocument::query()->firstOrFail()->rendered_body;

        foreach ([
            'DOKUMEN PELELANGAN TERBUKA',
            'BAB II<br>INSTRUKSI KEPADA PESERTA PELELANGAN',
            'Satu Tahap Dua Sampul',
            'JAMINAN PELAKSANAAN (PERFORMANCE BOND)',
            'MASA SANGGAH DAN JAMINAN SANGGAH',
            '2&permil; (dua perseribu)',
            'minimal 5% (lima persen)',
            'Denda = 1 per mil',
            'Badan Arbitrase Nasional Indonesia (BANI) di Surabaya',
            'PELELANGAN GAGAL',
            'KETENTUAN BLACKLIST',
            'Daftar Penerbit Jaminan Terseleksi',
            'Lampiran 11 : Term of Reference (TOR)',
            'Pengadaan Jasa Angkut BBM',
            'Dua miliar dua ratus tujuh puluh juta rupiah',
        ] as $needle) {
            $this->assertStringContainsString($needle, $body, "Bagian [{$needle}] tidak ditemukan pada RKS Tender.");
        }

        $this->assertDoesNotMatchRegularExpression('/\{\{[a-z0-9_]+\}\}/', $body);
    }

    public function test_spk_and_tender_resolve_to_their_own_template(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(RksSpkTemplateSeeder::class);
        $this->seed(RksTenderTemplateSeeder::class);

        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();
        $spk = ProcurementMethod::query()->where('code', 'spk')->firstOrFail();
        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();

        $spkTemplate = DocumentTemplate::resolveFor($rks->id, $spk->id);
        $tenderTemplate = DocumentTemplate::resolveFor($rks->id, $tender->id);

        $this->assertNotNull($spkTemplate);
        $this->assertNotNull($tenderTemplate);
        $this->assertNotSame($spkTemplate->id, $tenderTemplate->id);
        $this->assertStringContainsString('SURAT PERINTAH KERJA (SPK)', $spkTemplate->body);
        $this->assertStringContainsString('DOKUMEN PELELANGAN TERBUKA', $tenderTemplate->body);

        // Methods without their own template still fall back to the general one.
        $suratPesanan = ProcurementMethod::query()->where('code', 'surat-pesanan')->first();

        if ($suratPesanan !== null) {
            $fallback = DocumentTemplate::resolveFor($rks->id, $suratPesanan->id);

            $this->assertNotNull($fallback);
            $this->assertNull($fallback->procurement_method_id);
        }
    }

    public function test_template_availability_is_resolved_in_a_single_query(): void
    {
        $tender = ProcurementMethod::factory()->create(['name' => 'Tender', 'code' => 'tender']);
        $types = DocumentType::factory()->count(6)->create();

        foreach ($types as $index => $type) {
            DocumentTemplate::factory()->create([
                'document_type_id' => $type->id,
                'procurement_method_id' => $index % 2 === 0 ? $tender->id : null,
            ]);
        }

        $procurement = Procurement::factory()->create(['procurement_method_id' => $tender->id]);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $options = MasterDataOptions::forProcurementDetail($procurement);

        /** @var array<int, array{value: int, hasTemplate: bool}> $documentTypes */
        $documentTypes = $options['documentTypes'];

        $this->assertCount(6, $documentTypes);
        $this->assertTrue(collect($documentTypes)->every(fn (array $type): bool => $type['hasTemplate']));

        // Statuses, planners, executors, contract types, the resolvable ids and
        // the document types themselves: a fixed number of queries that does
        // not grow with the number of document types, which is the point.
        $this->assertLessThanOrEqual(6, $queries);
    }

    public function test_the_document_can_be_previewed_as_printable_html(): void
    {
        [$procurement, $document] = $this->generateSpkRks();

        $response = $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('procurements.documents.show', [$procurement, $document]).'?format=html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertStringContainsString('@page { size: A4', $response->getContent());
        $this->assertStringContainsString('page-break-before', $response->getContent());
    }

    /**
     * Generate the SPK RKS for a fresh procurement.
     *
     * @return array{0: Procurement, 1: ProcurementDocument}
     */
    protected function generateSpkRks(): array
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(RksSpkTemplateSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $spk = ProcurementMethod::query()->where('code', 'spk')->firstOrFail();
        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();
        $procurement = Procurement::factory()->create(['procurement_method_id' => $spk->id]);

        $this->actingAs($teamLeader)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $rks->id,
        ]);

        return [$procurement, ProcurementDocument::query()->firstOrFail()];
    }
}
