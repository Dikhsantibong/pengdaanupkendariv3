<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\PlanningApprovalState;
use App\Models\Procurement;
use App\Models\SelectionMethod;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The metode pengadaan is master data the planning PIC (Rendan) picks on the
 * identity panel, and the documents print it.
 */
class SelectionMethodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
    }

    public function test_the_four_methods_are_seeded_in_order(): void
    {
        $this->assertSame([
            'Penunjukan Langsung',
            'Pengadaan Langsung',
            'Lelang Terbuka 1 Tahap 1 Sampul',
            'Lelang Terbuka 1 Tahap 2 Sampul',
        ], SelectionMethod::query()->active()->ordered()->pluck('name')->all());
    }

    public function test_the_planner_picks_the_method_and_it_is_recorded(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $method = SelectionMethod::query()->where('code', 'lelang-terbuka-1-tahap-2-sampul')->firstOrFail();

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.selectionMethods', 4)
                ->where('procurement.selection_method_id', null));

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), ['selection_method_id' => $method->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($method->id, $procurement->refresh()->selection_method_id);
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::Diperbarui->value,
            'description' => 'Metode pengadaan ditetapkan: Lelang Terbuka 1 Tahap 2 Sampul.',
        ]);

        // The documents print it.
        $this->assertSame(
            'Lelang Terbuka 1 Tahap 2 Sampul',
            app(DocumentGenerator::class)->placeholderValues($procurement->refresh())['metode_pengadaan'],
        );
    }

    public function test_only_the_assigned_planner_picks_it_and_only_an_active_method(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $method = SelectionMethod::query()->firstOrFail();
        $retired = SelectionMethod::factory()->inactive()->create();

        $this->actingAs(User::factory()->planner()->create())
            ->put(route('procurements.planning-identity.update', $procurement), ['selection_method_id' => $method->id])
            ->assertForbidden();

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), ['selection_method_id' => $retired->id])
            ->assertSessionHasErrors('selection_method_id');

        $this->assertNull($procurement->refresh()->selection_method_id);
    }

    public function test_it_cannot_be_changed_once_planning_is_approved(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'planning_approval_state' => PlanningApprovalState::Disetujui,
        ]);

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), ['selection_method_id' => SelectionMethod::query()->value('id')])
            ->assertForbidden();
    }

    public function test_an_administrator_manages_the_methods(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('master-data.selection-methods.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('master-data/selection-methods')->has('records', 4));

        $this->actingAs($administrator)
            ->post(route('master-data.selection-methods.store'), [
                'name' => 'Seleksi Terbatas',
                'code' => 'Seleksi Terbatas',
                'description' => null,
                'sort_order' => 5,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('selection_methods', ['code' => 'seleksi-terbatas', 'name' => 'Seleksi Terbatas']);

        $this->actingAs(User::factory()->planner()->create())
            ->get(route('master-data.selection-methods.index'))
            ->assertForbidden();
    }
}
