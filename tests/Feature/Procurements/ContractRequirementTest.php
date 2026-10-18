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
use Database\Seeders\FinanceRequirementSeeder;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PjTemplateSeeder;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The Penyerahan and Pembayaran requirements are read from each
 * procurement's own contract, so they follow what is actually signed.
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

    public function test_an_spk_jasa_reads_its_requirements_from_its_own_contract(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $this->contract($procurement, 'spk-pasal');

        $this->sync($procurement);

        $template = FinanceRequirementSeeder::SOURCES['SPK Jasa']['items'];

        $this->assertSame(array_column($template['penyerahan'], 0), $this->names($procurement, FinanceSection::Penyerahan));
        $this->assertSame(array_column($template['pembayaran'], 0), $this->names($procurement, FinanceSection::Pembayaran));

        $csms = $this->item($procurement, 'Sertifikat CSMS dengan klasifikasi minimal Moderat');
        $this->assertSame('SPK (Surat Perintah Kerja) Pasal 6 ayat (8) huruf f', $csms->description);
        $this->assertSame($procurement->id, $csms->procurement_id);

        $addendum = $this->item($procurement, 'Salinan Addendum Pengadaan Barang/Jasa (apabila ada)');
        $this->assertSame('SPK (Surat Perintah Kerja) Pasal 5 ayat (1) huruf l', $addendum->description);
        $this->assertTrue($addendum->is_optional);

        // The template's requirements are not laid out beside them.
        $this->assertSame(0, $this->rows($procurement)->filter(fn ($row) => $row->checklistItem->procurement_id === null)->count());
    }

    public function test_an_edited_contract_changes_the_checklist_and_keeps_the_ticks(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $document = $this->contract($procurement, 'spk-pasal');
        $this->sync($procurement);

        $kept = $this->row($procurement, 'Laporan Pekerjaan');
        $kept->update(['is_completed' => true]);

        $dropped = $this->row($procurement, 'Dokumentasi Foto Pelaksanaan Pekerjaan');
        $dropped->update(['is_completed' => true]);

        $untouched = $this->row($procurement, 'Sertifikat CSMS dengan klasifikasi minimal Moderat');

        // The PIC rewrites Pasal 6 ayat (8): one paper out, one paper in.
        $document->update(['rendered_body' => str_replace(
            '<li><b>Dokumentasi Foto Pelaksanaan Pekerjaan</b></li>',
            '<li><b>Berita Acara Uji Fungsi</b></li>',
            $document->rendered_body,
        )]);
        $procurement->unsetRelation('documents');

        $this->sync($procurement);

        $names = $this->names($procurement, FinanceSection::Penyerahan);
        $this->assertContains('Berita Acara Uji Fungsi', $names);
        $this->assertSame(
            7,
            $this->rows($procurement)
                ->filter(fn ($row): bool => $row->checklistItem->section === FinanceSection::Penyerahan && $row->checklistItem->is_active)
                ->count(),
        );

        // An unchanged requirement is the same step, tick and all.
        $this->assertTrue($kept->refresh()->is_completed);
        $this->assertSame($untouched->id, $this->row($procurement, 'Sertifikat CSMS dengan klasifikasi minimal Moderat')->id);

        // A ticked requirement the contract dropped stays as history.
        $this->assertTrue(ProcurementChecklist::query()->whereKey($dropped->id)->exists());
        $this->assertFalse($dropped->checklistItem->refresh()->is_active);
    }

    public function test_the_template_stands_in_until_the_contract_is_drawn(): void
    {
        $procurement = $this->procurement('SPK', 'barang');
        $this->sync($procurement);

        $template = FinanceRequirementSeeder::SOURCES['SPK Barang']['items'];

        $this->assertSame(array_column($template['penyerahan'], 0), $this->names($procurement, FinanceSection::Penyerahan));
        $this->assertSame(0, ChecklistItem::query()->where('procurement_id', $procurement->id)->count());

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn ($page) => $page->where('detail.finance.requirements_source', ['penyerahan' => null, 'pembayaran' => null]));

        // Drawing the contract switches both tabs to it.
        $document = $this->contract($procurement, 'spk-pasal');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn ($page) => $page->where('detail.finance.requirements_source', [
                'penyerahan' => $document->title,
                'pembayaran' => $document->title,
            ]));

        $this->assertSame(0, $this->rows($procurement)->filter(fn ($row) => $row->checklistItem->procurement_id === null)->count());
    }

    public function test_a_surat_perjanjian_keeps_long_items_whole_and_mandatory(): void
    {
        $procurement = $this->procurement('PJ', 'barang');
        $this->contract($procurement, 'spk');
        $this->sync($procurement);

        $names = $this->names($procurement, FinanceSection::Pembayaran);
        $this->assertCount(8, $names);
        $this->assertCount(7, $this->names($procurement, FinanceSection::Penyerahan));

        // Its "(jika ada)" qualifies a detail mid-sentence, not the paper.
        $minutes = $this->item($procurement, $names[4]);
        $this->assertStringEndsWith('dilengkapi dengan tanda tangan elektronik yang telah tersertifikasi', $minutes->name);
        $this->assertFalse($minutes->is_optional);
        $this->assertSame('Surat Perjanjian (PJ) Pasal 10 ayat (1) huruf e', $minutes->description);

        $this->assertTrue($this->item($procurement, 'Surat keterangan bebas potong (jika ada)')->is_optional);
    }

    public function test_a_surat_pesanan_reads_its_payment_list_and_keeps_the_template_delivery_list(): void
    {
        $procurement = $this->procurement('SPPL', null);
        $this->contract($procurement, 'lampiran-sp');
        $this->sync($procurement);

        $payment = $this->names($procurement, FinanceSection::Pembayaran);
        $this->assertCount(15, $payment);
        $this->assertSame('Kwitansi tagihan bermaterai 3 (tiga) rangkap', $payment[1]);
        $this->assertSame('Lampiran SP Barang dan Jasa Bagian D huruf b', $this->item($procurement, $payment[1])->description);

        // Its delivery papers are written as sentences, so the template's
        // list of them is used.
        $this->assertSame(
            array_column(FinanceRequirementSeeder::SOURCES['Lampiran Surat Pesanan']['items']['penyerahan'], 0),
            $this->names($procurement, FinanceSection::Penyerahan),
        );
    }

    public function test_the_requirements_are_frozen_once_paid(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $document = $this->contract($procurement, 'spk-pasal');
        $this->sync($procurement);

        $procurement->forceFill(['payment_completed_at' => now()])->save();
        $document->update(['rendered_body' => str_replace('Laporan Pekerjaan</b>', 'Laporan Akhir</b>', $document->rendered_body)]);
        $procurement->unsetRelation('documents');

        $this->sync($procurement);

        $this->assertContains('Laporan Pekerjaan', $this->names($procurement, FinanceSection::Penyerahan));
        $this->assertNotContains('Laporan Akhir', $this->names($procurement, FinanceSection::Penyerahan));
    }

    public function test_an_unreadable_contract_falls_back_without_breaking_the_page(): void
    {
        $procurement = $this->procurement('SPK', 'jasa');
        $document = $this->contract($procurement, 'spk-pasal');
        $document->update(['rendered_body' => '<p>Kontrak tanpa daftar <ol><li>terputus']);
        $procurement->unsetRelation('documents');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $procurement))
            ->assertOk();

        $this->assertSame(
            array_column(FinanceRequirementSeeder::SOURCES['SPK Jasa']['items']['penyerahan'], 0),
            $this->names($procurement, FinanceSection::Penyerahan),
        );
    }

    public function test_a_contract_s_requirements_stay_with_its_procurement(): void
    {
        $first = $this->procurement('SPK', 'jasa');
        $this->contract($first, 'spk-pasal');
        $this->sync($first);

        $second = $this->procurement('SPK', 'jasa');
        $this->sync($second);

        // The second has no contract yet: it gets the template, not the first's items.
        $this->assertSame(0, $this->rows($second)->filter(fn ($row) => $row->checklistItem->procurement_id !== null)->count());

        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('master-data.checklist-items.index'))
            ->assertInertia(fn ($page) => $page->where('records', fn ($records) => collect($records)->pluck('id')
                ->intersect(ChecklistItem::query()->whereNotNull('procurement_id')->pluck('id'))
                ->isEmpty()));

        $own = ChecklistItem::query()->where('procurement_id', $first->id)->firstOrFail();

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
