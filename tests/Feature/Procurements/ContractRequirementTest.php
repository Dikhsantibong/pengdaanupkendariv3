<?php

namespace Tests\Feature\Procurements;

use App\Enums\FinanceSection;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\ContractRequirementReader;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use App\Services\ProcurementStepService;
use Database\Seeders\FinanceRequirementSeeder;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PjTemplateSeeder;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The Penyerahan and Pembayaran steps are typed by hand on each procurement:
 * neither the contract nor the Data Master adds any. The contract's list is
 * only offered as recommendations, and steps already there are kept.
 */
class ContractRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(FinanceRequirementSeeder::class);
        $this->seed(KontrakTemplateSeeder::class);
        $this->seed(PjTemplateSeeder::class);
        $this->seed(SpplDocumentTemplateSeeder::class);
    }

    public function test_neither_the_contract_nor_the_data_master_adds_handover_or_payment_steps(): void
    {
        foreach (['SPK' => 'jasa', 'PJ' => 'barang', 'SPPL' => null] as $format => $category) {
            $procurement = $this->procurement($format, $category);
            $this->contract($procurement, ['SPK' => 'spk-pasal', 'PJ' => 'spk', 'SPPL' => 'lampiran-sp'][$format]);

            $this->sync($procurement);

            // The Data Master has steps for these tabs, and the contract
            // lists requirements, yet none becomes a step by itself.
            $this->assertGreaterThan(0, ChecklistItem::query()->shared()->whereNotNull('section')->count());
            $this->assertSame([], $this->names($procurement, FinanceSection::Penyerahan), $format);
            $this->assertSame([], $this->names($procurement, FinanceSection::Pembayaran), $format);
            $this->assertSame(0, ChecklistItem::query()->where('procurement_id', $procurement->id)->count(), $format);
        }
    }

    public function test_planning_and_execution_still_take_their_data_master_steps(): void
    {
        $planning = ChecklistItem::query()->shared()->where('stage', ProcurementStage::Perencanaan)->exists();
        $procurement = $this->procurement('SPK', 'jasa');
        $this->sync($procurement);

        $this->assertTrue($planning);
        $this->assertGreaterThan(0, $procurement->checklists()->where('stage', ProcurementStage::Perencanaan)->count());
    }

    public function test_editing_the_contract_changes_nothing_on_the_tabs(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $document = $this->contract($procurement, 'spk-pasal');
        app(ProcurementStepService::class)->addTyped($procurement, User::factory()->administrator()->create(), 'penyerahan', 'Laporan Pekerjaan', null, false);

        $document->update(['rendered_body' => str_replace(
            '<li><b>Dokumentasi Foto Pelaksanaan Pekerjaan</b></li>',
            '<li><b>Berita Acara Uji Fungsi</b></li>',
            $document->rendered_body,
        )]);
        $procurement->unsetRelation('documents');

        $this->sync($procurement);

        $this->assertSame(['Laporan Pekerjaan'], $this->names($procurement, FinanceSection::Penyerahan));
        $this->assertSame([], $this->names($procurement, FinanceSection::Pembayaran));
    }

    public function test_the_contract_s_list_is_only_a_recommendation(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $this->contract($procurement, 'spk-pasal');
        $this->sync($procurement);

        $steps = app(ProcurementStepService::class);
        $handover = $steps->suggestions($procurement, 'penyerahan');
        $payment = $steps->suggestions($procurement, 'pembayaran');

        // The contract's requirements come first, marked as such.
        $contractHandover = array_values(array_filter($handover, fn (array $suggestion): bool => $suggestion['from_contract']));
        $this->assertNotEmpty($contractHandover);
        $this->assertSame($contractHandover, array_slice($handover, 0, count($contractHandover)));
        $this->assertContains('Laporan Pekerjaan', array_column($contractHandover, 'name'));
        $this->assertNotEmpty(array_filter($payment, fn (array $suggestion): bool => $suggestion['from_contract']));

        // Recommending adds nothing.
        $this->assertSame([], $this->names($procurement, FinanceSection::Penyerahan));

        // Once typed, the name is no longer recommended.
        $steps->addTyped($procurement, User::factory()->administrator()->create(), 'penyerahan', 'Laporan Pekerjaan', null, false);
        $this->assertNotContains('Laporan Pekerjaan', array_column($steps->suggestions($procurement, 'penyerahan'), 'name'));
        $this->assertSame(['Laporan Pekerjaan'], $this->names($procurement, FinanceSection::Penyerahan));
    }

    public function test_an_unused_data_master_step_is_not_recommended(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $this->sync($procurement);

        ChecklistItem::query()->create([
            'stage' => ProcurementStage::Keuangan,
            'section' => FinanceSection::Penyerahan,
            'name' => 'Tahapan Data Master Tak Terpakai',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $this->assertNotContains(
            'Tahapan Data Master Tak Terpakai',
            array_column(app(ProcurementStepService::class)->suggestions($procurement, 'penyerahan'), 'name'),
        );
    }

    public function test_steps_a_procurement_already_has_are_kept(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $document = $this->contract($procurement, 'spk-pasal');

        // Read from the contract before the change, ticked and unticked.
        $this->readStep($procurement, FinanceSection::Penyerahan, 'Laporan Pekerjaan (dari kontrak)', true);
        $this->readStep($procurement, FinanceSection::Penyerahan, 'Berita Acara (dari kontrak)', false);

        // Taken from the Data Master before the change.
        $master = ChecklistItem::query()->shared()
            ->forContractNumberFormat($procurement->contract_number_format_id)
            ->forWorkCategory('jasa')
            ->where('section', FinanceSection::Pembayaran->value)
            ->firstOrFail();
        $procurement->checklists()->create(['checklist_item_id' => $master->id, 'stage' => ProcurementStage::Keuangan, 'is_completed' => false]);

        $this->sync($procurement);

        $this->assertSame(
            ['Laporan Pekerjaan (dari kontrak)', 'Berita Acara (dari kontrak)'],
            $this->names($procurement, FinanceSection::Penyerahan),
        );
        $this->assertTrue($this->row($procurement, 'Laporan Pekerjaan (dari kontrak)')->is_completed);
        $this->assertSame([$master->name], $this->names($procurement, FinanceSection::Pembayaran));

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('detail.finance.requirements_source.penyerahan', $document->title)
                ->where('detail.finance.requirements_waiting_for', []));
    }

    public function test_an_unreadable_contract_does_not_break_the_page_or_the_recommendations(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $document = $this->contract($procurement, 'spk-pasal');
        $document->update(['rendered_body' => '<p>Kontrak tanpa daftar <ol><li>terputus']);
        $procurement->unsetRelation('documents');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $procurement))
            ->assertOk();

        $this->assertIsArray(app(ProcurementStepService::class)->suggestions($procurement, 'penyerahan'));
        $this->assertSame([], $this->names($procurement, FinanceSection::Penyerahan));
    }

    public function test_a_procurement_s_own_steps_stay_out_of_the_data_master(): void
    {
        $first = $this->procurement('SPK', 'jasa');
        $own = app(ProcurementStepService::class)->addTyped($first, User::factory()->administrator()->create(), 'penyerahan', 'Laporan Khusus', null, false);

        $second = $this->procurement('SPK', 'jasa');
        $this->sync($second);

        $this->assertSame([], $this->names($second, FinanceSection::Penyerahan));

        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('master-data.checklist-items.index'))
            ->assertInertia(fn ($page) => $page->where('records', fn ($records) => collect($records)->pluck('id')
                ->intersect(ChecklistItem::query()->whereNotNull('procurement_id')->pluck('id'))
                ->isEmpty()));

        $this->actingAs($administrator)
            ->delete(route('master-data.checklist-items.destroy', $own))
            ->assertNotFound();
    }

    public function test_the_reader_only_takes_a_list_that_directly_follows_the_phrase(): void
    {
        $reader = app(ContractRequirementReader::class);

        // The phrase in running text, with a list further down another item.
        $html = '<ol><li>Diserahkan dengan melampirkan copy Surat Pesanan.</li></ol>'
            .'<ol><li>Bukan syarat</li></ol>'
            .'<div class="pasal"><div>Pasal 9</div></div><table class="ayat"><tr><td class="no">(2)</td>'
            .'<td>Tagihan dengan melampirkan:<ol><li>Invoice;</li><li>Faktur (apabila ada).</li></ol></td></tr></table>';

        $items = $reader->requirementsIn($html, ContractRequirementReader::MARKERS['pembayaran'], 'SPK');

        $this->assertSame(['Invoice', 'Faktur (apabila ada)'], array_column($items ?? [], 'name'));
        $this->assertSame(['SPK Pasal 9 ayat (2) huruf a', 'SPK Pasal 9 ayat (2) huruf b'], array_column($items ?? [], 'description'));
        $this->assertSame([false, true], array_column($items ?? [], 'is_optional'));

        $this->assertNull($reader->requirementsIn('<p>Tidak ada daftar.</p>', ContractRequirementReader::MARKERS['penyerahan'], 'SPK'));
    }

    /**
     * A procurement under one contract format.
     */
    protected function procurement(string $format, ?string $category): Procurement
    {
        $procurement = Procurement::factory()->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $format)->value('id'),
            'work_category' => $category,
        ]);

        return $procurement->refresh();
    }

    /**
     * Draw a procurement's contract document from its template.
     */
    protected function contract(Procurement $procurement, string $code): ProcurementDocument
    {
        $document = app(DocumentGenerator::class)->generate(
            $procurement,
            DocumentType::query()->where('code', $code)->firstOrFail(),
            User::factory()->administrator()->create(),
        );

        $procurement->unsetRelation('documents');

        return $document;
    }

    /**
     * A step read from the procurement's contract before the steps came from
     * the Data Master, with its checklist row.
     */
    protected function readStep(Procurement $procurement, FinanceSection $section, string $name, bool $ticked): ChecklistItem
    {
        $item = ChecklistItem::query()->create([
            'procurement_id' => $procurement->id,
            'stage' => ProcurementStage::Keuangan,
            'section' => $section,
            'name' => $name,
            'sort_order' => ChecklistItem::query()->where('procurement_id', $procurement->id)->count() + 1,
            'is_active' => true,
        ]);

        $procurement->checklists()->create([
            'checklist_item_id' => $item->id,
            'stage' => ProcurementStage::Keuangan,
            'is_completed' => $ticked,
        ]);

        return $item;
    }

    protected function sync(Procurement $procurement): void
    {
        app(ProcurementService::class)->syncChecklists($procurement);
    }

    /**
     * The Keuangan checklist rows of a procurement.
     *
     * @return Collection<int, ProcurementChecklist>
     */
    protected function rows(Procurement $procurement)
    {
        return $procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan)
            ->with('checklistItem')
            ->get();
    }

    /**
     * The requirement names on one tab, in the contract's order.
     *
     * @return array<int, string>
     */
    protected function names(Procurement $procurement, FinanceSection $section): array
    {
        return $this->rows($procurement)
            ->filter(fn ($row): bool => $row->checklistItem->section === $section)
            ->sortBy(fn ($row): int => $row->checklistItem->sort_order)
            ->map(fn ($row): string => $row->checklistItem->name)
            ->values()
            ->all();
    }

    protected function row(Procurement $procurement, string $name): ProcurementChecklist
    {
        return $this->rows($procurement)->firstOrFail(fn ($row): bool => $row->checklistItem->name === $name);
    }

    protected function item(Procurement $procurement, string $name): ChecklistItem
    {
        return $this->row($procurement, $name)->checklistItem;
    }
}
