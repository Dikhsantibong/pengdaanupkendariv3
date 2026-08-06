<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A generated document is printed, signed by hand and scanned back in. The
 * pages rarely arrive as one file, so a document holds as many scans as needed.
 */
class SignedDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_several_scans_can_be_uploaded_at_once(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->post(route('procurements.documents.signed.store', [$procurement, $document]), [
                'files' => [
                    UploadedFile::fake()->create('ba-halaman-1.pdf', 120, 'application/pdf'),
                    UploadedFile::fake()->create('ba-halaman-2.pdf', 120, 'application/pdf'),
                    UploadedFile::fake()->image('ba-halaman-3.jpg'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh()->load('signedUploads');

        $this->assertCount(3, $document->signedUploads);
        $this->assertTrue($document->isSigned());
        $this->assertSame(
            ['ba-halaman-1.pdf', 'ba-halaman-2.pdf', 'ba-halaman-3.jpg'],
            $document->signedUploads->pluck('file_name')->all(),
        );

        foreach ($document->signedUploads as $upload) {
            Storage::disk('local')->assertExists($upload->path);
            $this->assertSame($teamLeader->id, $upload->uploaded_by);
        }

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::DokumenDitandatangani->value,
        ]);
    }

    public function test_uploading_again_adds_to_the_existing_scans(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [UploadedFile::fake()->create('pertama.pdf', 50, 'application/pdf')]],
        );

        $first = $document->refresh()->load('signedUploads')->signedUploads->first();

        $this->actingAs($teamLeader)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [UploadedFile::fake()->create('kedua.pdf', 50, 'application/pdf')]],
        );

        $document->refresh()->load('signedUploads');

        // The earlier scan is kept, not silently replaced.
        $this->assertCount(2, $document->signedUploads);
        $this->assertSame(
            ['pertama.pdf', 'kedua.pdf'],
            $document->signedUploads->pluck('file_name')->all(),
        );
        Storage::disk('local')->assertExists($first->path);
    }

    public function test_each_scan_is_downloaded_on_its_own(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [
                UploadedFile::fake()->create('satu.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('dua.pdf', 50, 'application/pdf'),
            ]],
        );

        foreach ($document->refresh()->load('signedUploads')->signedUploads as $upload) {
            $this->actingAs($teamLeader)
                ->get(route('procurements.documents.signed.show', [$procurement, $document, $upload]))
                ->assertOk()
                ->assertHeader('Content-Disposition', 'attachment; filename='.$upload->file_name);
        }
    }

    public function test_one_scan_can_be_removed_without_touching_the_rest(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [
                UploadedFile::fake()->create('buang.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('simpan.pdf', 50, 'application/pdf'),
            ]],
        );

        $document->refresh()->load('signedUploads');
        [$doomed, $kept] = [$document->signedUploads[0], $document->signedUploads[1]];

        $this->actingAs($teamLeader)
            ->delete(route('procurements.documents.signed.destroy', [$procurement, $document, $doomed]))
            ->assertRedirect();

        $document->refresh()->load('signedUploads');

        $this->assertCount(1, $document->signedUploads);
        $this->assertSame('simpan.pdf', $document->signedUploads->first()->file_name);
        Storage::disk('local')->assertMissing($doomed->path);
        Storage::disk('local')->assertExists($kept->path);
    }

    public function test_all_scans_can_be_cleared_at_once(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [
                UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
            ]],
        );

        $paths = $document->refresh()->load('signedUploads')->signedUploads->pluck('path');

        $this->actingAs($teamLeader)
            ->delete(route('procurements.documents.signed.destroy-all', [$procurement, $document]))
            ->assertRedirect();

        $document->refresh()->load('signedUploads');

        $this->assertCount(0, $document->signedUploads);
        $this->assertFalse($document->isSigned());

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_one_bad_file_rejects_the_whole_batch(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.documents.signed.store', [$procurement, $document]), [
                'files' => [
                    UploadedFile::fake()->create('baik.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('jahat.php', 10, 'text/x-php'),
                ],
            ])
            ->assertSessionHasErrors('files.1');

        // Nothing is stored, so a rejected batch never lands half applied.
        $this->assertCount(0, $document->refresh()->load('signedUploads')->signedUploads);
        $this->assertSame([], Storage::disk('local')->allFiles('documents/signed'));
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.documents.signed.store', [$procurement, $document]), [
                'files' => [UploadedFile::fake()->create('besar.pdf', 21 * 1024, 'application/pdf')],
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertCount(0, $document->refresh()->load('signedUploads')->signedUploads);
    }

    public function test_an_empty_batch_is_rejected(): void
    {
        [$procurement, $document] = $this->generate();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.documents.signed.store', [$procurement, $document]), [])
            ->assertSessionHasErrors('files');
    }

    public function test_an_unassigned_pic_cannot_upload_or_download_the_scans(): void
    {
        Storage::fake('local');

        $planner = User::factory()->planner()->create();
        $outsider = User::factory()->planner()->create();

        [$procurement, $document] = $this->generate($planner);

        $this->actingAs($planner)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [UploadedFile::fake()->create('ba.pdf', 50, 'application/pdf')]],
        )->assertRedirect();

        $upload = $document->refresh()->load('signedUploads')->signedUploads->firstOrFail();

        $this->actingAs($outsider)
            ->post(route('procurements.documents.signed.store', [$procurement, $document]), [
                'files' => [UploadedFile::fake()->create('dibajak.pdf', 50, 'application/pdf')],
            ])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('procurements.documents.signed.show', [$procurement, $document, $upload]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->delete(route('procurements.documents.signed.destroy', [$procurement, $document, $upload]))
            ->assertForbidden();

        $this->assertCount(1, $document->refresh()->load('signedUploads')->signedUploads);
    }

    public function test_a_scan_of_another_document_is_not_reachable(): void
    {
        Storage::fake('local');

        [$procurement, $document] = $this->generate();
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [UploadedFile::fake()->create('ba.pdf', 50, 'application/pdf')]],
        );

        $upload = $document->refresh()->load('signedUploads')->signedUploads->firstOrFail();

        $otherType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create([
            'document_type_id' => $otherType->id,
            'procurement_method_id' => null,
        ]);

        $this->actingAs($teamLeader)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $otherType->id,
        ]);

        $otherDocument = ProcurementDocument::query()
            ->where('document_type_id', $otherType->id)
            ->firstOrFail();

        $this->actingAs($teamLeader)
            ->get(route('procurements.documents.signed.show', [$procurement, $otherDocument, $upload]))
            ->assertNotFound();
    }

    /**
     * Generate a document for a fresh procurement.
     *
     * @return array{0: Procurement, 1: ProcurementDocument}
     */
    protected function generate(?User $planner = null): array
    {
        $author = $planner ?? User::factory()->teamLeader()->create();
        $documentType = DocumentType::factory()->create();

        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'body' => '<p>Berita acara untuk {{nama_pengadaan}}.</p>',
        ]);

        $procurement = $planner === null
            ? Procurement::factory()->create()
            : Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs($author)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        return [$procurement, ProcurementDocument::query()->firstOrFail()];
    }
}
