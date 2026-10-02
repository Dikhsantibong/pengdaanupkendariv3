<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\DocumentPdfRenderer;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorDocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_tor_template_is_seeded_with_cover_and_logo(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);

        $documentType = DocumentType::query()->where('code', 'tor')->firstOrFail();

        // TOR is uploaded by default now; its template stays installed and
        // must keep working for an administrator who switches generation on.
        $documentType->update(['upload_only' => false]);
        $template = DocumentTemplate::resolveFor($documentType->id, null);

        $this->assertNotNull($template);
        $this->assertTrue($template->is_active);
        $this->assertStringContainsString('/image/bg-TOR.jpeg', $template->body);
        $this->assertStringContainsString('/logo/sidebar-logo.png', $template->body);
        $this->assertStringContainsString('TERM OF REFERENCE (TOR)', $template->body);
        $this->assertStringContainsString('LEMBAR PENGESAHAN', $template->body);
        $this->assertContains('nama_pengadaan', $template->placeholders);
        $this->assertContains('nomor_prk', $template->placeholders);
        $this->assertContains('tahun', $template->placeholders);
    }

    public function test_tor_document_can_be_generated_and_rendered_to_html(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = Procurement::factory()->create([
            'name' => 'Jasa Pembuatan Web Digitalisasi PLN NP UP Kendari',
            'prk_number' => 'KD262O0306',
        ]);

        $documentType = DocumentType::query()->where('code', 'tor')->firstOrFail();

        // TOR is uploaded by default now; its template stays installed and
        // must keep working for an administrator who switches generation on.
        $documentType->update(['upload_only' => false]);

        $this->actingAs($teamLeader)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ])->assertRedirect();

        $document = $procurement->documents()->where('document_type_id', $documentType->id)->firstOrFail();

        $generator = app(DocumentGenerator::class);
        $html = $generator->printableHtml($document);

        $this->assertStringContainsString('Jasa Pembuatan Web Digitalisasi PLN NP UP Kendari', $html);
        $this->assertStringContainsString('KD262O0306', $html);
        $this->assertStringContainsString('/image/bg-TOR.jpeg', $html);
        $this->assertStringContainsString('/logo/sidebar-logo.png', $html);
        $this->assertStringContainsString('LEMBAR PENGESAHAN', $html);
    }

    public function test_tor_document_renders_to_pdf_successfully(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = Procurement::factory()->create([
            'name' => 'Jasa Pembuatan Web Digitalisasi PLN NP UP Kendari',
            'prk_number' => 'KD262O0306',
        ]);

        $documentType = DocumentType::query()->where('code', 'tor')->firstOrFail();

        // TOR is uploaded by default now; its template stays installed and
        // must keep working for an administrator who switches generation on.
        $documentType->update(['upload_only' => false]);

        $this->actingAs($teamLeader)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        $document = $procurement->documents()->where('document_type_id', $documentType->id)->firstOrFail();

        $pdfRenderer = app(DocumentPdfRenderer::class);
        $pdf = $pdfRenderer->render($document);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(10000, strlen($pdf));
    }
}
