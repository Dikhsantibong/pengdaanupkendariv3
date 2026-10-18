<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The planning and execution lists narrow to one contract format: SPK, PJ
 * or SPPL.
 */
class ContractFormatFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_planning_list_is_filtered_by_contract_format(): void
    {
        $planner = User::factory()->create();
        [$spk, $sppl] = $this->formats();

        $match = Procurement::factory()->plannedBy($planner)->create(['contract_number_format_id' => $sppl->id]);
        Procurement::factory()->plannedBy($planner)->create(['contract_number_format_id' => $spk->id]);

        $this->actingAs($planner)
            ->get(route('planning.index', ['contract_number_format_id' => $sppl->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.contract_number_format_id', $sppl->id)
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $match->id)
            );
    }

    public function test_the_execution_list_is_filtered_by_contract_format(): void
    {
        $executor = User::factory()->create();
        [$spk, $sppl] = $this->formats();

        $match = Procurement::factory()->planningApproved()->executedBy($executor)->create(['contract_number_format_id' => $spk->id]);
        Procurement::factory()->planningApproved()->executedBy($executor)->create(['contract_number_format_id' => $sppl->id]);

        $this->actingAs($executor)
            ->get(route('execution.index', ['contract_number_format_id' => $spk->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.contract_number_format_id', $spk->id)
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $match->id)
            );
    }

    public function test_without_the_filter_every_format_is_listed(): void
    {
        $executor = User::factory()->create();
        [$spk, $sppl] = $this->formats();

        Procurement::factory()->planningApproved()->executedBy($executor)->create(['contract_number_format_id' => $spk->id]);
        Procurement::factory()->planningApproved()->executedBy($executor)->create(['contract_number_format_id' => $sppl->id]);

        $this->actingAs($executor)
            ->get(route('execution.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.contract_number_format_id', null)
                ->has('procurements.data', 2)
            );
    }

    public function test_only_contract_formats_are_offered(): void
    {
        $planner = User::factory()->create();
        $this->formats();
        ContractNumberFormat::factory()->create(['code' => 'Amd', 'for_amendment' => true]);
        ContractNumberFormat::factory()->create(['code' => 'DO', 'for_delivery_order' => true]);

        $this->actingAs($planner)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contractFormats', fn ($formats) => collect($formats)->pluck('label')->sort()->values()->all() === ['SPK', 'SPPL'])
            );
    }

    public function test_both_lists_are_filtered_by_contract_type(): void
    {
        $user = User::factory()->create();
        $khs = ContractType::query()->firstWhere('name', 'KHS') ?? ContractType::factory()->create(['name' => 'KHS']);
        $jasa = ContractType::query()->firstWhere('name', 'Jasa') ?? ContractType::factory()->create(['name' => 'Jasa']);

        $planned = Procurement::factory()->plannedBy($user)->create(['contract_type_id' => $khs->id]);
        Procurement::factory()->plannedBy($user)->create(['contract_type_id' => $jasa->id]);
        $executed = Procurement::factory()->planningApproved()->executedBy($user)->create(['contract_type_id' => $khs->id]);
        Procurement::factory()->planningApproved()->executedBy($user)->create(['contract_type_id' => null]);

        foreach (['planning.index' => $planned, 'execution.index' => $executed] as $route => $match) {
            $this->actingAs($user)
                ->get(route($route, ['contract_type_id' => $khs->id]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('filters.contract_type_id', $khs->id)
                    ->where('contractTypes', fn ($types) => collect($types)->pluck('label')->intersect(['Jasa', 'KHS'])->count() === 2)
                    ->has('procurements.data', 1)
                    ->where('procurements.data.0.id', $match->id)
                );
        }
    }

    /**
     * @return array{0: ContractNumberFormat, 1: ContractNumberFormat}
     */
    protected function formats(): array
    {
        return [
            ContractNumberFormat::factory()->create(['code' => 'SPK']),
            ContractNumberFormat::factory()->create(['code' => 'SPPL']),
        ];
    }
}
