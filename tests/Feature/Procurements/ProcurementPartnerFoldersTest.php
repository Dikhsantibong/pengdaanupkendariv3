<?php

namespace Tests\Feature\Procurements;

use App\Http\Controllers\ProcurementController;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProcurementPartnerFoldersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_opens_on_one_folder_per_partner(): void
    {
        Procurement::factory()->count(2)->create(['partner_name' => 'PT. Sinar Teknik']);
        Procurement::factory()->create(['partner_name' => ' pt. sinar teknik ']);
        Procurement::factory()->create(['partner_name' => 'CV. Mudabbir']);
        Procurement::factory()->create(['partner_name' => null]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('procurements/index')
                ->where('partner', null)
                ->has('folders.data', 3)
                ->where('folders.data', fn (Collection $folders) => $folders->firstWhere('name', 'PT. Sinar Teknik')['total'] === 3
                    && $folders->firstWhere('name', 'CV. Mudabbir')['total'] === 1)
                ->where('folders.data.2.key', ProcurementController::NO_PARTNER));
    }

    public function test_a_folder_lists_only_that_partners_procurements(): void
    {
        $theirs = Procurement::factory()->create(['partner_name' => 'PT. Sinar Teknik']);
        Procurement::factory()->create(['partner_name' => 'CV. Mudabbir']);
        $unnamed = Procurement::factory()->create(['partner_name' => null]);

        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('procurements.index', ['mitra' => 'pt. sinar teknik']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('folders', null)
                ->where('partner.name', 'pt. sinar teknik')
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $theirs->id));

        $this->actingAs($administrator)
            ->get(route('procurements.index', ['mitra' => ProcurementController::NO_PARTNER]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('partner.name', 'Belum Ada Mitra')
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $unnamed->id));
    }

    public function test_a_planner_only_gets_folders_of_their_own_work(): void
    {
        $planner = User::factory()->planner()->create();

        Procurement::factory()->plannedBy($planner)->create(['partner_name' => 'PT. Sinar Teknik']);
        Procurement::factory()->create(['partner_name' => 'CV. Mudabbir']);

        $this->actingAs($planner)
            ->get(route('procurements.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('folders.data', 1)
                ->where('folders.data.0.name', 'PT. Sinar Teknik'));
    }
}
