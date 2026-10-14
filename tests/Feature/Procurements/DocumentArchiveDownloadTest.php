<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\SignedDocumentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Every file of a procurement can be downloaded at once, as one ZIP.
 */
class DocumentArchiveDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_zip_holds_every_document_by_stage(): void
    {
        Storage::fake('local');

        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->planningApproved()->executedBy($executor)->create(['name' => 'Jasa Pompa']);

        // A generated planning document with a signed scan.
        $hpe = DocumentType::factory()->create(['code' => 'hpe', 'name' => 'HPE', 'stage' => ProcurementStage::Perencanaan]);
        DocumentTemplate::factory()->create(['document_type_id' => $hpe->id, 'procurement_method_id' => null, 'body' => '<p>HPE {{nama_pengadaan}}</p>']);
        $hpeDocument = app(DocumentGenerator::class)->generate($procurement, $hpe, $executor);
        app(SignedDocumentStore::class)->store($hpeDocument, [UploadedFile::fake()->create('hpe-ttd.pdf', 20, 'application/pdf')], $executor);

        // An uploaded execution document, and the Nota Dinas of the proposal.
        foreach ([
            ['jaminan-bank', 'Jaminan Bank', ProcurementStage::Pelaksanaan, 'jaminan.pdf'],
            ['nota-dinas-usulan', 'Nota Dinas Usulan', ProcurementStage::Perencanaan, 'nota-dinas.pdf'],
        ] as [$code, $name, $stage, $file]) {
            $type = DocumentType::factory()->create(['code' => $code, 'name' => $name, 'stage' => $stage, 'upload_only' => true]);

            $this->actingAs($executor)
                ->post(route('procurements.documents.upload', $procurement), [
                    'document_type_id' => $type->id,
                    'files' => [UploadedFile::fake()->create($file, 20, 'application/pdf')],
                ])
                ->assertSessionHasNoErrors();
        }

        $response = $this->actingAs($executor)
            ->get(route('procurements.documents.archive', $procurement))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $this->assertStringContainsString('.zip', (string) $response->headers->get('Content-Disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));

        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }

        $zip->close();

        $this->assertContains('BACA SAYA.txt', $names);
        $this->assertContains('2. Perencanaan/HPE/TTD - hpe-ttd.pdf', $names);
        $this->assertNotEmpty(array_filter($names, fn (string $name): bool => str_starts_with($name, '2. Perencanaan/HPE/') && str_ends_with($name, '.pdf') && ! str_contains($name, 'TTD')));
        $this->assertContains('3. Pelaksanaan/Jaminan Bank/jaminan.pdf', $names);
        $this->assertContains('1. Usulan/Nota Dinas Usulan/nota-dinas.pdf', $names);
    }

    public function test_only_those_who_may_see_the_procurement_download_it(): void
    {
        $procurement = Procurement::factory()->create();

        $this->actingAs(User::factory()->executor()->create())
            ->get(route('procurements.documents.archive', $procurement))
            ->assertForbidden();
    }
}
