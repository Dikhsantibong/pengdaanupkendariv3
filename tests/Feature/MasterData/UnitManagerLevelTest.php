<?php

namespace Tests\Feature\MasterData;

use App\Enums\TeamLeaderField;
use App\Enums\UnitManagerLevel;
use App\Models\TargetUnit;
use App\Models\UnitManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The jabatan on Data Master → Manager Unit is picked from the ranks, highest
 * first: Manager, Manager UL holding several units, TL leading one bidang
 * across several units, and TL OPHAR holding one unit.
 */
class UnitManagerLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_jabatan_is_picked_from_the_ranks_highest_first(): void
    {
        $unit = TargetUnit::factory()->create(['name' => 'PLTD Langara']);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('master-data.unit-managers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/unit-managers')
                ->where('levelOptions.0.value', 'manager')
                ->where('levelOptions.0.label', 'MANAGER — jabatan tertinggi')
                ->where('levelOptions.1.value', 'manager_ul')
                ->where('levelOptions.1.label', 'MANAGER UL — memegang beberapa unit')
                ->where('levelOptions.2.value', 'tl')
                ->where('levelOptions.2.label', 'TL — satu bidang di beberapa unit')
                ->where('levelOptions.3.value', 'tl_ophar')
                ->where('levelOptions.3.label', 'TL OPHAR — memegang 1 unit')
                ->has('fieldOptions', 3)
                ->where('unitOptions.0.value', (string) $unit->id)
            );
    }

    public function test_a_tl_leads_one_bidang_across_several_units(): void
    {
        [$wuaWua, $langara] = $this->units('PLTD Wua Wua', 'PLTD Langara');

        $this->save([
            'name' => 'SURYADI PRATAMA',
            'level' => 'tl',
            'field' => 'pemeliharaan',
            'target_unit_ids' => [$langara->id, $wuaWua->id],
            // Left over from another rank; not kept.
            'target_unit_id' => (string) $wuaWua->id,
        ])->assertSessionHasNoErrors();

        $official = $this->official('SURYADI PRATAMA');
        $this->assertSame(UnitManagerLevel::Tl, $official->level);
        $this->assertSame(TeamLeaderField::Pemeliharaan, $official->field);
        $this->assertSame('TL PEMELIHARAAN', $official->position);
        $this->assertSame(['PLTD Langara', 'PLTD Wua Wua'], $official->targetUnits->pluck('name')->all());
    }

    public function test_a_tl_needs_a_bidang_and_units(): void
    {
        $this->save(['name' => 'SUHARTADI', 'level' => 'tl', 'field' => null, 'target_unit_ids' => []])
            ->assertSessionHasErrors(['field', 'target_unit_ids']);

        $this->save(['name' => 'ZULKIFLIN', 'level' => 'manager_ul', 'target_unit_ids' => []])
            ->assertSessionHasErrors('target_unit_ids');

        $this->assertDatabaseMissing('unit_managers', ['name' => 'SUHARTADI']);
        $this->assertDatabaseMissing('unit_managers', ['name' => 'ZULKIFLIN']);
    }

    public function test_a_tl_ophar_holds_one_unit_named_in_the_jabatan(): void
    {
        [$langara] = $this->units('PLTD Langara');

        $this->save(['name' => 'INDRA MULYADI', 'level' => 'tl_ophar', 'target_unit_id' => null])
            ->assertSessionHasErrors('target_unit_id');

        $this->save([
            'name' => 'INDRA MULYADI',
            'level' => 'tl_ophar',
            'field' => 'operasi',
            'target_unit_id' => (string) $langara->id,
        ])->assertSessionHasNoErrors();

        $official = $this->official('INDRA MULYADI');
        $this->assertSame('TL OPHAR PLTD LANGARA', $official->position);
        $this->assertNull($official->field);
        $this->assertSame(['PLTD Langara'], $official->targetUnits->pluck('name')->all());
    }

    public function test_the_manager_holds_no_unit(): void
    {
        [$langara] = $this->units('PLTD Langara');
        $official = UnitManager::factory()->create(['name' => 'ABDUL RAHMAN KADIR', 'level' => UnitManagerLevel::ManagerUl, 'position' => 'MANAGER UL']);
        $official->targetUnits()->attach($langara->id);

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('master-data.unit-managers.update', $official), [
                'name' => 'ABDUL RAHMAN KADIR',
                'level' => 'manager',
                'target_unit_ids' => [$langara->id],
                'description' => null,
                'sort_order' => 2,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $official = $this->official('ABDUL RAHMAN KADIR');
        $this->assertSame('MANAGER', $official->position);
        $this->assertCount(0, $official->targetUnits);
    }

    public function test_a_jabatan_written_before_is_read_as_its_rank(): void
    {
        $this->assertSame(UnitManagerLevel::Manager, UnitManagerLevel::fromPosition('MANAGER'));
        $this->assertSame(UnitManagerLevel::ManagerUl, UnitManagerLevel::fromPosition('MANAGER UL '));
        $this->assertSame(UnitManagerLevel::Tl, UnitManagerLevel::fromPosition('TL K3L'));
        $this->assertSame(UnitManagerLevel::TlOphar, UnitManagerLevel::fromPosition('tl ophar pltd raha'));

        $this->assertSame(TeamLeaderField::Operasi, TeamLeaderField::fromPosition('TL OPERASI'));
        $this->assertNull(TeamLeaderField::fromPosition('TL LINGKUNGAN'));
    }

    /**
     * Add an official through the form.
     *
     * @param  array<string, mixed>  $values
     * @return TestResponse<Response>
     */
    protected function save(array $values): TestResponse
    {
        return $this->actingAs(User::factory()->administrator()->create())
            ->from(route('master-data.unit-managers.index'))
            ->post(route('master-data.unit-managers.store'), [
                'description' => 'ULPLTD WUA WUA',
                'sort_order' => 2,
                'is_active' => true,
                ...$values,
            ]);
    }

    /**
     * Units on Unit Tujuan, by name.
     *
     * @return list<TargetUnit>
     */
    protected function units(string ...$names): array
    {
        return array_map(fn (string $name): TargetUnit => TargetUnit::factory()->create(['name' => $name]), array_values($names));
    }

    /**
     * An official with the units they hold.
     */
    protected function official(string $name): UnitManager
    {
        return UnitManager::query()->with('targetUnits')->where('name', $name)->firstOrFail();
    }
}
