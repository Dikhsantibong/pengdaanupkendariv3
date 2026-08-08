<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\PlanningApprovalState;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The identity details the appointed planning PIC supplies: the kind of
 * contract, which is master data an administrator maintains, and the manager's
 * memo number, which is free text issued outside this system.
 */
class PlanningIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_khs_and_lumsum_are_seeded(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(
            ['KHS', 'Lumsum'],
            ContractType::query()->active()->ordered()->pluck('name')->all(),
        );
    }

    public function test_the_assigned_planner_sets_the_contract_type(): void
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $khs = ContractType::query()->where('code', 'khs')->firstOrFail();

        $this->assertNull($procurement->contract_type_id);

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'contract_type_id' => $khs->id,
            ])
            ->assertRedirect();

        $this->assertSame($khs->id, $procurement->refresh()->contract_type_id);

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::Diperbarui->value,
            'description' => 'Jenis kontrak ditetapkan: KHS.',
        ]);
    }

    public function test_the_picker_appears_on_the_identity_panel_for_the_planner(): void
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.updatePlanningIdentity', true)
                ->has('options.contractTypes', 2)
                ->where('procurement.contract_type', null)
                ->where('procurement.contract_type_id', null)
            );
    }

    public function test_a_pic_of_another_procurement_cannot_set_it(): void
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $outsider = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $khs = ContractType::query()->where('code', 'khs')->firstOrFail();

        $this->actingAs($outsider)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'contract_type_id' => $khs->id,
            ])
            ->assertForbidden();

        $this->assertNull($procurement->refresh()->contract_type_id);
    }

    public function test_a_supervisor_can_correct_it_and_the_change_is_logged(): void
    {
        $this->seed(MasterDataSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = Procurement::factory()->create();
        $khs = ContractType::query()->where('code', 'khs')->firstOrFail();
        $lumsum = ContractType::query()->where('code', 'lumsum')->firstOrFail();

        $this->actingAs($teamLeader)->put(
            route('procurements.planning-identity.update', $procurement),
            ['contract_type_id' => $khs->id],
        );

        $this->actingAs($teamLeader)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'contract_type_id' => $lumsum->id,
            ])
            ->assertRedirect();

        $this->assertSame($lumsum->id, $procurement->refresh()->contract_type_id);
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'description' => 'Jenis kontrak diubah dari KHS menjadi Lumsum.',
        ]);
    }

    public function test_it_can_be_cleared_again(): void
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'contract_type_id' => ContractType::query()->where('code', 'khs')->firstOrFail()->id,
        ]);

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'contract_type_id' => null,
            ])
            ->assertRedirect();

        $this->assertNull($procurement->refresh()->contract_type_id);
    }

    public function test_a_deactivated_contract_type_cannot_be_chosen(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $retired = ContractType::factory()->inactive()->create();

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'contract_type_id' => $retired->id,
            ])
            ->assertSessionHasErrors('contract_type_id');

        $this->assertNull($procurement->refresh()->contract_type_id);
    }

    public function test_the_planner_cannot_change_it_once_planning_is_approved(): void
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $administrator = User::factory()->administrator()->create();
        $lumsum = ContractType::query()->where('code', 'lumsum')->firstOrFail();

        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'planning_approval_state' => PlanningApprovalState::Disetujui,
        ]);

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'contract_type_id' => $lumsum->id,
            ])
            ->assertForbidden();

        // A supervisor keeps the ability to correct a mistake afterwards.
        $this->actingAs($administrator)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'contract_type_id' => $lumsum->id,
            ])
            ->assertRedirect();

        $this->assertSame($lumsum->id, $procurement->refresh()->contract_type_id);
    }

    public function test_a_soft_deleted_contract_type_keeps_showing_on_its_procurements(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $khs = ContractType::query()->where('code', 'khs')->firstOrFail();
        $procurement = Procurement::factory()->create(['contract_type_id' => $khs->id]);

        $this->actingAs($administrator)
            ->delete(route('master-data.contract-types.destroy', $khs))
            ->assertRedirect();

        $this->assertSoftDeleted($khs);

        $this->actingAs($administrator)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('procurement.contract_type', 'KHS'));
    }

    public function test_an_administrator_maintains_the_list_like_other_master_data(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('master-data.contract-types.store'), [
                'name' => 'Gabungan Lumsum dan Harga Satuan',
                'code' => 'Gabungan Lumsum',
                'description' => 'Kombinasi lumsum dan harga satuan.',
                'sort_order' => 3,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $created = ContractType::query()->where('name', 'Gabungan Lumsum dan Harga Satuan')->firstOrFail();

        // The code is slugged so it stays usable as a lookup key.
        $this->assertSame('gabungan-lumsum', $created->code);

        $this->actingAs($administrator)
            ->get(route('master-data.contract-types.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('master-data/contract-types'));
    }

    public function test_a_pic_pelaksana_cannot_reach_the_master_data_screen(): void
    {
        $this->actingAs(User::factory()->executor()->create())
            ->get(route('master-data.contract-types.index'))
            ->assertForbidden();
    }

    public function test_the_planner_sets_the_manager_memo_number(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $this->assertNull($procurement->manager_memo_number);

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'manager_memo_number' => '  0123.ND/612/UPKD/2026  ',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Stored trimmed, so stray spaces never reach a printed document.
        $this->assertSame('0123.ND/612/UPKD/2026', $procurement->refresh()->manager_memo_number);

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'description' => 'Nomor nota dinas manager ditetapkan: 0123.ND/612/UPKD/2026.',
        ]);
    }

    public function test_the_memo_number_can_be_corrected_and_cleared(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'manager_memo_number' => 'ND-LAMA',
        ]);

        $this->actingAs($planner)->put(
            route('procurements.planning-identity.update', $procurement),
            ['manager_memo_number' => 'ND-BARU'],
        )->assertRedirect();

        $this->assertSame('ND-BARU', $procurement->refresh()->manager_memo_number);
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'description' => 'Nomor nota dinas manager diubah dari ND-LAMA menjadi ND-BARU.',
        ]);

        $this->actingAs($planner)->put(
            route('procurements.planning-identity.update', $procurement),
            ['manager_memo_number' => ''],
        )->assertRedirect();

        $this->assertNull($procurement->refresh()->manager_memo_number);
    }

    public function test_each_field_is_saved_without_disturbing_the_other(): void
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $khs = ContractType::query()->where('code', 'khs')->firstOrFail();

        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'contract_type_id' => $khs->id,
            'manager_memo_number' => 'ND-001',
        ]);

        // A control posts only its own key; the other value must survive.
        $this->actingAs($planner)->put(
            route('procurements.planning-identity.update', $procurement),
            ['manager_memo_number' => 'ND-002'],
        )->assertRedirect();

        $procurement->refresh();

        $this->assertSame('ND-002', $procurement->manager_memo_number);
        $this->assertSame($khs->id, $procurement->contract_type_id);

        $lumsum = ContractType::query()->where('code', 'lumsum')->firstOrFail();

        $this->actingAs($planner)->put(
            route('procurements.planning-identity.update', $procurement),
            ['contract_type_id' => $lumsum->id],
        )->assertRedirect();

        $procurement->refresh();

        $this->assertSame($lumsum->id, $procurement->contract_type_id);
        $this->assertSame('ND-002', $procurement->manager_memo_number);
    }

    public function test_an_unassigned_pic_cannot_set_the_memo_number(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs(User::factory()->planner()->create())
            ->put(route('procurements.planning-identity.update', $procurement), [
                'manager_memo_number' => 'ND-DIBAJAK',
            ])
            ->assertForbidden();

        $this->assertNull($procurement->refresh()->manager_memo_number);
    }

    public function test_the_memo_number_shows_on_the_identity_panel(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'manager_memo_number' => 'ND-777',
        ]);

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('procurement.manager_memo_number', 'ND-777')
                ->where('can.updatePlanningIdentity', true)
            );
    }

    public function test_an_overlong_memo_number_is_rejected(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs($planner)
            ->put(route('procurements.planning-identity.update', $procurement), [
                'manager_memo_number' => str_repeat('A', 256),
            ])
            ->assertSessionHasErrors('manager_memo_number');

        $this->assertNull($procurement->refresh()->manager_memo_number);
    }

    public function test_the_memo_number_is_available_to_document_templates(): void
    {
        $procurement = Procurement::factory()->create([
            'manager_memo_number' => 'ND-555/612/UPKD/2026',
        ]);

        $values = app(DocumentGenerator::class)->placeholderValues($procurement);

        $this->assertSame('ND-555/612/UPKD/2026', $values['nomor_nota_dinas_manager']);
        $this->assertArrayHasKey(
            'nomor_nota_dinas_manager',
            DocumentGenerator::placeholderCatalog(),
        );
    }

    public function test_the_contract_type_is_available_to_document_templates(): void
    {
        $this->seed(MasterDataSeeder::class);

        $khs = ContractType::query()->where('code', 'khs')->firstOrFail();
        $procurement = Procurement::factory()->create(['contract_type_id' => $khs->id]);

        $values = app(DocumentGenerator::class)->placeholderValues($procurement);

        $this->assertSame('KHS', $values['jenis_kontrak']);
        $this->assertArrayHasKey(
            'jenis_kontrak',
            DocumentGenerator::placeholderCatalog(),
        );
    }
}
