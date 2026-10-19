<?php

namespace Tests\Feature\Procurements;

use App\Enums\ChecklistInput;
use App\Enums\PlanningApprovalState;
use App\Enums\WorkCategory;
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
 * contract number, and the barang/jasa category of an SPK or PJ, on the
 * Kontrak step (SPK/PJ) or the Lampiran Surat Pesanan step (SPPL).
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

    public function test_the_executor_gives_the_number_and_category_before_ticking_the_step(): void
    {
        [$procurement, $executor, $kontrak] = $this->step('SPK', 'Kontrak');
        $jasa = ContractType::query()->where('code', 'jasa')->firstOrFail();

        // The internal number is not a contract number.
        $this->assertFalse($procurement->hasContractNumber());
        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $kontrak]), ['is_completed' => true])
            ->assertSessionHasErrors('is_completed');

        // An SPK needs its category too.
        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $kontrak]), ['number' => 'KDD075.SPK/612/UPKD/2026'])
            ->assertSessionHasErrors('contract_type_id');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $kontrak]), [
                'number' => ' KDD075.SPK/612/UPKD/2026 ',
                'contract_type_id' => $jasa->id,
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame('KDD075.SPK/612/UPKD/2026', $procurement->number);
        $this->assertSame($jasa->id, $procurement->contract_type_id);
        $this->assertSame(WorkCategory::Jasa, $procurement->work_category);
        $this->assertTrue(ChecklistInput::ContractNumber->isFilledOn($procurement));
    }

    public function test_an_sppl_gives_only_the_number(): void
    {
        [$procurement, $executor, $lampiran] = $this->step('SPPL', 'Lampiran Surat Pesanan');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $lampiran]), [
                'number' => 'KDD010.SPPL/612/UPKD/2026',
                'contract_type_id' => ContractType::query()->value('id'),
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame('KDD010.SPPL/612/UPKD/2026', $procurement->number);
        // SPPL has no barang/jasa category.
        $this->assertNull($procurement->contract_type_id);
        $this->assertNull($procurement->work_category);
    }

    public function test_the_number_must_be_a_free_contract_number(): void
    {
        [$procurement, $executor, $kontrak] = $this->step('PJ', 'Kontrak');
        $type = ContractType::query()->value('id');
        Procurement::factory()->create(['number' => 'KDD020.PJ/612/UPKD/2026']);

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $kontrak]), ['number' => 'PGD/2026/08/0009', 'contract_type_id' => $type])
            ->assertSessionHasErrors('number');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $kontrak]), ['number' => 'KDD020.PJ/612/UPKD/2026', 'contract_type_id' => $type])
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
                    && collect($rows)->firstWhere('name', 'Kontrak')['input']['values']['suggested_number'] === 'KDD075.SPK/612/UPKD/2026'
                    && collect($rows)->firstWhere('name', 'Kontrak')['input']['values']['uses_work_category'] === true));
    }

    public function test_the_create_form_registers_the_job_without_a_contract_number_or_category(): void
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
        $this->assertSame([], $created->missingPlanningData());
    }
}
