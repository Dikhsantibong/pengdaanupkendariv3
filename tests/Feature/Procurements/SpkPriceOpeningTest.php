<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\StandardDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The SPK has its own Pelaksanaan step, Hasil Pembukaan Penawaran Harga, drawn
 * on form SMT-FM-DAN-01.02 from the calon mitra's offers. PJ and SPPL keep
 * their steps and documents as they were.
 */
class SpkPriceOpeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(StandardDocumentTemplateSeeder::class);
    }

    public function test_only_an_spk_takes_the_step_right_after_the_hps(): void
    {
        $spk = $this->procurement('SPK');

        $steps = $this->executionSteps($spk);
        $this->assertContains(MasterDataSeeder::PRICE_OPENING_STEP, $steps);
        $this->assertSame(
            array_search('Penyusunan HPS', $steps, true) + 1,
            array_search(MasterDataSeeder::PRICE_OPENING_STEP, $steps, true),
        );

        $step = ChecklistItem::query()->shared()->where('name', MasterDataSeeder::PRICE_OPENING_STEP)->sole();
        $this->assertSame(['hasil-pembukaan-penawaran-harga'], $step->documentTypes->pluck('code')->all());
        $this->assertFalse($step->is_optional);

        foreach (['PJ', 'SPPL'] as $format) {
            $this->assertNotContains(MasterDataSeeder::PRICE_OPENING_STEP, $this->executionSteps($this->procurement($format)), $format);
        }
    }

    public function test_the_form_lists_each_offer_on_one_portrait_sheet(): void
    {
        $procurement = $this->procurement('SPK');
        $procurement->forceFill(['name' => 'Pengadaan Material Dan Jasa Pemasangan Radiator LT Section'])->save();
        $procurement->partnerCandidates()->create(['name' => 'PT. Heat Exchanger Manufacturing Indonesia', 'quotation_value' => 298590000]);
        $procurement->partnerCandidates()->create(['name' => 'CV Tanpa Harga']);

        $document = app(DocumentGenerator::class)->generate(
            $procurement,
            DocumentType::query()->where('code', 'hasil-pembukaan-penawaran-harga')->firstOrFail(),
            User::factory()->administrator()->create(),
        );
        $body = (string) $document->rendered_body;

        $this->assertStringContainsString('SMT-FM-DAN-01.02', $body);
        $this->assertStringContainsString('HASIL PEMBUKAAN PENAWARAN HARGA', $body);
        $this->assertStringContainsString('size: A4 portrait', $body);
        $this->assertStringContainsString('PT. HEAT EXCHANGER MANUFACTURING INDONESIA', $body);
        $this->assertStringContainsString('298.590.000,00', $body);
        $this->assertStringContainsString('LENGKAP', $body);
        $this->assertStringContainsString('PELAKSANA PENGADAAN BARANG &amp; JASA', $body);
        $this->assertStringContainsString('Pengadaan Material Dan Jasa Pemasangan Radiator LT Section', $body);
        $this->assertStringNotContainsString('{{', $body);
        // Fifteen rows to write on, as on the printed form.
        $this->assertSame(15, substr_count($body, '<td class="no">'));
    }

    public function test_the_migration_adds_the_step_without_reopening_finished_executions(): void
    {
        $running = $this->procurement('SPK');
        $finished = $this->procurement('SPK');
        $finished->forceFill(['execution_approval_state' => PlanningApprovalState::Disetujui])->save();
        $pj = $this->procurement('PJ');

        // A database from before the step.
        $step = ChecklistItem::query()->shared()->where('name', MasterDataSeeder::PRICE_OPENING_STEP)->sole();
        DB::table('procurement_checklists')->where('checklist_item_id', $step->id)->delete();
        DB::table('checklist_item_document_type')->where('checklist_item_id', $step->id)->delete();
        DB::table('checklist_item_format_exclusions')->where('checklist_item_id', $step->id)->delete();
        DB::table('checklist_items')->where('id', $step->id)->delete();
        $hpsBefore = $this->sortOrder('Penyusunan HPS');
        $smartScmBefore = $this->sortOrder('Proses SMART SCM & Berita Acara');

        $migration = require database_path('migrations/2026_11_17_065745_add_the_spk_price_opening_step.php');
        $migration->up();
        $migration->up();

        $step = ChecklistItem::query()->shared()->where('name', MasterDataSeeder::PRICE_OPENING_STEP)->sole();
        $this->assertSame($hpsBefore + 1, $step->sort_order);
        $this->assertSame($smartScmBefore + 1, $this->sortOrder('Proses SMART SCM & Berita Acara'));
        $this->assertSame(['PJ', 'SPPL'], $step->excludedContractNumberFormats->pluck('code')->sort()->values()->all());
        $this->assertSame(['hasil-pembukaan-penawaran-harga'], $step->documentTypes->pluck('code')->all());

        foreach ([$running, $finished, $pj] as $procurement) {
            app(ProcurementService::class)->syncChecklists($procurement);
        }

        $this->assertContains(MasterDataSeeder::PRICE_OPENING_STEP, $this->executionSteps($running));
        $this->assertNotContains(MasterDataSeeder::PRICE_OPENING_STEP, $this->executionSteps($finished));
        $this->assertNotContains(MasterDataSeeder::PRICE_OPENING_STEP, $this->executionSteps($pj));
    }

    public function test_an_spk_uploads_its_pakta_integritas_after_the_price_opening(): void
    {
        $steps = $this->executionSteps($this->procurement('SPK'));

        $this->assertSame(
            array_search(MasterDataSeeder::PRICE_OPENING_STEP, $steps, true) + 1,
            array_search(MasterDataSeeder::INTEGRITY_PACT_STEP, $steps, true),
        );

        // Uploaded only: the partner signs it, nothing is generated.
        $step = ChecklistItem::query()->shared()->where('name', MasterDataSeeder::INTEGRITY_PACT_STEP)->sole();
        $this->assertSame(['pakta-integritas'], $step->documentTypes->pluck('code')->all());
        $this->assertTrue($step->documentTypes->first()->upload_only);
        $this->assertFalse($step->is_optional);

        foreach (['PJ', 'SPPL'] as $format) {
            $this->assertNotContains(MasterDataSeeder::INTEGRITY_PACT_STEP, $this->executionSteps($this->procurement($format)), $format);
        }
    }

    public function test_the_pakta_integritas_migration_leaves_finished_executions_alone(): void
    {
        $running = $this->procurement('SPK');
        $finished = $this->procurement('SPK');
        $finished->forceFill(['execution_approval_state' => PlanningApprovalState::Disetujui])->save();

        // A database from before the step.
        $step = ChecklistItem::query()->shared()->where('name', MasterDataSeeder::INTEGRITY_PACT_STEP)->sole();
        DB::table('procurement_checklists')->where('checklist_item_id', $step->id)->delete();
        DB::table('checklist_item_document_type')->where('checklist_item_id', $step->id)->delete();
        DB::table('checklist_item_format_exclusions')->where('checklist_item_id', $step->id)->delete();
        DB::table('checklist_items')->where('id', $step->id)->delete();

        $migration = require database_path('migrations/2026_11_17_072803_add_the_spk_integrity_pact_step.php');
        $migration->up();
        $migration->up();

        $step = ChecklistItem::query()->shared()->where('name', MasterDataSeeder::INTEGRITY_PACT_STEP)->sole();
        $this->assertSame($this->sortOrder(MasterDataSeeder::PRICE_OPENING_STEP) + 1, $step->sort_order);
        $this->assertSame(['PJ', 'SPPL'], $step->excludedContractNumberFormats->pluck('code')->sort()->values()->all());
        $this->assertTrue($step->documentTypes->sole()->upload_only);

        foreach ([$running, $finished] as $procurement) {
            app(ProcurementService::class)->syncChecklists($procurement);
        }

        $this->assertContains(MasterDataSeeder::INTEGRITY_PACT_STEP, $this->executionSteps($running));
        $this->assertNotContains(MasterDataSeeder::INTEGRITY_PACT_STEP, $this->executionSteps($finished));
    }

    protected function procurement(string $format): Procurement
    {
        $procurement = Procurement::factory()->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $format)->value('id'),
            'work_category' => $format === 'SPPL' ? null : 'barang',
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        return $procurement->refresh();
    }

    /**
     * @return list<string>
     */
    protected function executionSteps(Procurement $procurement): array
    {
        return $procurement->checklists()
            ->where('stage', ProcurementStage::Pelaksanaan)
            ->with('checklistItem')
            ->get()
            ->sortBy(fn ($checklist): int => $checklist->checklistItem->sort_order)
            ->map(fn ($checklist): string => $checklist->checklistItem->name)
            ->values()
            ->all();
    }

    protected function sortOrder(string $name): int
    {
        return (int) ChecklistItem::query()->shared()->where('stage', ProcurementStage::Pelaksanaan)->where('name', $name)->value('sort_order');
    }
}
