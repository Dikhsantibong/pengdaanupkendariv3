<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DocumentPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Generated documents are served as real PDFs, cached on disk after the first
 * render because a rendered body is an immutable snapshot.
 */
class DocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloading_a_document_returns_a_pdf(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generateDocument();

        $response = $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('procurements.documents.show', [$procurement, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString(
            '.pdf',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_the_pdf_is_cached_on_disk_and_reused(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generateDocument();
        $renderer = app(DocumentPdfRenderer::class);

        $first = $renderer->bytes($document);

        // Cache entries are keyed by document id plus a body fingerprint, so a
        // corrected document never resolves to an entry built before the edit.
        $cached = Storage::disk('local')->files('documents/pdf');

        $this->assertCount(1, $cached);
        $this->assertMatchesRegularExpression(
            '#^documents/pdf/'.$document->id.'-[0-9a-f]{12}\.pdf$#',
            $cached[0],
        );
        $this->assertSame($first, $renderer->bytes($document));
    }

    public function test_every_page_carries_the_running_footer(): void
    {
        Storage::fake('local');

        [, $document] = $this->generateDocument(pages: 3);

        $pdf = app(DocumentPdfRenderer::class)->render($document);

        $this->assertSame(3, $this->pageCount($pdf));
        $this->assertSame(3, $this->streamsContaining($pdf, 'Halaman'));
    }

    public function test_the_pdf_file_name_swaps_the_html_extension(): void
    {
        [, $document] = $this->generateDocument();

        $this->assertSame(
            preg_replace('/\.html$/', '', $document->file_name).'.pdf',
            app(DocumentPdfRenderer::class)->fileName($document),
        );
    }

    /**
     * Generate a document whose body spans the requested number of pages.
     *
     * @return array{0: Procurement, 1: ProcurementDocument}
     */
    protected function generateDocument(int $pages = 1): array
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $documentType = DocumentType::factory()->create();

        $body = collect(range(1, $pages))
            ->map(fn (int $page): string => $page === 1
                ? '<p>Halaman isi '.$page.' untuk {{nama_pengadaan}}.</p>'
                : '<p style="page-break-before: always">Halaman isi '.$page.'.</p>')
            ->implode('');

        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'body' => $body,
        ]);

        $procurement = Procurement::factory()->create();

        $this->actingAs($teamLeader)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        return [$procurement, ProcurementDocument::query()->firstOrFail()];
    }

    /**
     * Count the page objects in a PDF.
     */
    protected function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    /**
     * Count the content streams that draw the given text.
     *
     * Text drawn with an embedded TrueType font is stored as UTF-16BE inside a
     * deflated stream, so both have to be undone before searching.
     */
    protected function streamsContaining(string $pdf, string $needle): int
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        $encoded = (string) mb_convert_encoding($needle, 'UTF-16BE', 'UTF-8');

        return collect($matches[1])
            ->map(function (string $stream): string {
                $raw = ltrim($stream, "\r\n");

                return (string) (@gzuncompress($raw) ?: @gzinflate($raw) ?: $raw);
            })
            ->filter(fn (string $stream): bool => str_contains($stream, $encoded))
            ->count();
    }
}
