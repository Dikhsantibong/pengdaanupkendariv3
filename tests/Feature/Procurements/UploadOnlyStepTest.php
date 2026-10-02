<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The opening planning steps are uploaded, not generated, and the steps that
 * apply can differ per contract number format.
 */
class UploadOnlyStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_opening_planning_documents_are_upload_only(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(
            ['csms', 'nota-dinas-perintah-pekerjaan', 'nota-dinas-usulan', 'penawaran', 'rab', 'tor'],
            DocumentType::query()->where('upload_only', true)->orderBy('code')->pluck('code')->all(),
        );

        // The documents further on are still generated.
        $this->assertFalse(DocumentType::query()->where('code', 'hpe')->firstOrFail()->upload_only);
    }

    public function test_an_upload_only_step_is_finished_by_uploading_alone(): void
    {
        Storage::fake('local');

        [$procurement, $planner, $checklist, $type] = $this->planningStep('TOR / KAK');

        $this->actingAs($planner)
            ->post(route('procurements.documents.upload', $procurement), [
                'document_type_id' => $type->id,
                'files' => [UploadedFile::fake()->create('kak.pdf', 120, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $document = $procurement->documents()->firstOrFail();

        $this->assertSame($type->id, $document->document_type_id);
        $this->assertNull($document->document_template_id);
        $this->assertSame(1, $document->signedUploads()->count());

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($checklist->refresh()->is_completed);
    }

    public function test_an_upload_only_step_still_needs_its_upload(): void
    {
        [$procurement, $planner, $checklist] = $this->planningStep('TOR / KAK');

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertSessionHasErrors('is_completed');
    }

    public function test_an_upload_only_document_cannot_be_generated(): void
    {
        [$procurement, $planner, , $type] = $this->planningStep('TOR / KAK');

        $this->actingAs($planner)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $type->id,
            ])
            ->assertSessionHasErrors('document_type_id');

        $this->assertDatabaseCount('procurement_documents', 0);
    }

    public function test_a_generated_document_cannot_be_filed_through_the_upload_route(): void
    {
        [$procurement, $planner] = $this->planningStep('TOR / KAK');
        $hpe = DocumentType::query()->where('code', 'hpe')->firstOrFail();

        $this->actingAs($planner)
            ->post(route('procurements.documents.upload', $procurement), [
                'document_type_id' => $hpe->id,
                'files' => [UploadedFile::fake()->create('hpe.pdf', 10, 'application/pdf')],
            ])
            ->assertSessionHasErrors('document_type_id');
    }

    public function test_an_unassigned_pic_cannot_upload(): void
    {
        [$procurement, , , $type] = $this->planningStep('TOR / KAK');

        $this->actingAs(User::factory()->planner()->create())
            ->post(route('procurements.documents.upload', $procurement), [
                'document_type_id' => $type->id,
                'files' => [UploadedFile::fake()->create('kak.pdf', 10, 'application/pdf')],
            ])
            ->assertForbidden();
    }

    public function test_sppl_merges_the_rab_into_the_penawaran(): void
    {
        $this->seed(MasterDataSeeder::class);

        $sppl = ContractNumberFormat::query()->where('code', 'SPPL')->firstOrFail();
        $spk = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();

        $withSppl = Procurement::factory()->create(['procurement_method_id' => null, 'contract_number_format_id' => $sppl->id]);
        $withSpk = Procurement::factory()->create(['procurement_method_id' => null, 'contract_number_format_id' => $spk->id]);

        app(ProcurementService::class)->syncChecklists($withSppl);
        app(ProcurementService::class)->syncChecklists($withSpk);

        $names = fn (Procurement $procurement): array => $procurement->checklists()
            ->with('checklistItem')
            ->get()
            ->pluck('checklistItem.name')
            ->all();

        $this->assertNotContains('RAB (Rencana Anggaran Biaya)', $names($withSppl));
        $this->assertContains('Penawaran', $names($withSppl));

        $this->assertContains('RAB (Rencana Anggaran Biaya)', $names($withSpk));
    }

    public function test_an_administrator_sets_which_formats_skip_a_step(): void
    {
        $this->seed(MasterDataSeeder::class);

        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();
        $item = ChecklistItem::query()->where('name', 'UPB')->firstOrFail();

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('master-data.checklist-items.update', $item), [
                'stage' => $item->stage->value,
                'name' => $item->name,
                'description' => $item->description,
                'is_optional' => $item->is_optional,
                'sort_order' => $item->sort_order,
                'is_active' => true,
                'excluded_contract_number_format_ids' => [$pj->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$pj->id], $item->excludedContractNumberFormats()->pluck('contract_number_formats.id')->all());
    }

    /**
     * A planning step of a procurement, ready to be ticked.
     *
     * @return array{0: Procurement, 1: User, 2: ProcurementChecklist, 3: DocumentType}
     */
    protected function planningStep(string $name): array
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create(['procurement_method_id' => null]);

        app(ProcurementService::class)->syncChecklists($procurement);

        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->where('name', $name)
            ->firstOrFail();

        $checklist = $procurement->checklists()->where('checklist_item_id', $item->id)->firstOrFail();

        return [$procurement, $planner, $checklist, $item->documentTypes->firstOrFail()];
    }
}
