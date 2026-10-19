<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementDocumentUpload;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An uploaded file is what its content says, never what its name or the
 * browser claims: stored under that extension, served with that type and
 * nosniff, and never opened as a page of this site.
 */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A PDF that also carries a script, named and sent as a web page.
     */
    protected function disguisedPdf(): UploadedFile
    {
        // A real file, so its type is read from the bytes as in production.
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, "%PDF-1.4\n<html><script>alert(document.cookie)</script></html>\n%%EOF\n");

        return new UploadedFile($path, 'laporan.html', 'text/html', null, true);
    }

    public function test_a_disguised_file_is_stored_and_served_as_its_content(): void
    {
        Storage::fake('local');
        [$procurement, $planner, $typeId] = $this->planningUpload();

        $this->actingAs($planner)
            ->post(route('procurements.documents.upload', $procurement), [
                'document_type_id' => $typeId,
                'files' => [$this->disguisedPdf()],
            ])
            ->assertSessionHasNoErrors();

        $upload = ProcurementDocumentUpload::query()->sole();

        $this->assertStringEndsWith('.pdf', $upload->path);
        $this->assertSame('application/pdf', $upload->mime);
        // The name the user gave stays only as the download name.
        $this->assertSame('laporan.html', $upload->file_name);

        $response = $this->actingAs($planner)
            ->get(route('procurements.documents.signed.show', [$procurement, $upload->procurement_document_id, $upload, 'preview' => 1]))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_a_type_recorded_before_is_only_downloaded(): void
    {
        Storage::fake('local');
        [$procurement, $planner, $typeId] = $this->planningUpload();

        $this->actingAs($planner)->post(route('procurements.documents.upload', $procurement), [
            'document_type_id' => $typeId,
            'files' => [UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf')],
        ]);

        // A file recorded with the browser's type, before this fix.
        $upload = ProcurementDocumentUpload::query()->sole();
        $upload->forceFill(['mime' => 'text/html'])->save();

        $response = $this->actingAs($planner)
            ->get(route('procurements.documents.signed.show', [$procurement, $upload->procurement_document_id, $upload, 'preview' => 1]))
            ->assertOk();

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_a_calon_mitra_quotation_is_typed_by_its_content(): void
    {
        Storage::fake('local');
        $this->seed(MasterDataSeeder::class);
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs($planner)
            ->post(route('procurements.partner-candidates.store', $procurement), [
                'name' => 'Mitra Uji. PT',
                'quotation_file' => $this->disguisedPdf(),
            ])
            ->assertSessionHasNoErrors();

        $candidate = $procurement->partnerCandidates()->sole();
        $this->assertStringEndsWith('.pdf', (string) $candidate->quotation_file_path);
        $this->assertSame('application/pdf', $candidate->quotation_file_mime);

        $response = $this->actingAs($planner)
            ->get(route('procurements.partner-candidates.quotation', [$procurement, $candidate]))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_stored_files_have_no_direct_address(): void
    {
        $this->get('/storage/documents/signed/1/berkas.pdf')->assertNotFound();
        // Only the 404 fallback answers there, and it takes no writes.
        $this->put('/storage/documents/signed/1/berkas.php', ['x' => 1])->assertMethodNotAllowed();
    }

    /**
     * A planning step whose document is uploaded, with its planner.
     *
     * @return array{0: Procurement, 1: User, 2: int}
     */
    protected function planningUpload(): array
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create(['procurement_method_id' => null]);
        app(ProcurementService::class)->syncChecklists($procurement);

        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->where('name', 'Nota Dinas ke Pengadaan')
            ->firstOrFail();

        return [$procurement, $planner, $item->documentTypes->firstOrFail()->id];
    }
}
