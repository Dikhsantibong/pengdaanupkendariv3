<?php

namespace Tests\Feature\Procurements;

use App\Enums\ChecklistInput;
use App\Enums\PlanningApprovalState;
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
 * contract number on the Kontrak step (SPK/PJ) or the Lampiran Surat Pesanan
 * step (SPPL). The category is the planning PIC's, not the executor's.
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
        foreach (['SPK' => 'Kontrak', 'PJ' => 'Kontrak', 'SPPL' => 'Lampiran Surat Pesanan'] as $format => $name) {
            [$procurement] = $this->step($format, $name);

            $asking = $procurement->checklists()->with('checklistItem')->get()
                ->filter(fn (ProcurementChecklist $row): bool => $row->checklistItem->input_kind === ChecklistInput::ContractNumber)
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

    public function test_an_sppl_gives_its_number_on_the_lampiran(): void
    {
        [$procurement, $executor, $lampiran] = $this->step('SPPL', 'Lampiran Surat Pesanan');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $lampiran]), ['number' => 'KDD010.SPPL/612/UPKD/2026'])
            ->assertSessionHasNoErrors();

        $this->assertSame('KDD010.SPPL/612/UPKD/2026', $procurement->refresh()->number);
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
}
