<?php

namespace Tests\Feature\MasterData;

use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\DocumentTemplateLibrary;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every document has a single template of one version, and the HPE and UPB
 * use the latest layout for every contract format.
 */
class SingleTemplateVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hpe_and_upb_ship_a_single_template_with_the_item_table(): void
    {
        $library = new DocumentTemplateLibrary;

        foreach (['hpe' => 'Harga HPE', 'upb' => 'Tgl Dibutuhkan'] as $type => $heading) {
            $entries = array_filter($library->entries(), fn (array $entry): bool => $entry['document_type'] === $type);

            $this->assertSame(["umum.{$type}"], array_keys($entries));
            $this->assertNull($entries["umum.{$type}"]['contract_format']);
            $this->assertStringContainsString($heading, $library->body("umum.{$type}"));
        }
    }

    public function test_old_versions_and_the_pj_copies_are_removed_and_documents_follow(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $pj = ContractNumberFormat::query()->where('code', 'PJ')->value('id');
        $hpeType = DocumentType::query()->where('code', 'hpe')->firstOrFail();
        $rksType = DocumentType::query()->where('code', 'rks')->value('id');

        // The former PJ copy of the HPE, and a document drawn from it.
        $pjHpe = DocumentTemplate::factory()->create([
            'document_type_id' => $hpeType->id,
            'contract_number_format_id' => $pj,
            'library_key' => 'pj.hpe',
            'version' => 3,
            'body' => '<p>HPE PJ {{nama_pengadaan}}</p>',
        ]);
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create(['contract_number_format_id' => $pj]);
        $document = app(DocumentGenerator::class)->generate($procurement, $hpeType, $planner);
        $this->assertTrue($document->documentTemplate->is($pjHpe));

        // An older version beside the general RKS, and an unused default of a
        // document merged into another one.
        $oldRks = DocumentTemplate::factory()->inactive()->create([
            'document_type_id' => $rksType,
            'library_key' => 'umum.rks',
            'version' => 7,
        ]);
        $merged = DocumentTemplate::factory()->inactive()->create(['library_key' => 'sppl.surat-pesanan-jasa']);

        (require database_path('migrations/2026_10_18_142407_keep_a_single_template_per_document.php'))->up();

        $hpe = DocumentTemplate::query()->where('library_key', 'umum.hpe')->sole();
        $document->refresh();
        $this->assertSame($hpe->id, $document->document_template_id);
        $this->assertSame(1, $document->template_version);
        $this->assertStringContainsString('Harga HPE', $hpe->body);

        $this->assertModelMissing($pjHpe);
        $this->assertModelMissing($oldRks);
        $this->assertModelMissing($merged);
        $this->assertSame(1, DocumentTemplate::query()->where('library_key', 'umum.rks')->count());
        $this->assertSame([1], DocumentTemplate::query()->distinct()->pluck('version')->all());
        $this->assertFalse(DocumentTemplate::query()->where('is_active', false)->exists());
    }
}
