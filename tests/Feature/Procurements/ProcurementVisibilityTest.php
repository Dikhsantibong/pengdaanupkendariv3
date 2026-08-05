<?php

namespace Tests\Feature\Procurements;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProcurementVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_only_sees_procurements_assigned_to_them(): void
    {
        $planner = User::factory()->planner()->create();
        $otherPlanner = User::factory()->planner()->create();

        $assigned = Procurement::factory()->plannedBy($planner)->create();
        Procurement::factory()->plannedBy($otherPlanner)->create();

        $this->actingAs($planner)
            ->get(route('procurements.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('procurements/index')
                ->has('procurements.data', 1)
                ->where('procurements.data.0.number', $assigned->number),
            );
    }

    public function test_executor_only_sees_procurements_assigned_to_them(): void
    {
        $executor = User::factory()->executor()->create();

        $assigned = Procurement::factory()->executedBy($executor)->create();
        Procurement::factory()->create();

        $this->actingAs($executor)
            ->get(route('procurements.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('procurements.data', 1)
                ->where('procurements.data.0.number', $assigned->number),
            );
    }

    public function test_pic_cannot_open_a_procurement_belonging_to_another_pic(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->create();

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertForbidden();
    }

    public function test_pic_can_open_their_own_procurement(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('procurements/show')
                ->where('procurement.number', $procurement->number),
            );
    }

    public function test_team_leader_sees_every_procurement(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        Procurement::factory()->count(3)->create();

        $this->actingAs($teamLeader)
            ->get(route('procurements.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('procurements.data', 3));
    }

    public function test_reassigning_a_pic_revokes_the_previous_pic_access(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $previousPlanner = User::factory()->planner()->create();
        $newPlanner = User::factory()->planner()->create();

        $procurement = Procurement::factory()->plannedBy($previousPlanner)->create();

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => $newPlanner->id,
                'executor_id' => null,
            ])
            ->assertRedirect();

        $this->actingAs($previousPlanner)
            ->get(route('procurements.show', $procurement))
            ->assertForbidden();

        $this->actingAs($newPlanner)
            ->get(route('procurements.show', $procurement))
            ->assertOk();
    }
}
