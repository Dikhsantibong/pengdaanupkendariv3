<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
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
 * A generated document is a draft: its wording and the data pulled into it can
 * be corrected by hand, or pulled through again from the template.
 */
class DocumentEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_shows_the_body_and_the_values_pulled_in(): void
    {
        [$procurement, $document] = $this->generate(['name' => 'Pengadaan Trafo']);

        $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('procurements.documents.edit', [$procurement, $document]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('procurements/document-editor')
                ->where('document.body', $document->rendered_body)
                ->where('document.revision', 0)
                ->where('procurement.number', $procurement->number)
                ->has('placeholders')
            );
    }

    public function test_an_edit_replaces_the_body_and_counts_as_a_revision(): void
    {
        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => 'RKS Direvisi',
                'body' => '<p>Redaksi yang sudah diperbaiki.</p>',
            ])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame('RKS Direvisi', $document->title);
        $this->assertSame('<p>Redaksi yang sudah diperbaiki.</p>', $document->rendered_body);
        $this->assertSame(1, $document->revision);
        $this->assertSame($teamLeader->id, $document->edited_by);
        $this->assertNotNull($document->edited_at);

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::DokumenDiedit->value,
        ]);
    }

    public function test_the_edited_body_is_what_gets_downloaded(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        // Build and cache a PDF of the original body first.
        app(DocumentPdfRenderer::class)->bytes($document);

        $cachedBefore = Storage::disk('local')->files('documents/pdf');
        $this->assertCount(1, $cachedBefore);

        $this->actingAs($teamLeader)
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => $document->title,
                'body' => '<p>KALIMAT PENGGANTI</p>',
            ]);

        $html = $this->actingAs($teamLeader)
            ->get(route('procurements.documents.show', [$procurement, $document]).'?format=html')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('KALIMAT PENGGANTI', $html);

        $pdf = $this->actingAs($teamLeader)
            ->get(route('procurements.documents.show', [$procurement, $document]))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString(
            (string) mb_convert_encoding('KALIMAT PENGGANTI', 'UTF-16BE', 'UTF-8'),
            $this->inflateStreams($pdf),
        );

        // The stale entry built from the previous body is gone, so a download
        // can never serve the document as it read before the correction.
        $cachedAfter = Storage::disk('local')->files('documents/pdf');
        $this->assertCount(1, $cachedAfter);
        $this->assertNotSame($cachedBefore[0], $cachedAfter[0]);
    }

    /**
     * Concatenate the decompressed content streams of a PDF.
     */
    protected function inflateStreams(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        return collect($matches[1])
            ->map(function (string $stream): string {
                $raw = ltrim($stream, "\r\n");

                return (string) (@gzuncompress($raw) ?: @gzinflate($raw) ?: $raw);
            })
            ->implode('');
    }

    public function test_reloading_from_the_template_pulls_the_corrected_data_through(): void
    {
        [$procurement, $document] = $this->generate(['name' => 'Nama Lama']);
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => $document->title,
                'body' => '<p>Ditulis tangan</p>',
            ]);

        $this->assertSame(1, $document->refresh()->revision);

        $procurement->update(['name' => 'Nama Yang Benar']);

        $this->actingAs($teamLeader)
            ->post(route('procurements.documents.regenerate', [$procurement, $document]))
            ->assertRedirect();

        $document->refresh();

        $this->assertSame(0, $document->revision);
        $this->assertNull($document->edited_by);
        $this->assertStringContainsString('Nama Yang Benar', $document->rendered_body);
        $this->assertStringNotContainsString('Ditulis tangan', $document->rendered_body);
    }

    public function test_reloading_reports_a_missing_template_instead_of_failing(): void
    {
        [$procurement, $document] = $this->generate();

        DocumentTemplate::query()->update(['is_active' => false]);

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.documents.regenerate', [$procurement, $document]))
            ->assertRedirect();

        $this->assertStringContainsString('Ditulis oleh template', $document->refresh()->rendered_body);
    }

    public function test_an_unassigned_pic_cannot_open_or_save_the_editor(): void
    {
        $planner = User::factory()->planner()->create();
        $outsider = User::factory()->planner()->create();

        [$procurement, $document] = $this->generate([], $planner);

        $this->actingAs($outsider)
            ->get(route('procurements.documents.edit', [$procurement, $document]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => 'Dibajak',
                'body' => '<p>Dibajak</p>',
            ])
            ->assertForbidden();

        $this->assertSame(0, $document->refresh()->revision);
    }

    public function test_the_assigned_planner_can_edit_the_document(): void
    {
        $planner = User::factory()->planner()->create();

        [$procurement, $document] = $this->generate([], $planner);

        $this->actingAs($planner)
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => $document->title,
                'body' => '<p>Diperbaiki PIC perencana</p>',
            ])
            ->assertRedirect();

        $this->assertSame(1, $document->refresh()->revision);
    }

    public function test_a_document_of_another_procurement_is_not_reachable(): void
    {
        [, $document] = $this->generate();
        $other = Procurement::factory()->create();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('procurements.documents.edit', [$other, $document]))
            ->assertNotFound();
    }

    public function test_an_empty_body_is_rejected(): void
    {
        [$procurement, $document] = $this->generate();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => 'Judul',
                'body' => '',
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $document->refresh()->revision);
    }

    public function test_rich_document_markup_survives_a_save(): void
    {
        [$procurement, $document] = $this->generate();

        $body = '<section class="bab"><h2 class="bab-heading">BAB I<br>UMUM</h2>'
            .'<table><tr><td>Nilai</td><td>Rp 1.000.000,00</td></tr></table>'
            .'<ol><li>Butir &amp; ketentuan</li></ol></section>';

        $this->actingAs(User::factory()->teamLeader()->create())
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => $document->title,
                'body' => $body,
            ])
            ->assertRedirect();

        $this->assertSame($body, $document->refresh()->rendered_body);
    }

    /**
     * Generate a document for a fresh procurement.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Procurement, 1: ProcurementDocument}
     */
    protected function generate(array $attributes = [], ?User $planner = null): array
    {
        $author = $planner ?? User::factory()->teamLeader()->create();
        $documentType = DocumentType::factory()->create();

        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'body' => '<p>Ditulis oleh template untuk {{nama_pengadaan}}.</p>',
        ]);

        $procurement = $planner === null
            ? Procurement::factory()->create($attributes)
            : Procurement::factory()->plannedBy($planner)->create($attributes);

        $this->actingAs($author)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        return [$procurement, ProcurementDocument::query()->firstOrFail()];
    }
}
