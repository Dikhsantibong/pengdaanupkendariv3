<?php

namespace Tests\Feature\Procurements;

use App\Enums\UserRole;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\TargetUnit;
use App\Models\User;
use App\Services\DocumentTemplateLibrary;
use App\Services\ProcurementService;
use App\Support\FinanceLetters;
use App\Support\MasterDataOptions;
use Database\Seeders\FinanceRequirementSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The letters the partner writes on its own letterhead for Keuangan (Surat
 * Jalan, Surat Pengantar Penyerahan Pekerjaan, Berita Acara Pemeriksaan,
 * Surat Pernyataan Garansi Material, Surat Permohonan Pembayaran) are downloaded from their step as Word, filled in
 * with the contract's data, and uploaded back once signed; they are never
 * generated.
 */
class FinanceLettersTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(FinanceRequirementSeeder::class);
        app(DocumentTemplateLibrary::class)->sync(groups: ['keuangan']);

        $this->finance = User::factory()->create(['role' => UserRole::Keuangan]);
        $this->procurement = $this->procurementOf('SPK', 'barang');
    }

    public function test_the_letter_steps_offer_their_template(): void
    {
        $this->actingAs($this->finance)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page->where('checklists.keuangan', function (Collection $rows): bool {
                $offered = $rows->filter(fn (array $row): bool => $row['template_url'] !== null)->pluck('name')->sort()->values()->all();

                $this->assertSame([
                    'Asli Berita Acara Pemeriksaan Barang',
                    'Asli atau Salinan Surat Jalan / Delivery Order',
                    'Permohonan Pembayaran',
                    'Surat Jalan / Delivery Order',
                    'Surat Pernyataan Garansi Barang',
                    'Surat Pernyataan Garansi Barang',
                ], $offered);

                return true;
            }));
    }

    public function test_the_payment_request_is_filled_in_with_the_contract(): void
    {
        $response = $this->download('Permohonan Pembayaran')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/msword; charset=UTF-8');

        $this->assertStringContainsString('Surat Permohonan Pembayaran KDD066.SPK-612-UPKD-2026.doc', (string) $response->headers->get('Content-Disposition'));

        $letter = $response->streamedContent();
        $this->assertStringContainsString('<w:View>Print</w:View>', $letter);
        $this->assertStringContainsString('KOP SURAT PERUSAHAAN', $letter);
        $this->assertStringContainsString('Surat Perintah Kerja (SPK) No. KDD066.SPK/612/UPKD/2026 tanggal 30 Juli 2026', $letter);
        $this->assertStringContainsString('Rp 112.000.000 (Seratus Dua Belas Juta Rupiah) sudah termasuk PPN', $letter);
        $this->assertStringContainsString('BANK BRI', $letter);
        $this->assertStringContainsString('0587.01.000012.30.6', $letter);
        $this->assertStringContainsString('( NAMA DIREKTUR )', $letter);
        // Plain text for Word: no live fields and nothing left unfilled.
        $this->assertStringNotContainsString('data-field', $letter);
        $this->assertStringNotContainsString('{{', $letter);
    }

    public function test_the_surat_jalan_lists_the_items_for_the_receiving_unit(): void
    {
        $this->procurement->contractItems()->createMany([
            ['sort_order' => 1, 'description' => 'Packing Oil Cooler', 'part_number' => '37739-10200', 'volume' => '1', 'unit' => 'Bh'],
            ['sort_order' => 2, 'description' => 'O Ring', 'part_number' => '05507-10140', 'volume' => '16', 'unit' => 'Bh'],
        ]);

        $letter = $this->download('Surat Jalan / Delivery Order')->assertOk()->streamedContent();

        $this->assertStringContainsString('SURAT JALAN', $letter);
        $this->assertStringContainsString('Packing Oil Cooler', $letter);
        $this->assertStringContainsString('05507-10140', $letter);
        $this->assertStringContainsString('>16<', $letter);
        // Up: the Manager UL holding the unit; received by its TL Pemeliharaan.
        $this->assertStringContainsString('MANAGER ULPLTD WUA WUA', $letter);
        $this->assertStringContainsString('PLTD WUA WUA', $letter);
        $this->assertStringContainsString('SURYADI PRATAMA', $letter);
    }

    public function test_a_unit_with_its_own_tl_ophar_is_received_by_them(): void
    {
        $this->procurement->update(['target_unit_id' => TargetUnit::query()->where('name', 'PLTD Langara')->value('id')]);

        $letter = $this->download('Surat Jalan / Delivery Order')->assertOk()->streamedContent();

        $this->assertStringContainsString('INDRA MULYADI', $letter);
        $this->assertStringNotContainsString('SURYADI PRATAMA', $letter);
    }

    public function test_the_berita_acara_is_titled_after_its_step(): void
    {
        $goods = $this->download('Asli Berita Acara Pemeriksaan Barang')->assertOk()->streamedContent();

        $this->assertStringContainsString('BERITA ACARA PEMERIKSAAN BARANG', $goods);
        $this->assertStringContainsString('telah selesai dengan bobot 100%', $goods);
        // Received by the unit, known by its Manager UL.
        $this->assertStringContainsString('SURYADI PRATAMA', $goods);
        $this->assertStringContainsString('ABDUL RAHMAN KADIR', $goods);

        $this->procurement = $this->procurementOf('SPK', 'jasa');

        $this->assertStringContainsString(
            'BERITA ACARA PENYELESAIAN PEKERJAAN',
            $this->download('Asli Berita Acara Penyelesaian Pekerjaan')->assertOk()->streamedContent(),
        );
    }

    public function test_the_guarantee_letter_states_the_guarantee_period(): void
    {
        $letter = $this->download('Surat Pernyataan Garansi Barang')->assertOk()->streamedContent();

        $this->assertStringContainsString('SURAT PERNYATAAN GARANSI MATERIAL', $letter);
        $this->assertStringContainsString('6 (enam) Bulan', $letter);
        $this->assertStringContainsString('KDD066.SPK/612/UPKD/2026', $letter);
    }

    public function test_the_cover_letter_hands_the_work_over_for_inspection(): void
    {
        $this->procurement = $this->procurementOf('SPK', 'jasa');

        $letter = $this->download('Asli Surat Penyerahan Pekerjaan')->assertOk()->streamedContent();

        $this->assertStringContainsString('Surat Pengantar Penyerahan Pekerjaan', $letter);
        $this->assertStringContainsString('DIREKSI PEKERJAAN', $letter);
        $this->assertStringContainsString('KDD067.SPK/612/UPKD/2026', $letter);
        $this->assertStringContainsString('segera diterbitkan Berita Acara Penyelesaian Pekerjaan', $letter);
        $this->assertStringContainsString('Link Drive', $letter);
    }

    public function test_only_a_letter_step_of_this_procurement_downloads_one(): void
    {
        $this->actingAs($this->finance)
            ->get(route('procurements.checklists.template', [$this->procurement, $this->step('Packing List')]))
            ->assertNotFound();

        // The guarantee of the work done is not a material's guarantee.
        $services = $this->procurementOf('SPK', 'jasa');
        $workGuarantee = $services->checklists()->whereHas('checklistItem', fn ($query) => $query->where('name', 'Surat Pernyataan Garansi Pekerjaan'))->firstOrFail();
        $this->assertNull(FinanceLetters::codeFor($workGuarantee));

        $this->actingAs($this->finance)
            ->get(route('procurements.checklists.template', [$services, $this->step('Permohonan Pembayaran')]))
            ->assertNotFound();
    }

    public function test_the_letters_are_never_generated(): void
    {
        $letterTypes = DocumentType::query()->whereIn('code', FinanceLetters::codes())->pluck('id');
        $this->assertCount(5, $letterTypes);

        $documentTypes = MasterDataOptions::forProcurementDetail($this->procurement)['documentTypes'];
        $this->assertIsArray($documentTypes);
        $this->assertSame([], array_intersect(array_column($documentTypes, 'value'), $letterTypes->all()));

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('procurements.documents.store', $this->procurement), ['document_type_id' => $letterTypes->first()])
            ->assertSessionHasErrors('document_type_id');
    }

    /**
     * A procurement in Keuangan, under its contract.
     */
    protected function procurementOf(string $format, string $category): Procurement
    {
        $procurement = Procurement::factory()->planningApproved()->create([
            'number' => $category === 'barang' ? 'KDD066.SPK/612/UPKD/2026' : 'KDD067.SPK/612/UPKD/2026',
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $format)->value('id'),
            'work_category' => $category,
            'target_unit_id' => TargetUnit::query()->where('name', 'PLTD Wua Wua')->value('id'),
            'value_after_negotiation' => 100000000,
            'warranty_months' => 6,
            'bank_name' => 'BANK BRI',
            'bank_account_number' => '0587.01.000012.30.6',
            'bank_account_holder' => 'CV. BINTANG MAS',
            'finance_submitted_at' => now(),
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        // The contract the letters refer to, drawn on 30 July 2026.
        $procurement->documents()->create([
            'document_type_id' => DocumentType::query()->where('code', 'spk-pasal')->value('id'),
            'title' => 'SPK - Syarat & Ketentuan (Pasal)',
            'file_name' => 'spk-pasal.html',
            'template_version' => 1,
            'rendered_body' => '<p>Pasal 1</p>',
            'generated_by' => $this->finance->id,
            'generated_at' => '2026-07-30 09:00:00',
        ]);

        return $procurement->refresh();
    }

    /**
     * A Keuangan step of the procurement, by name.
     */
    protected function step(string $name): ProcurementChecklist
    {
        return $this->procurement->checklists()
            ->whereHas('checklistItem', fn ($query) => $query->where('name', $name))
            ->firstOrFail();
    }

    /**
     * Download the letter of a step.
     *
     * @return TestResponse<Response>
     */
    protected function download(string $step): TestResponse
    {
        return $this->actingAs($this->finance)
            ->get(route('procurements.checklists.template', [$this->procurement, $this->step($step)]));
    }
}
