<?php

namespace Tests\Feature\Procurements;

use App\Enums\ChecklistInput;
use App\Enums\PlanningApprovalState;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A job is registered under an internal number; the executor gives the
 * contract number on the Kontrak step (SPK/PJ) or the Surat Pesanan step
 * (SPPL). The category is the planning PIC's, not the executor's.
 */
class ContractNumberInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->travelTo('2026-08-07');
    }

    /**
     * A job of the given format in execution, with its executor and one step.
     *
     * @return array{0: Procurement, 1: User, 2: ProcurementChecklist}
     */
    protected function step(string $format, string $name): array
    {
        $executor = User::factory()->executor()->create();

        $procurement = Procurement::factory()->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $format)->value('id'),
            'executor_id' => $executor->id,
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'number' => sprintf('PGD/2026/08/%04d', Procurement::withTrashed()->count() + 1),
            'contract_type_id' => null,
            'work_category' => null,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        $checklist = $procurement->checklists()
            ->whereHas('checklistItem', fn ($query) => $query->where('name', $name))
            ->firstOrFail();

        return [$procurement, $executor, $checklist];
    }

    public function test_each_format_asks_for_the_number_on_one_step(): void
    {
        foreach (['SPK' => 'Kontrak', 'PJ' => 'Kontrak', 'SPPL' => 'Surat Pesanan'] as $format => $name) {
            [$procurement] = $this->step($format, $name);

            $asking = $procurement->checklists()->with('checklistItem')->get()
                ->filter(fn (ProcurementChecklist $row): bool => $row->checklistItem->inputFor($procurement) === ChecklistInput::ContractNumber)
                ->map(fn (ProcurementChecklist $row): string => $row->checklistItem->name)
                ->values()
                ->all();

            $this->assertSame([$name], $asking, $format);
        }
    }

    public function test_the_executor_gives_the_number_before_ticking_the_step(): void
    {
        [$procurement, $executor, $kontrak] = $this->step('SPK', 'Kontrak');

        // The internal number is not a contract number.
        $this->assertFalse($procurement->hasContractNumber());
        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $kontrak]), ['is_completed' => true])
            ->assertSessionHasErrors('is_completed');

        // A category sent along is not the executor's to set.
        $jasa = ContractType::query()->where('code', 'jasa')->firstOrFail();
        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $kontrak]), [
                'number' => ' KDD075.SPK/612/UPKD/2026 ',
                'contract_type_id' => $jasa->id,
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame('KDD075.SPK/612/UPKD/2026', $procurement->number);
        $this->assertNull($procurement->contract_type_id);
        // The step still waits for its signed SPK, as before.
        $this->assertTrue(ChecklistInput::ContractNumber->isFilledOn($procurement));
    }

    public function test_an_sppl_gives_its_number_on_the_surat_pesanan(): void
    {
        [$procurement, $executor, $suratPesanan] = $this->step('SPPL', 'Surat Pesanan');

        // The step waits for the number before it can be ticked.
        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $suratPesanan]), ['is_completed' => true])
            ->assertSessionHasErrors('is_completed');

        $this->actingAs($executor)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.pelaksanaan', fn ($rows): bool => collect($rows)
                    ->firstWhere('name', 'Surat Pesanan')['input']['kind'] === 'contract_number'
                    && collect($rows)->firstWhere('name', 'Lampiran Surat Pesanan')['input'] === null));

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $suratPesanan]), ['number' => 'KDD010.SPPL/612/UPKD/2026'])
            ->assertSessionHasNoErrors();

        $this->assertSame('KDD010.SPPL/612/UPKD/2026', $procurement->refresh()->number);
        $this->assertTrue(ChecklistInput::ContractNumber->isFilledOn($procurement));
    }

    public function test_the_lampiran_of_an_sppl_no_longer_asks_for_the_number(): void
    {
        [$procurement, $executor, $lampiran] = $this->step('SPPL', 'Lampiran Surat Pesanan');

        $this->assertNull($lampiran->checklistItem->inputFor($procurement));

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $lampiran]), ['number' => 'KDD011.SPPL/612/UPKD/2026'])
            ->assertNotFound();
    }

    public function test_without_a_surat_pesanan_step_the_lampiran_keeps_asking_for_the_number(): void
    {
        // Removed in Data Master: the SPPL must still be asked somewhere.
        ChecklistItem::query()->where('stage', 'pelaksanaan')->where('name', 'Surat Pesanan')->firstOrFail()->delete();
        $this->seed(MasterDataSeeder::class);

        [$procurement, $executor, $lampiran] = $this->step('SPPL', 'Lampiran Surat Pesanan');

        $this->assertSame(ChecklistInput::ContractNumber, $lampiran->checklistItem->inputFor($procurement));

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $lampiran]), ['number' => 'KDD013.SPPL/612/UPKD/2026'])
            ->assertSessionHasNoErrors();

        $this->assertSame('KDD013.SPPL/612/UPKD/2026', $procurement->refresh()->number);
    }

    public function test_the_surat_pesanan_of_an_spk_still_asks_for_nothing(): void
    {
        foreach (['SPK', 'PJ'] as $format) {
            [$procurement, $executor, $suratPesanan] = $this->step($format, 'Surat Pesanan');

            $this->assertNull($suratPesanan->checklistItem->inputFor($procurement), $format);

            $this->actingAs($executor)
                ->put(route('procurements.checklists.input', [$procurement, $suratPesanan]), ['number' => 'KDD012.SPK/612/UPKD/2026'])
                ->assertNotFound();

            $this->assertFalse($procurement->refresh()->hasContractNumber(), $format);
        }
    }

    public function test_an_administrator_limits_a_step_input_to_one_format(): void
    {
        $administrator = User::factory()->administrator()->create();
        $item = ChecklistItem::query()->where('stage', 'pelaksanaan')->where('name', 'Surat Pesanan')->firstOrFail();
        $sppl = ContractNumberFormat::query()->where('code', 'SPPL')->value('id');

        $this->assertSame($sppl, $item->input_contract_number_format_id);

        $this->actingAs($administrator)
            ->get(route('master-data.checklist-items.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('records', fn ($records): bool => collect($records)
                    ->firstWhere('id', $item->id)['input_contract_number_format_id'] === (string) $sppl));

        $update = fn (string $format) => $this->actingAs($administrator)
            ->put(route('master-data.checklist-items.update', $item), [
                'stage' => 'pelaksanaan',
                'name' => $item->name,
                'description' => $item->description,
                'is_optional' => false,
                'input_kind' => 'contract_number',
                'input_contract_number_format_id' => $format,
                'sort_order' => $item->sort_order,
                'is_active' => true,
            ]);

        $update('none')->assertSessionHasNoErrors();
        $this->assertNull($item->refresh()->input_contract_number_format_id);

        $update((string) $sppl)->assertSessionHasNoErrors();
        $this->assertSame($sppl, $item->refresh()->input_contract_number_format_id);

        $update('99999')->assertSessionHasErrors('input_contract_number_format_id');
    }

    public function test_the_planner_picks_the_category_including_barang_dan_jasa(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
        ]);
        $both = ContractType::query()->where('code', 'barang-jasa')->firstOrFail();
        $this->assertSame('Barang dan Jasa', $both->name);

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), ['contract_type_id' => $both->id])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame($both->id, $procurement->contract_type_id);
        // Both at once prints the general SPK format.
        $this->assertNull($procurement->work_category);
    }

    public function test_the_number_must_be_a_free_contract_number(): void
    {
        [$procurement, $executor, $kontrak] = $this->step('PJ', 'Kontrak');
        Procurement::factory()->create(['number' => 'KDD020.PJ/612/UPKD/2026']);

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $kontrak]), ['number' => 'PGD/2026/08/0009'])
            ->assertSessionHasErrors('number');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $kontrak]), ['number' => 'KDD020.PJ/612/UPKD/2026'])
            ->assertSessionHasErrors('number');

        $this->assertSame('PGD/2026/08/0001', $procurement->refresh()->number);
    }

    public function test_the_step_offers_the_running_number_of_the_format(): void
    {
        [$procurement] = $this->step('SPK', 'Kontrak');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.pelaksanaan', fn ($rows) => collect($rows)
                    ->firstWhere('name', 'Kontrak')['input'] === [
                        ...collect($rows)->firstWhere('name', 'Kontrak')['input'],
                        'kind' => 'contract_number',
                        'is_filled' => false,
                    ]
                    && collect($rows)->firstWhere('name', 'Kontrak')['input']['values']['number'] === null
                    && collect($rows)->firstWhere('name', 'Kontrak')['input']['values']['suggested_number'] === 'KDD075.SPK/612/UPKD/2026'));
    }

    public function test_the_create_form_registers_the_job_without_a_contract_number(): void
    {
        $administrator = User::factory()->administrator()->create();
        $spk = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();
        $procurement = Procurement::factory()->make();

        $this->actingAs($administrator)
            ->post(route('procurements.store'), [
                'contract_number_format_id' => $spk->id,
                'name' => 'Pengadaan Pompa',
                'work_director_id' => $procurement->work_director_id,
                'target_unit_ids' => [$procurement->target_unit_id],
                'budget_source_id' => $procurement->budget_source_id,
                'hpe_value' => 1000000,
            ])
            ->assertSessionHasNoErrors();

        $created = Procurement::query()->where('name', 'Pengadaan Pompa')->firstOrFail();
        $this->assertStringStartsWith('PGD/2026/08/', $created->number);
        $this->assertNull($created->contract_type_id);
    }

    public function test_migration_moves_contract_number_to_surat_pesanan_and_preserves_existing_numbers(): void
    {
        $spplId = ContractNumberFormat::query()->where('code', 'SPPL')->value('id');
        $spkId = ContractNumberFormat::query()->where('code', 'SPK')->value('id');

        // Simulate the pre-migration state: Lampiran had contract_number, Surat Pesanan was deleted.
        $suratPesananItem = ChecklistItem::withTrashed()
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Surat Pesanan')
            ->firstOrFail();
        $lampiranItem = ChecklistItem::query()
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Lampiran Surat Pesanan')
            ->firstOrFail();

        $suratPesananItem->update([
            'input_kind' => null,
            'input_contract_number_format_id' => null,
        ]);
        $suratPesananItem->delete();

        $lampiranItem->update([
            'input_kind' => ChecklistInput::ContractNumber,
            'input_contract_number_format_id' => null,
        ]);

        // An existing SPPL job in execution that already had its contract number filled in,
        // and another SPPL job already submitted for execution approval.
        $executor = User::factory()->executor()->create();
        $inProgressJob = Procurement::factory()->create([
            'contract_number_format_id' => $spplId,
            'executor_id' => $executor->id,
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'number' => 'KDD056.SPPL/612/UPKD/2026',
        ]);
        $submittedJob = Procurement::factory()->create([
            'contract_number_format_id' => $spplId,
            'executor_id' => $executor->id,
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'execution_approval_state' => 'menunggu_persetujuan',
            'number' => 'KDD057.SPPL/612/UPKD/2026',
        ]);

        $migration = require base_path('database/migrations/2026_10_26_093245_move_sppl_contract_number_input_to_surat_pesanan.php');
        $migration->up();

        $suratPesananItem->refresh();
        $lampiranItem->refresh();

        $this->assertFalse($suratPesananItem->trashed());
        $this->assertSame(ChecklistInput::ContractNumber, $suratPesananItem->input_kind);
        $this->assertSame($spplId, $suratPesananItem->input_contract_number_format_id);
        $this->assertNull($lampiranItem->input_kind);

        // Restored for SPPL only: SPK is excluded from the restored Surat Pesanan step.
        $this->assertTrue(
            $suratPesananItem->excludedContractNumberFormats()->where('contract_number_formats.id', $spkId)->exists()
        );

        // Running SPPL jobs received the step: open for in-progress, completed for already handed on.
        $inProgressStep = $inProgressJob->checklists()->where('checklist_item_id', $suratPesananItem->id)->first();
        $submittedStep = $submittedJob->checklists()->where('checklist_item_id', $suratPesananItem->id)->first();
        $this->assertNotNull($inProgressStep);
        $this->assertFalse($inProgressStep->is_completed);
        $this->assertNotNull($submittedStep);
        $this->assertTrue($submittedStep->is_completed);

        // Existing contract number is immediately recognized as filled on Surat Pesanan.
        $this->assertTrue($suratPesananItem->inputFor($inProgressJob)?->isFilledOn($inProgressJob));
    }
}
