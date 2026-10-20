<?php

namespace Tests\Feature\Procurements;

use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\SelectionMethod;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Support\TenderBeritaAcara;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Proses SMART SCM & Berita Acara" step asks for the Berita Acara of the
 * procurement's selection method: Pengadaan Langsung keeps its own, the
 * tenders (Penunjukan Langsung, Lelang Terbuka 1 Tahap 1 / 2 Sampul) have
 * theirs, and the two-envelope tender adds the papers of each envelope.
 */
class TenderMethodDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);
        // Rabu, 9 September 2026.
        $this->travelTo('2026-09-09 09:00:00');

        User::factory()->teamLeaderPengadaan()->create(['name' => 'Muhammad Amrullah']);
        $this->author = User::factory()->executor()->create(['name' => 'Supriyadi']);
    }

    public function test_pengadaan_langsung_keeps_its_own_berita_acara(): void
    {
        foreach ([null, 'pengadaan-langsung'] as $method) {
            $codes = $this->stepDocuments($this->procurement($method));

            $this->assertContains('ba-aanwijzing', $codes);
            $this->assertContains('ba-evaluasi-teknis', $codes);
            $this->assertContains('lampiran-bapp', $codes);
            $this->assertNotContains('ba-aanwijzing-lelang', $codes);
            $this->assertNotContains('sppbj', $codes);
            $this->assertNotContains('ba-pembukaan-sampul-1', $codes);
        }
    }

    public function test_the_tenders_have_their_own_and_two_envelopes_open_each(): void
    {
        $twoEnvelopes = $this->stepDocuments($this->procurement('lelang-terbuka-1-tahap-2-sampul'));

        $this->assertSame([
            'proses-smart-scm', 'ba-aanwijzing-lelang', 'ba-pembukaan-sampul-1', 'lampiran-bapp',
            'ba-evaluasi-administrasi', 'ba-evaluasi-keuangan', 'ba-evaluasi-teknis-lelang', 'ba-evaluasi-harga',
            'ba-hasil-evaluasi', 'pengumuman-evaluasi-sampul-1', 'ba-pembukaan-sampul-2', 'ba-klarifikasi',
            'ba-negosiasi-penawaran', 'ba-hasil-pengadaan', 'usulan-calon-pemenang', 'ba-penetapan-pemenang',
            'pengumuman-pemenang', 'sppbj',
        ], $twoEnvelopes);

        foreach (['lelang-terbuka-1-tahap-1-sampul', 'penunjukan-langsung'] as $method) {
            $codes = $this->stepDocuments($this->procurement($method));

            $this->assertContains('sppbj', $codes, $method);
            $this->assertContains('ba-hasil-pengadaan', $codes, $method);
            $this->assertNotContains('ba-pembukaan-sampul-1', $codes, $method);
            $this->assertNotContains('ba-pembukaan-sampul-2', $codes, $method);
            $this->assertNotContains('ba-aanwijzing', $codes, $method);
        }
    }

    public function test_every_tender_document_draws_from_its_template(): void
    {
        $procurement = $this->procurement('lelang-terbuka-1-tahap-2-sampul', [
            'name' => 'Pengadaan Jasa Rewinding Generator',
            'hpe_value' => 2_030_891_786,
            'value_after_negotiation' => 1_980_111_607,
            'execution_duration_days' => 120,
        ]);
        $winner = $procurement->partnerCandidates()->create([
            'name' => 'Mesindo Teknindesia. PT',
            'address' => 'Jl. Raya Kayu Tinggi No. 2 & 3 Cakung Jakarta Timur',
            'director_name' => 'Hartono',
            'quotation_value' => 2_274_589_800,
            'sort_order' => 1,
        ]);
        $procurement->partnerCandidates()->create(['name' => 'Mega Daya', 'quotation_value' => 2_400_000_000, 'sort_order' => 2]);
        $procurement->update(['partner_candidate_id' => $winner->id]);

        foreach (array_keys(TenderBeritaAcara::TYPES) as $code) {
            $body = $this->draw($procurement->refresh(), $code);

            $this->assertStringNotContainsString('{{', $body, $code);
            $this->assertStringContainsString('/logo/sidebar-logo.png', $body, $code);
        }

        // The winner, its offer and the negotiated price against the HPS.
        $award = $this->draw($procurement, 'ba-penetapan-pemenang');
        $this->assertStringContainsString('MESINDO TEKNINDESIA. PT', $award);
        $this->assertStringContainsString('Nominator ke-1', $award);
        $this->assertStringContainsString('2.217.725.000', $award);
        $this->assertStringContainsString('Dua Milyar Dua Ratus Tujuh Belas Juta Tujuh Ratus Dua Puluh Lima Ribu Rupiah', str_replace('Miliar', 'Milyar', $award));

        $negotiation = $this->draw($procurement, 'ba-negosiasi-penawaran');
        $this->assertStringContainsString('2.274.589.800,00', $negotiation);
        $this->assertStringContainsString('2.217.725.000,00', $negotiation);
        $this->assertStringContainsString('120', $negotiation);

        $report = $this->draw($procurement, 'ba-hasil-pengadaan');
        $this->assertStringContainsString('Satu Tahap Dua Sampul', $report);
        $this->assertStringContainsString('97,50%', $report);

        $announcement = $this->draw($procurement, 'pengumuman-pemenang');
        $this->assertStringContainsString('HASIL EVALUASI SAMPUL 2', $announcement);
        $this->assertStringContainsString('LANJUT KE MASA SANGGAH', $announcement);

        $finance = $this->draw($procurement, 'ba-evaluasi-keuangan');
        $this->assertStringContainsString('Copy Laporan keuangan tahun 2025', $finance);
        $this->assertStringContainsString('MEMENUHI', $finance);
    }

    public function test_an_administrator_keeps_a_document_to_some_methods(): void
    {
        $administrator = User::factory()->administrator()->create();
        $type = DocumentType::query()->where('code', 'ba-klarifikasi')->firstOrFail();
        $lelang = SelectionMethod::query()->where('code', 'lelang-terbuka-1-tahap-1-sampul')->firstOrFail();

        $update = fn (array $methods) => $this->actingAs($administrator)
            ->put(route('master-data.document-types.update', $type), [
                'name' => $type->name,
                'code' => $type->code,
                'stage' => $type->stage->value,
                'description' => null,
                'sort_order' => $type->sort_order,
                'is_active' => true,
                'selection_method_ids' => $methods,
            ])
            ->assertSessionHasNoErrors();

        $update([$lelang->id]);

        $this->assertNotContains('ba-klarifikasi', $this->stepDocuments($this->procurement('pengadaan-langsung')));
        $this->assertContains('ba-klarifikasi', $this->stepDocuments($this->procurement('lelang-terbuka-1-tahap-1-sampul')));

        // None chosen: every method again.
        $update([]);

        $this->assertContains('ba-klarifikasi', $this->stepDocuments($this->procurement('pengadaan-langsung')));
    }

    /**
     * An SPK procurement of the selection method.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function procurement(?string $method, array $attributes = []): Procurement
    {
        return Procurement::factory()->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'selection_method_id' => $method === null ? null : SelectionMethod::query()->where('code', $method)->value('id'),
            'executor_id' => $this->author->id,
            ...$attributes,
        ]);
    }

    /**
     * The documents the SMART SCM step asks of the procurement, in order.
     *
     * @return list<string>
     */
    protected function stepDocuments(Procurement $procurement): array
    {
        $step = ChecklistItem::query()
            ->whereNull('procurement_id')
            ->where('name', TenderBeritaAcara::STEP_NAME)
            ->with('documentTypes.selectionMethods')
            ->firstOrFail();

        return $step->documentTypesFor($procurement->load('selectionMethod'))
            ->map(fn (DocumentType $type): string => $type->code)
            ->values()
            ->all();
    }

    /**
     * The body of a document of the type, drawn now.
     */
    protected function draw(Procurement $procurement, string $code): string
    {
        $type = DocumentType::query()->where('code', $code)->firstOrFail();

        return app(DocumentGenerator::class)->generate($procurement, $type, $this->author)->rendered_body;
    }
}
