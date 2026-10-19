<?php

namespace Tests\Feature\MasterData;

use App\Models\Procurement;
use App\Models\UnitManager;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\UnitLayananManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The managers and team leaders of the Unit Layanan are kept on Data Master →
 * Manager Unit, after the Manager UP Kendari who signs the documents.
 */
class UnitLayananManagerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_every_unit_layanan_official(): void
    {
        $this->seed(UnitLayananManagerSeeder::class);

        $this->assertSame(27, UnitManager::query()->where('description', 'like', 'ULPLTD%')->count());
        $this->assertSame(5, UnitManager::query()->where('description', 'ULPLTD WUA WUA')->count());
        $this->assertSame(4, UnitManager::query()->where('description', 'ULPLTD POASIA')->count());
        $this->assertSame(8, UnitManager::query()->where('description', 'ULPLTDG KOLAKA')->count());
        $this->assertSame(10, UnitManager::query()->where('description', 'ULPLTD BAU BAU')->count());

        $manager = UnitManager::query()->where('name', 'ABDUL RAHMAN KADIR')->firstOrFail();
        $this->assertSame('MANAGER UL', $manager->position);
        $this->assertTrue($manager->is_active);

        $this->assertSame('TL OPHAR PLTG KOLAKA', UnitManager::query()->where('name', 'RIZAL JHON')->value('position'));
        $this->assertSame('TL PEMELIHARAAN', UnitManager::query()->where('name', 'I PUTU AGUS SAPUTRA')->value('position'));
    }

    public function test_the_manager_up_kendari_still_signs_the_documents(): void
    {
        $this->seed(MasterDataSeeder::class);

        $procurement = Procurement::factory()->create();
        $values = app(DocumentGenerator::class)->placeholderValues($procurement->fresh());

        $this->assertSame('MUHAMMAD RUSLI', $values['nama_manager']);
        $this->assertSame(
            'MUHAMMAD RUSLI',
            UnitManager::query()->active()->ordered()->value('name'),
        );
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
        $this->seed(UnitLayananManagerSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $edited = UnitManager::query()->where('name', 'SUHARTADI')->firstOrFail();
        $deleted = UnitManager::query()->where('name', 'SAKIRI')->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.unit-managers.update', $edited), [
                'name' => 'SUHARTADI',
                'position' => 'TL OPERASI PLTD WUA WUA',
                'description' => 'ULPLTD WUA WUA',
                'sort_order' => 5,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->actingAs($administrator)
            ->delete(route('master-data.unit-managers.destroy', $deleted))
            ->assertRedirect();

        $this->seed(UnitLayananManagerSeeder::class);

        $this->assertSame(1, UnitManager::query()->where('name', 'SUHARTADI')->count());
        $this->assertSame('TL OPERASI PLTD WUA WUA', $edited->refresh()->position);
        $this->assertFalse($edited->is_active);
        $this->assertSame(0, UnitManager::query()->where('name', 'SAKIRI')->count());
        $this->assertSoftDeleted($deleted);
    }
}
