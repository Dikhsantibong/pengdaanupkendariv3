<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Under the SPK format the "Kontrak" step draws the SPK as a table of what
 * is ordered, and its articles as a document of their own, the Syarat &
 * Ketentuan (Pasal). A PJ keeps one contract, the Surat Perjanjian.
 */
class SpkContractDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $executor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $this->executor = User::factory()->executor()->create();
    }

    public function test_the_spk_is_a_table_and_its_articles_a_document_of_their_own(): void
    {
        $procurement = $this->onFormat('SPK');
        $generator = app(DocumentGenerator::class);

        $spk = $generator->generate($procurement, $this->type('spk'), $this->executor);
        $this->assertStringContainsString('NAMA BARANG &amp; SPESIFIKASI', $spk->rendered_body);
        $this->assertStringContainsString('BATAS WAKTU', $spk->rendered_body);
        $this->assertStringNotContainsString('LINGKUP PEKERJAAN', $spk->rendered_body);

        $articles = $generator->generate($procurement, $this->type('spk-pasal'), $this->executor);
        $this->assertStringContainsString('LINGKUP PEKERJAAN', $articles->rendered_body);

        $this->actingAs($this->executor)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.pelaksanaan', fn ($rows) => collect($rows)->firstWhere('name', 'Kontrak')['documents'] !== null
                    && collect(collect($rows)->firstWhere('name', 'Kontrak')['documents'])->pluck('type_name')->all() === [
                        'SPK (Surat Perintah Kerja)',
                        'SPK - Syarat & Ketentuan (Pasal)',
                    ]));
    }

    public function test_the_kontrak_step_waits_for_both_signed_documents_under_spk(): void
    {
        $procurement = $this->onFormat('SPK');
        $step = $this->kontrak($procurement);

        $this->sign($procurement, 'spk');
        $this->tick($procurement, $step)->assertSessionHasErrors('is_completed');

        $this->sign($procurement, 'spk-pasal');
        $this->tick($procurement, $step)->assertSessionHasNoErrors();
        $this->assertTrue($step->refresh()->is_completed);
    }

    public function test_a_pj_is_not_asked_for_the_spk_articles(): void
    {
        $procurement = $this->onFormat('PJ');
        $step = $this->kontrak($procurement);

        $this->actingAs($this->executor)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.pelaksanaan', fn ($rows) => collect(collect($rows)->firstWhere('name', 'Kontrak')['documents'])
                    ->pluck('type_name')->all() === ['Surat Perjanjian (PJ)']));

        $this->sign($procurement, 'spk');
        $this->tick($procurement, $step)->assertSessionHasNoErrors();
    }

    public function test_a_document_type_can_be_limited_to_one_format_on_master_data(): void
    {
        $admin = User::factory()->administrator()->create();
        $type = $this->type('spk-pasal');

        $this->actingAs($admin)
            ->put(route('master-data.document-types.update', $type), [
                'name' => $type->name,
                'code' => $type->code,
                'stage' => 'pelaksanaan',
                'contract_number_format_id' => 'none',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($type->refresh()->contract_number_format_id);
    }

    protected function onFormat(string $code): Procurement
    {
        $procurement = Procurement::factory()->planningApproved()->executedBy($this->executor)->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $code)->value('id'),
            'contract_type_id' => ContractType::query()->where('code', 'jasa')->value('id'),
            'work_category' => 'jasa',
            'procurement_method_id' => null,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement->refresh());

        return $procurement;
    }

    protected function type(string $code): DocumentType
    {
        return DocumentType::query()->where('code', $code)->firstOrFail();
    }

    protected function kontrak(Procurement $procurement): ProcurementChecklist
    {
        return $procurement->checklists()
            ->where('stage', ProcurementStage::Pelaksanaan->value)
            ->whereHas('checklistItem', fn ($query) => $query->where('name', 'Kontrak'))
            ->firstOrFail();
    }

    protected function sign(Procurement $procurement, string $code): void
    {
        $document = app(DocumentGenerator::class)->generate($procurement->refresh(), $this->type($code), $this->executor);

        $this->actingAs($this->executor)
            ->post(route('procurements.documents.signed.store', [$procurement, $document]), [
                'files' => [UploadedFile::fake()->create("{$code}.pdf", 20, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();
    }

    protected function tick(Procurement $procurement, ProcurementChecklist $step): TestResponse
    {
        return $this->actingAs($this->executor)
            ->put(route('procurements.checklists.update', [$procurement, $step]), ['is_completed' => true]);
    }
}
