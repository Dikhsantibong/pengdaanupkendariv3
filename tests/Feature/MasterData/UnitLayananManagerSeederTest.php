<?php

namespace Tests\Feature\MasterData;

use App\Enums\TeamLeaderField;
use App\Enums\UnitManagerLevel;
use App\Models\Procurement;
use App\Models\TargetUnit;
use App\Models\UnitManager;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\UnitLayananManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The managers and team leaders of the Unit Layanan are kept on Data Master →
 * Manager Unit with their rank and the units they hold, after the Manager UP
 * Kendari who signs the documents.
 */
class UnitLayananManagerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_every_unit_layanan_official(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(27, UnitManager::query()->where('description', 'like', 'ULPLTD%')->count());
        $this->assertSame(5, UnitManager::query()->where('description', 'ULPLTD WUA WUA')->count());
        $this->assertSame(4, UnitManager::query()->where('description', 'ULPLTD POASIA')->count());
        $this->assertSame(8, UnitManager::query()->where('description', 'ULPLTDG KOLAKA')->count());
        $this->assertSame(10, UnitManager::query()->where('description', 'ULPLTD BAU BAU')->count());

        $manager = $this->official('ABDUL RAHMAN KADIR');
        $this->assertSame(UnitManagerLevel::ManagerUl, $manager->level);
        $this->assertSame('MANAGER UL', $manager->position);
        $this->assertTrue($manager->is_active);
        $this->assertSame(['PLTD Wua Wua', 'PLTD Langara'], $manager->targetUnits->pluck('name')->all());

        $teamLeader = $this->official('I PUTU AGUS SAPUTRA');
        $this->assertSame(UnitManagerLevel::Tl, $teamLeader->level);
        $this->assertSame(TeamLeaderField::Pemeliharaan, $teamLeader->field);
        $this->assertSame('TL PEMELIHARAAN', $teamLeader->position);
        $this->assertSame(['PLTD Kolaka', 'PLTD Lanipa', 'PLTM Sabilambo', 'PLTM Mikuasi'], $teamLeader->targetUnits->pluck('name')->all());

        $ophar = $this->official('INDRA MULYADI');
        $this->assertSame(UnitManagerLevel::TlOphar, $ophar->level);
        $this->assertSame('TL OPHAR PLTD LANGARA', $ophar->position);
        $this->assertSame(['PLTD Langara'], $ophar->targetUnits->pluck('name')->all());

        // The jabatan follows the unit's name on Unit Tujuan.
        $this->assertSame('TL OPHAR PLTD WINNING', $this->official('ASFAR ADRIN ASLI')->position);
        $this->assertSame('TL OPHAR PLTD PASAR WAJO', $this->official('SAKIRI')->position);

        // A unit not listed on Unit Tujuan is left out.
        $notListed = $this->official('RIZAL JHON');
        $this->assertSame('TL OPHAR PLTG KOLAKA', $notListed->position);
        $this->assertCount(0, $notListed->targetUnits);
    }

    public function test_the_manager_up_kendari_still_signs_the_documents(): void
    {
        $this->seed(MasterDataSeeder::class);

        // Even listed first, a Manager UL does not sign for UP Kendari.
        $this->official('ABDUL RAHMAN KADIR')->update(['sort_order' => 0]);

        $procurement = Procurement::factory()->create();
        $values = app(DocumentGenerator::class)->placeholderValues($procurement->fresh());

        $this->assertSame('MUHAMMAD RUSLI', $values['nama_manager']);
    }

    public function test_the_officials_are_listed_after_the_rows_already_there(): void
    {
        UnitManager::query()->update(['sort_order' => 40]);

        $this->seed(UnitLayananManagerSeeder::class);

        $this->assertSame(41, UnitManager::query()->where('name', 'ABDUL RAHMAN KADIR')->value('sort_order'));
        $this->assertSame('MUHAMMAD RUSLI', UnitManager::query()->active()->ordered()->value('name'));
    }

    public function test_running_it_again_keeps_what_an_administrator_changed(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $edited = $this->official('SUHARTADI');
        $deleted = $this->official('SAKIRI');
        $langara = TargetUnit::query()->where('name', 'PLTD Langara')->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.unit-managers.update', $edited), [
                'name' => 'SUHARTADI',
                'level' => 'tl',
                'field' => 'k3l',
                'target_unit_ids' => [$langara->id],
                'description' => 'ULPLTD WUA WUA',
                'sort_order' => 5,
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($administrator)
            ->delete(route('master-data.unit-managers.destroy', $deleted))
            ->assertRedirect();

        $this->seed(UnitLayananManagerSeeder::class);

        $edited->refresh();
        $this->assertSame(1, UnitManager::query()->where('name', 'SUHARTADI')->count());
        $this->assertSame('TL K3L', $edited->position);
        $this->assertSame(['PLTD Langara'], $edited->targetUnits->pluck('name')->all());
        $this->assertFalse($edited->is_active);
        $this->assertSame(0, UnitManager::query()->where('name', 'SAKIRI')->count());
        $this->assertSoftDeleted($deleted);
    }

    /**
     * A seeded official, with the units they hold.
     */
    protected function official(string $name): UnitManager
    {
        return UnitManager::query()->with('targetUnits')->where('name', $name)->firstOrFail();
    }
}
