<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\SignedDocumentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SP appendices drawn before the fix lose the forced page break that left
 * most of page 1 empty; signed ones stay as they were signed.
 */
class LampiranSpPageBreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_appendices_lose_the_forced_page_break(): void
    {
        Storage::fake('local');

        $type = DocumentType::query()->firstOrCreate(
            ['code' => 'lampiran-sp'],
            ['name' => 'Lampiran SP Barang dan Jasa', 'stage' => ProcurementStage::Pelaksanaan, 'sort_order' => 1, 'is_active' => true],
        );
        $body = '<div>C. PEMERIKSAAN</div>'."\n\n    ".'<div style="page-break-before: always;"></div>'."\n\n".'<div>D. PEMBAYARAN</div>';

        $draft = $this->appendix($type, $body);
        $signed = $this->appendix($type, $body);

        app(SignedDocumentStore::class)->store(
            $signed,
            [UploadedFile::fake()->create('ttd.pdf', 20, 'application/pdf')],
            User::factory()->create(),
        );

        $migration = require database_path('migrations/2026_10_15_021502_remove_forced_page_break_from_lampiran_sp.php');
        $migration->up();

        $this->assertStringNotContainsString('page-break-before', $draft->refresh()->rendered_body);
        $this->assertStringContainsString('C. PEMERIKSAAN', $draft->rendered_body);
        $this->assertStringContainsString('D. PEMBAYARAN', $draft->rendered_body);

        // What was signed is not touched.
        $this->assertStringContainsString('page-break-before', $signed->refresh()->rendered_body);
    }

    /**
     * An SP appendix drawn with the given body.
     */
    protected function appendix(DocumentType $type, string $body): ProcurementDocument
    {
        return Procurement::factory()->create()->documents()->create([
            'document_type_id' => $type->id,
            'document_template_id' => null,
            'title' => 'Lampiran SP Barang dan Jasa - Uji',
            'file_name' => 'lampiran-sp.html',
            'template_version' => 1,
            'rendered_body' => $body,
            'generated_by' => User::factory()->create()->id,
            'generated_at' => now(),
        ]);
    }
}
