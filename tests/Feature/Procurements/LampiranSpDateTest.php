<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DocumentTemplateLibrary;
use App\Services\ProcurementService;
use App\Services\SignedDocumentStore;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Lampiran SP is dated like its Surat Pesanan: on the start date of the
 * "Rentang Waktu Pelaksanaan" step, not on the day it was generated.
 */
class LampiranSpDateTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_HEADING = '<div style="font-weight: bold;">Surat Pesanan (SP) Nomor {{nomor_pengadaan}} tanggal {{tanggal_dokumen}}</div>';

    public function test_the_lampiran_sp_takes_the_date_of_the_rentang_waktu_step(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(SpplDocumentTemplateSeeder::class);

        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPPL')->value('id'),
            'executor_id' => $executor->id,
            'planning_approval_state' => PlanningApprovalState::Disetujui,
        ]);
        app(ProcurementService::class)->syncChecklists($procurement);

        // The start date, given on the Rentang Waktu Pelaksanaan step.
        $step = $procurement->checklists()
            ->whereHas('checklistItem', fn ($query) => $query->where('name', 'Rentang Waktu Pelaksanaan'))
            ->firstOrFail();

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'execution_start_date' => '2026-11-10',
                'execution_duration_days' => 30,
            ])
            ->assertSessionHasNoErrors();

        $type = DocumentType::query()->where('code', 'lampiran-sp')->firstOrFail();
        $this->assertStringContainsString(
            'tanggal {{tanggal_mulai_pelaksanaan}}',
            DocumentTemplate::query()->where('document_type_id', $type->id)->firstOrFail()->body,
        );

        $this->actingAs($executor)
            ->post(route('procurements.documents.store', $procurement), ['document_type_id' => $type->id])
            ->assertSessionHasNoErrors();

        $body = $procurement->documents()->where('document_type_id', $type->id)->firstOrFail()->rendered_body;

        $this->assertMatchesRegularExpression('/Surat Pesanan \(SP\) Nomor .*? tanggal (<[^>]+>)?10 November 2026/s', $body);
        $this->assertStringNotContainsString(now()->translatedFormat('d F Y').'</div>', $body);
    }

    public function test_existing_templates_and_edited_appendices_move_to_the_start_date(): void
    {
        Storage::fake('local');

        $library = app(DocumentTemplateLibrary::class);
        $type = DocumentType::query()->firstOrCreate(
            ['code' => 'lampiran-sp'],
            ['name' => 'Lampiran SP Barang dan Jasa', 'stage' => ProcurementStage::Pelaksanaan, 'sort_order' => 1, 'is_active' => true],
        );

        // An untouched default and one an administrator edited elsewhere.
        $untouchedBody = '<p>A</p>'.self::OLD_HEADING;
        $untouched = DocumentTemplate::factory()->create([
            'document_type_id' => $type->id,
            'body' => $untouchedBody,
            'library_checksum' => $library->checksum($untouchedBody),
        ]);
        $edited = DocumentTemplate::factory()->create([
            'document_type_id' => $type->id,
            'body' => '<p>Diubah admin</p>'.self::OLD_HEADING,
            'library_checksum' => 'lain',
        ]);

        $procurement = Procurement::factory()->create(['execution_start_date' => '2026-11-10', 'execution_duration_days' => 30]);
        $heading = '<div style="font-weight: bold;">Surat Pesanan (SP) Nomor KDD063.SPPL/612/UPKD/2026 tanggal 20 November 2026</div>';

        $draft = $this->appendix($procurement, $type, $heading.'<p>Isi yang diedit</p>');
        $signed = $this->appendix($procurement, $type, $heading);

        app(SignedDocumentStore::class)->store(
            $signed,
            [UploadedFile::fake()->create('ttd.pdf', 20, 'application/pdf')],
            User::factory()->create(),
        );

        $migration = require database_path('migrations/2026_11_27_134939_date_the_lampiran_sp_on_the_execution_start.php');
        $migration->up();

        // Templates: the heading's date is the start date; other edits stay.
        $untouched->refresh();
        $this->assertStringContainsString('tanggal {{tanggal_mulai_pelaksanaan}}</div>', $untouched->body);
        $this->assertSame($library->checksum($untouched->body), $untouched->library_checksum);
        $this->assertStringContainsString('<p>Diubah admin</p>', $edited->refresh()->body);
        $this->assertStringContainsString('tanggal {{tanggal_mulai_pelaksanaan}}</div>', $edited->body);
        $this->assertSame('lain', $edited->library_checksum);

        // An edited appendix now shows the start date, as a live field.
        $this->assertStringContainsString(
            'tanggal <span data-field="tanggal_mulai_pelaksanaan" data-value="10 November 2026">10 November 2026</span></div><p>Isi yang diedit</p>',
            $draft->refresh()->rendered_body,
        );

        // What was signed is not touched.
        $this->assertSame($heading, $signed->refresh()->rendered_body);
    }

    /**
     * An edited Lampiran SP drawn with the given body.
     */
    protected function appendix(Procurement $procurement, DocumentType $type, string $body): ProcurementDocument
    {
        return $procurement->documents()->create([
            'document_type_id' => $type->id,
            'document_template_id' => null,
            'title' => 'Lampiran SP Barang dan Jasa - Uji',
            'file_name' => 'lampiran-sp.html',
            'template_version' => 1,
            'rendered_body' => $body,
            'revision' => 1,
            'generated_by' => User::factory()->create()->id,
            'generated_at' => now(),
        ]);
    }
}
