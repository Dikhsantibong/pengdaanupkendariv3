<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BudgetPlan;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Perencanaan Anggaran: the Anggaran role keeps the PRK of each bidang, the
 * team leaders read them, and the proposers of that bidang pick them.
 */
class BudgetPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $budgeter;

    protected WorkDirector $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budgeter = User::factory()->role(UserRole::Anggaran)->create();
        $this->division = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
    }

    public function test_haskar_udin_has_an_anggaran_account(): void
    {
        $haskar = User::query()->where('email', 'haskar.udin@upkendari.go.id')->firstOrFail();

        $this->assertSame('Haskar Udin', $haskar->name);
        $this->assertSame(UserRole::Anggaran, $haskar->role);
        $this->assertTrue($haskar->is_active);
        $this->assertTrue(Hash::check('password', $haskar->password));
    }

    public function test_the_anggaran_role_adds_changes_and_removes_a_prk(): void
    {
        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('budget-plans/index')
                ->where('canManage', true)
                ->has('plans', 0));

        $this->actingAs($this->budgeter)
            ->post(route('budget-plans.store'), [
                'prk_induk' => '2026.AO.01.001',
                'work_director_id' => $this->division->id,
                'prk_induk_description' => 'Pemeliharaan Mesin Pembangkit',
                'sub_program_description' => 'Overhaul Mesin PLTD',
                'ai_plan_value' => 1500000000,
                'aki_plan_value' => 1200000000,
                'wo_number' => 'WO-2026-0042',
                'pr_number' => '1000012345',
            ])
            ->assertSessionHasNoErrors();

        $plan = BudgetPlan::query()->sole();
        $this->assertSame($this->division->id, $plan->work_director_id);
        $this->assertSame('1500000000.00', $plan->ai_plan_value);
        $this->assertSame($this->budgeter->id, $plan->created_by);

        $this->actingAs($this->budgeter)
            ->put(route('budget-plans.update', $plan), [
                'prk_induk' => '2026.AO.01.001',
                'work_director_id' => $this->division->id,
                'aki_plan_value' => 1100000000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('1100000000.00', $plan->refresh()->aki_plan_value);

        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 1)
                ->where('plans.0.work_director', 'Asman Operasi')
                ->where('totals.aki', 1100000000));

        $this->actingAs($this->budgeter)
            ->delete(route('budget-plans.destroy', $plan))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('budget_plans', 0);
    }

    public function test_a_prk_needs_its_number_and_bidang(): void
    {
        $this->actingAs($this->budgeter)
            ->post(route('budget-plans.store'), ['ai_plan_value' => -5])
            ->assertSessionHasErrors(['prk_induk', 'work_director_id', 'ai_plan_value']);

        $this->assertDatabaseCount('budget_plans', 0);
    }

    public function test_the_list_filters_by_bidang_and_search(): void
    {
        $other = WorkDirector::factory()->create();
        BudgetPlan::factory()->create(['prk_induk' => '2026.AO.01.001', 'work_director_id' => $this->division->id]);
        BudgetPlan::factory()->create(['prk_induk' => '2026.HAR.02.002', 'work_director_id' => $other->id]);

        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index', ['bidang' => $this->division->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 1)
                ->where('plans.0.prk_induk', '2026.AO.01.001'));

        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index', ['cari' => '2026.HAR']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 1)
                ->where('plans.0.prk_induk', '2026.HAR.02.002'));
    }

    public function test_team_leaders_read_the_plan_but_do_not_change_it(): void
    {
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $plan = BudgetPlan::factory()->create();

        $this->actingAs($teamLeader)
            ->get(route('budget-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($teamLeader)
            ->post(route('budget-plans.store'), ['prk_induk' => 'X', 'work_director_id' => $this->division->id])
            ->assertForbidden();
        $this->actingAs($teamLeader)->delete(route('budget-plans.destroy', $plan))->assertForbidden();

        $this->assertDatabaseCount('budget_plans', 1);
    }

    public function test_other_roles_do_not_see_the_plan(): void
    {
        $this->actingAs(User::factory()->planner()->create())
            ->get(route('budget-plans.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->proposer($this->division)->create())
            ->get(route('budget-plans.index'))
            ->assertForbidden();
    }

    public function test_the_menu_is_shown_to_the_anggaran_role(): void
    {
        $this->actingAs($this->budgeter)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions.menus.budget-plans', true));
    }

    public function test_the_proposal_form_offers_the_prk_of_each_bidang(): void
    {
        $plan = BudgetPlan::factory()->create([
            'prk_induk' => '2026.AO.01.001',
            'work_director_id' => $this->division->id,
            'sub_program_description' => 'Overhaul Mesin PLTD',
            'wo_number' => 'WO-2026-0042',
            'pr_number' => '1000012345',
        ]);

        $this->actingAs(User::factory()->proposer($this->division)->create())
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.budgetPlans', 1)
                ->where('options.budgetPlans.0.value', '2026.AO.01.001')
                ->where('options.budgetPlans.0.workDirectorId', $this->division->id)
                ->where('options.budgetPlans.0.description', 'Overhaul Mesin PLTD')
                ->where('options.budgetPlans.0.woNumber', 'WO-2026-0042')
                ->where('options.budgetPlans.0.prNumber', '1000012345'));

        $this->assertNotNull($plan->id);
    }
}
