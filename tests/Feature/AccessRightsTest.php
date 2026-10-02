<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\UserRole;
use App\Models\BudgetSource;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Services\AccessRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Hak Akses" screen: which role may use which feature. The defaults
 * reproduce the fixed behaviour, and the administrator can never be locked out.
 */
class AccessRightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_defaults_keep_the_access_everyone_already_had(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();

        $this->assertTrue($teamLeader->hasPermission(Permission::CreateProcurement));
        $this->assertTrue($teamLeader->hasPermission(Permission::ViewAllProcurements));
        $this->assertFalse($teamLeader->hasPermission(Permission::ManageMasterData));

        $this->assertFalse($planner->hasPermission(Permission::CreateProcurement));
        $this->assertFalse($planner->hasPermission(Permission::ViewAllProcurements));
    }

    public function test_the_administrator_always_holds_every_right(): void
    {
        $administrator = User::factory()->administrator()->create();

        AccessRights::replace([]);

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($administrator->hasPermission($permission));
        }
    }

    public function test_only_the_administrator_reaches_the_screen(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('access-rights.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('access-rights/index')
                ->has('permissions', count(Permission::cases()))
                ->where('grants.team_leader', fn ($grants) => in_array('procurement.create', $grants->all(), true)));

        $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('access-rights.index'))
            ->assertForbidden();
    }

    public function test_a_planning_pic_given_the_right_can_create_a_procurement(): void
    {
        $planner = User::factory()->planner()->create();

        $this->actingAs($planner)->get(route('procurements.create'))->assertForbidden();

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('access-rights.update'), [
                'grants' => [
                    'team_leader' => ['procurement.create', 'procurement.view-all'],
                    'pic_perencana' => ['procurement.create'],
                    'pic_pelaksana' => [],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($planner)->get(route('procurements.create'))->assertOk();

        $this->actingAs($planner)
            ->post(route('procurements.store'), [
                'name' => 'Usulan PIC',
                'work_director_id' => WorkDirector::factory()->create()->id,
                'target_unit_ids' => [TargetUnit::factory()->create()->id],
                'procurement_method_id' => ProcurementMethod::factory()->create()->id,
                'budget_source_id' => BudgetSource::factory()->create()->id,
                'hpe_value' => 1_000_000,
                'progress_status_id' => ProgressStatus::factory()->asDefault()->create()->id,
            ])
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->firstOrFail();

        // Whoever created it keeps sight of it, even without seeing all.
        $this->actingAs($planner)->get(route('procurements.show', $procurement))->assertOk();
        $this->actingAs($planner)
            ->get(route('procurements.index'))
            ->assertInertia(fn ($page) => $page->has('procurements.data', 1));

        // A second PIC without the right still cannot see it.
        $this->actingAs(User::factory()->planner()->create())
            ->get(route('procurements.show', $procurement))
            ->assertForbidden();
    }

    public function test_revoking_a_right_takes_it_away(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        AccessRights::replace(['team_leader' => ['procurement.view-all']]);

        $this->actingAs($teamLeader)->get(route('procurements.create'))->assertForbidden();
    }

    public function test_nobody_approves_their_own_planning(): void
    {
        $planner = User::factory()->planner()->create();

        AccessRights::replace([
            'team_leader' => ['procurement.view-all', 'procurement.review-planning'],
            'pic_perencana' => ['procurement.review-planning'],
        ]);

        $own = Procurement::factory()->plannedBy($planner)->create([
            'planning_approval_state' => PlanningApprovalState::MenungguPersetujuan,
        ]);

        $this->assertFalse($planner->can('reviewPlanning', $own));
        $this->assertTrue(User::factory()->teamLeader()->create()->can('reviewPlanning', $own));
    }

    public function test_the_administrator_rights_cannot_be_submitted(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('access-rights.update'), [
                'grants' => ['administrator' => []],
            ])
            ->assertSessionHasErrors('grants');
    }

    public function test_an_unknown_right_is_rejected(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('access-rights.update'), [
                'grants' => ['pic_perencana' => ['users.manage']],
            ])
            ->assertSessionHasErrors('grants.pic_perencana.0');

        $this->assertFalse(User::factory()->planner()->create()->hasPermission(Permission::CreateProcurement));
        $this->assertSame(UserRole::PicPerencana, User::factory()->planner()->create()->role);
    }

    public function test_the_master_data_right_can_be_shared(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)->get(route('master-data.target-units.index'))->assertForbidden();

        AccessRights::replace([
            'team_leader' => [...array_map(
                fn (Permission $permission): string => $permission->value,
                array_filter(Permission::cases(), fn (Permission $p): bool => in_array(UserRole::TeamLeader, $p->defaultRoles(), true)),
            ), 'master-data.manage'],
        ]);

        $this->actingAs($teamLeader)->get(route('master-data.target-units.index'))->assertOk();

        // Users stay with the administrator whatever is ticked.
        $this->actingAs($teamLeader)->get(route('users.index'))->assertForbidden();
    }
}
