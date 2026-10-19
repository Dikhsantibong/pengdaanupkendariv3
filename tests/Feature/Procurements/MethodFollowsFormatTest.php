<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\BudgetSource;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The procurement method is no longer asked on the form: each contract
 * format names its method (SPK by SPK, PJ by tender, SPPL by surat pesanan)
 * and a procurement takes it from its format.
 */
class MethodFollowsFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
    }

    public function test_each_seeded_format_names_its_method(): void
    {
        $methods = ContractNumberFormat::query()->forContracts()->with('procurementMethod')->get()
            ->mapWithKeys(fn (ContractNumberFormat $format): array => [$format->code => $format->procurementMethod?->code])
            ->all();

        $this->assertSame(['SPK' => 'spk', 'PJ' => 'tender', 'SPPL' => 'surat-pesanan'], $methods);
    }

    public function test_a_procurement_registered_without_a_method_takes_it_from_its_format(): void
    {
        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->post(route('procurements.store'), [
                'contract_number_format_id' => $pj->id,
                'contract_type_id' => ContractType::query()->firstOrFail()->id,
                'name' => 'Pengadaan Tanpa Metode',
                'work_director_id' => WorkDirector::query()->firstOrFail()->id,
                'target_unit_ids' => [TargetUnit::query()->firstOrFail()->id],
                'budget_source_id' => BudgetSource::query()->firstOrFail()->id,
                'hpe_value' => 10_000_000,
                'progress_status_id' => ProgressStatus::defaultStatus()?->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('tender', Procurement::query()->sole()->procurementMethod?->code);
    }

    public function test_changing_the_format_changes_the_method(): void
    {
        $spk = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();
        $sppl = ContractNumberFormat::query()->where('code', 'SPPL')->firstOrFail();

        $procurement = Procurement::factory()->create(['contract_number_format_id' => $spk->id, 'procurement_method_id' => null]);
        $this->assertSame('spk', $procurement->refresh()->procurementMethod?->code);

        $procurement->update(['contract_number_format_id' => $sppl->id]);
        $this->assertSame('surat-pesanan', $procurement->refresh()->procurementMethod?->code);

        // Saving anything else leaves the method alone.
        $procurement->update(['name' => 'Nama Baru']);
        $this->assertSame('surat-pesanan', $procurement->refresh()->procurementMethod?->code);
    }

    public function test_a_format_without_a_method_keeps_the_procurements_own(): void
    {
        $format = ContractNumberFormat::factory()->create(['procurement_method_id' => null]);
        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();

        $procurement = Procurement::factory()->create(['procurement_method_id' => $tender->id]);
        $procurement->update(['contract_number_format_id' => $format->id]);

        $this->assertSame($tender->id, $procurement->refresh()->procurement_method_id);
    }

    public function test_a_proposal_waits_for_the_format_only(): void
    {
        $procurement = Procurement::factory()->create([
            'proposal_submitted_at' => now(),
            'procurement_method_id' => null,
            'contract_number_format_id' => null,
        ]);

        $this->assertSame(['Jenis Kontrak'], $procurement->missingPlanningData());
    }

    public function test_the_administrator_sets_the_method_of_a_format(): void
    {
        $spk = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();
        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();

        $payload = [
            'code' => 'SPK',
            'name' => $spk->name,
            'prefix' => $spk->prefix,
            'unit_segment' => $spk->unit_segment,
            'sequence_length' => $spk->sequence_length,
            'starting_sequence' => $spk->starting_sequence,
            'sort_order' => $spk->sort_order,
            'is_active' => true,
        ];

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->put(route('master-data.contract-number-formats.update', $spk), [...$payload, 'procurement_method_id' => (string) $tender->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($tender->id, $spk->refresh()->procurement_method_id);

        $this->actingAs($admin)
            ->put(route('master-data.contract-number-formats.update', $spk), [...$payload, 'procurement_method_id' => 'none'])
            ->assertSessionHasNoErrors();

        $this->assertNull($spk->refresh()->procurement_method_id);
    }

    public function test_seeding_again_does_not_bring_back_a_deleted_checklist_item(): void
    {
        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Pelaksanaan)
            ->where('name', 'Surat Pesanan')
            ->firstOrFail();
        $item->delete();

        $this->seed(MasterDataSeeder::class);

        $this->assertSame(0, ChecklistItem::query()->forStage(ProcurementStage::Pelaksanaan)->where('name', 'Surat Pesanan')->count());
        $this->assertSoftDeleted($item);

        // Items still in use are kept up to date as before.
        $this->assertSame(1, ChecklistItem::query()->forStage(ProcurementStage::Pelaksanaan)->where('name', 'BA Negosiasi')->count());
    }

    public function test_the_form_no_longer_offers_the_method(): void
    {
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('procurements.create'))
            ->assertInertia(fn ($page) => $page->missing('options.procurementMethods'));
    }
}
