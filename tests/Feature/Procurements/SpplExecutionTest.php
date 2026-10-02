<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The execution stage of an SPPL, and the inputs and alternatives it uses.
 */
class SpplExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sppl_runs_its_own_execution_steps(): void
    {
        $this->assertSame([
            'Evaluasi Dokumen',
            'BA Negosiasi',
            'Purchase Order (PO)',
            'Rekening Pelaksana',
            'Surat Pesanan',
            'Rentang Waktu Pelaksanaan',
            'Masa Garansi',
        ], $this->executionSteps('SPPL'));
    }

    public function test_spk_and_pj_keep_their_steps_without_penyusunan_kontrak(): void
    {
        $expected = [
            'Evaluasi Dokumen',
            'Penyusunan HPS',
            'Proses SMART SCM',
            'Berita Acara',
            'Purchase Order (PO)',
            'Jaminan Bank',
            'Kontrak',
            'Rentang Waktu',
            'Amandemen',
            'Masa Pemeliharaan',
        ];

        $this->assertSame($expected, $this->executionSteps('SPK'));
        $this->assertSame($expected, $this->executionSteps('PJ'));
    }

    public function test_the_end_date_counts_the_start_date_as_day_one(): void
    {
        $procurement = Procurement::factory()->make([
            'execution_start_date' => '2026-10-01',
            'execution_duration_days' => 30,
        ]);

        $this->assertSame('2026-10-30', $procurement->executionEndDate()?->toDateString());
    }

    public function test_an_input_step_needs_its_data_before_it_is_ticked(): void
    {
        [$procurement, $executor, $checklist] = $this->spplStep('Rentang Waktu Pelaksanaan');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), ['is_completed' => true])
            ->assertSessionHasErrors('is_completed');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $checklist]), [
                'execution_start_date' => '2026-10-01',
                'execution_duration_days' => 14,
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame('2026-10-14', $procurement->executionEndDate()?->toDateString());

        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), ['is_completed' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($checklist->refresh()->is_completed);
    }

    public function test_the_bank_account_and_warranty_are_saved(): void
    {
        [$procurement, $executor, $rekening] = $this->spplStep('Rekening Pelaksana');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $rekening]), [
                'bank_account_number' => '0123 4567 89',
                'bank_name' => 'BRI',
                'bank_account_holder' => 'CV Maju Jaya',
            ])
            ->assertSessionHasNoErrors();

        $garansi = $procurement->checklists()
            ->whereHas('checklistItem', fn ($query) => $query->where('name', 'Masa Garansi'))
            ->firstOrFail();

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $garansi]), ['warranty_months' => 3])
            ->assertSessionHasNoErrors();

        $procurement->refresh();

        $this->assertSame('0123 4567 89', $procurement->bank_account_number);
        $this->assertSame('BRI', $procurement->bank_name);
        $this->assertSame('CV Maju Jaya', $procurement->bank_account_holder);
        $this->assertSame(3, $procurement->warranty_months);
    }

    public function test_a_step_only_writes_its_own_fields(): void
    {
        [$procurement, $executor, $garansi] = $this->spplStep('Masa Garansi');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $garansi]), [
                'warranty_months' => 2,
                'bank_name' => 'Disusupkan',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($procurement->refresh()->bank_name);
    }

    public function test_an_unassigned_pic_cannot_fill_in_a_step(): void
    {
        [$procurement, , $garansi] = $this->spplStep('Masa Garansi');

        $this->actingAs(User::factory()->executor()->create())
            ->put(route('procurements.checklists.input', [$procurement, $garansi]), ['warranty_months' => 2])
            ->assertForbidden();
    }

    public function test_either_surat_pesanan_barang_or_jasa_is_enough_for_the_surat_pesanan_step(): void
    {
        Storage::fake('local');

        [$procurement, $executor, $checklist] = $this->spplStep('Surat Pesanan');

        // Neither alternative is signed yet: the step cannot be completed.
        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), ['is_completed' => true])
            ->assertSessionHasErrors('is_completed');

        $this->sign($procurement, $executor, 'surat-pesanan-jasa');

        // Jasa alone completes the choice; Barang is not needed.
        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), ['is_completed' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($checklist->refresh()->is_completed);
    }

    public function test_the_surat_pesanan_carries_the_negotiated_value_and_the_inputs(): void
    {
        $this->seed(MasterDataSeeder::class);

        $procurement = Procurement::factory()->create([
            'value_after_negotiation' => 25_000_000,
            'execution_start_date' => '2026-10-01',
            'execution_duration_days' => 30,
            'warranty_months' => 3,
            'bank_account_number' => '1234567890',
            'bank_name' => 'Mandiri',
            'bank_account_holder' => 'CV Maju Jaya',
        ]);

        $values = app(DocumentGenerator::class)->placeholderValues($procurement);

        $this->assertSame('Rp 25.000.000,00', $values['nilai_setelah_nego']);
        $this->assertStringContainsString('dua puluh lima juta', strtolower($values['nilai_setelah_nego_terbilang']));
        $this->assertSame('22.916.667', $values['nilai_setelah_nego_dpp_angka']);
        $this->assertSame('3.000.000', $values['nilai_setelah_nego_ppn_angka']);
        $this->assertSame('28.000.000', $values['nilai_setelah_nego_total_angka']);
        $this->assertSame('30', $values['jangka_waktu_hari']);
        $this->assertStringContainsString('30 Oktober 2026', $values['tanggal_selesai_pelaksanaan']);
        $this->assertSame('3', $values['masa_garansi_bulan']);
        $this->assertSame('tiga', $values['masa_garansi_bulan_terbilang']);
        $this->assertSame('1234567890', $values['nomor_rekening']);
        $this->assertSame('Mandiri', $values['nama_bank']);
        $this->assertSame('CV Maju Jaya', $values['nama_pemilik_rekening']);
    }

    public function test_an_administrator_sets_a_step_input_and_alternatives(): void
    {
        $this->seed(MasterDataSeeder::class);

        $item = ChecklistItem::query()->where('name', 'Purchase Order (PO)')->firstOrFail();
        $barang = DocumentType::query()->where('code', 'lampiran-sp-barang')->firstOrFail();
        $jasa = DocumentType::query()->where('code', 'lampiran-sp-jasa')->firstOrFail();

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('master-data.checklist-items.update', $item), [
                'stage' => $item->stage->value,
                'name' => $item->name,
                'description' => null,
                'is_optional' => false,
                'input_kind' => 'warranty',
                'sort_order' => $item->sort_order,
                'is_active' => true,
                'document_type_ids' => [],
                'alternative_document_type_ids' => [$barang->id, $jasa->id],
            ])
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame('warranty', $item->input_kind?->value);
        $this->assertSame(
            [true, true],
            $item->documentTypes->map(fn (DocumentType $type): bool => (bool) $type->pivot?->getAttribute('is_alternative'))->all(),
        );

        // "none" clears the input again.
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('master-data.checklist-items.update', $item), [
                'stage' => $item->stage->value,
                'name' => $item->name,
                'is_optional' => false,
                'input_kind' => 'none',
                'sort_order' => $item->sort_order,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($item->refresh()->input_kind);
    }

    /**
     * The execution step names a format goes through, in order.
     *
     * @return array<int, string>
     */
    protected function executionSteps(string $format): array
    {
        $this->seed(MasterDataSeeder::class);

        $formatId = ContractNumberFormat::query()->where('code', $format)->value('id');

        $procurement = Procurement::factory()->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => $formatId,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        return $procurement->checklists()
            ->where('stage', ProcurementStage::Pelaksanaan->value)
            ->with('checklistItem')
            ->get()
            ->sortBy(fn (ProcurementChecklist $row): array => [$row->checklistItem->sort_order, $row->checklistItem->name])
            ->map(fn (ProcurementChecklist $row): string => $row->checklistItem->name)
            ->values()
            ->all();
    }

    /**
     * An SPPL execution step ready to work on, with its executor.
     *
     * @return array{0: Procurement, 1: User, 2: ProcurementChecklist}
     */
    protected function spplStep(string $name): array
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

        $checklist = $procurement->checklists()
            ->whereHas('checklistItem', fn ($query) => $query->where('name', $name))
            ->firstOrFail();

        return [$procurement, $executor, $checklist];
    }

    /**
     * Generate a document and file its signed scan.
     */
    protected function sign(Procurement $procurement, User $executor, string $code): void
    {
        $type = DocumentType::query()->where('code', $code)->firstOrFail();

        $this->actingAs($executor)
            ->post(route('procurements.documents.store', $procurement), ['document_type_id' => $type->id])
            ->assertSessionHasNoErrors();

        $document = $procurement->documents()->where('document_type_id', $type->id)->latest('id')->firstOrFail();

        $this->actingAs($executor)
            ->post(route('procurements.documents.signed.store', [$procurement, $document]), [
                'files' => [UploadedFile::fake()->create($code.'.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();
    }
}
