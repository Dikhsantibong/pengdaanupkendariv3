<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DocumentPdfRenderer;
use Database\Seeders\BeritaAcaraTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The berita acara produced during the execution stage.
 */
class BeritaAcaraDocumentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The berita acara types, with a phrase that must appear in each.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function beritaAcaraProvider(): array
    {
        return [
            'aanwijzing' => ['ba-aanwijzing', 'BERITA ACARA PENJELASAN PELELANGAN'],
            'lampiran bapp' => ['lampiran-bapp', 'LAMPIRAN BERITA ACARA PEMBUKAAN PENAWARAN'],
            'evaluasi harga' => ['ba-evaluasi-harga', 'BERITA ACARA EVALUASI HARGA'],
            'hasil evaluasi' => ['ba-hasil-evaluasi', 'BERITA ACARA HASIL EVALUASI'],
            'klarifikasi' => ['ba-klarifikasi', 'BERITA ACARA KLARIFIKASI DAN NEGOSIASI'],
        ];
    }

    public function test_every_berita_acara_type_is_seeded_on_the_execution_stage(): void
    {
        $this->seed(MasterDataSeeder::class);

        foreach (array_column(self::beritaAcaraProvider(), 0) as $code) {
            $type = DocumentType::query()->where('code', $code)->first();

            $this->assertNotNull($type, "Jenis dokumen [{$code}] belum ada.");
            $this->assertSame(ProcurementStage::Pelaksanaan, $type->stage);
            $this->assertTrue($type->is_active);
        }
    }

    #[DataProvider('beritaAcaraProvider')]
    public function test_a_berita_acara_generates_from_its_template(string $code, string $heading): void
    {
        $this->seedTemplates();

        $teamLeader = User::factory()->teamLeader()->create();
        $type = DocumentType::query()->where('code', $code)->firstOrFail();

        $procurement = Procurement::factory()->create([
            'name' => 'Pengadaan Jasa Angkut BBM',
            'hpe_value' => 250_000_000,
        ]);

        $this->actingAs($teamLeader)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $type->id,
            ])
            ->assertRedirect();

        $body = ProcurementDocument::query()->firstOrFail()->rendered_body;

        $this->assertStringContainsString($heading, $body);
        $this->assertStringContainsString('Pengadaan Jasa Angkut BBM', $body);
        $this->assertStringContainsString('PT PLN NUSANTARA POWER', $body);
        $this->assertStringContainsString('Dua ratus lima puluh juta rupiah', $body);
        $this->assertDoesNotMatchRegularExpression('/\{\{[a-z0-9_]+\}\}/', $body);
    }

    public function test_a_berita_acara_renders_to_a_printable_pdf(): void
    {
        Storage::fake('local');

        $this->seedTemplates();

        $teamLeader = User::factory()->teamLeader()->create();
        $type = DocumentType::query()->where('code', 'ba-aanwijzing')->firstOrFail();
        $procurement = Procurement::factory()->create();

        $this->actingAs($teamLeader)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $type->id,
        ]);

        $document = ProcurementDocument::query()->firstOrFail();
        $pdf = app(DocumentPdfRenderer::class)->render($document);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(0, preg_match('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_the_seeder_reports_when_the_document_types_are_missing(): void
    {
        // Templates are seeded without the master data they hang off.
        $this->seed(BeritaAcaraTemplateSeeder::class);

        $this->assertDatabaseCount('document_templates', 0);
    }

    /**
     * Seed the master data and the berita acara templates.
     */
    protected function seedTemplates(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(BeritaAcaraTemplateSeeder::class);

        $this->assertSame(
            5,
            DocumentTemplate::query()
                ->whereIn('document_type_id', DocumentType::query()
                    ->whereIn('code', array_column(self::beritaAcaraProvider(), 0))
                    ->select('id'))
                ->count(),
        );
    }
}
