<?php

namespace Tests\Feature;

use App\Enums\PlanningApprovalState;
use App\Enums\ProposalState;
use App\Enums\UserRole;
use App\Models\Procurement;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Each role gets a dashboard of its own, showing only the figures and jobs
 * it works with.
 */
class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_keeps_the_full_overview(): void
    {
        Procurement::factory()->count(2)->create();

        foreach ([User::factory()->administrator()->create(), User::factory()->teamLeaderPengadaan()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('profile', 'manajemen')
                    ->where('summary.total', 2)
                    ->has('byWorkDirector')
                    ->has('byPlanner')
                    ->has('stages', 5)
                    ->missing('role'));
        }
    }

    public function test_a_proposer_sees_only_their_own_proposals(): void
    {
        $division = WorkDirector::factory()->create();
        $proposer = User::factory()->proposer($division)->create();

        $returned = $this->proposal($proposer, ProposalState::Dikembalikan);
        $this->proposal($proposer, ProposalState::MenungguPersetujuanTl);
        $this->proposal($proposer, ProposalState::Disetujui);
        $this->proposal($proposer, ProposalState::Disetujui, ['completed_at' => now()]);

        // Someone else's proposal stays out of every figure.
        $this->proposal(User::factory()->proposer($division)->create(), ProposalState::MenungguPersetujuan);

        $this->actingAs($proposer)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', 'pengusul')
                ->missing('byWorkDirector')
                ->missing('byPlanner')
                ->missing('recent')
                ->where('role.cards', fn ($cards) => collect($cards)->pluck('value', 'key')->all() === [
                    'total' => 4,
                    'waiting' => 1,
                    'returned' => 1,
                    'running' => 1,
                    'done' => 1,
                ])
                ->where('role.stages', fn ($stages) => collect($stages)->pluck('total', 'key')->all() === [
                    'usulan' => 2,
                    'perencanaan' => 1,
                    'pelaksanaan' => 0,
                    'keuangan' => 0,
                    'selesai' => 1,
                ])
                ->has('role.jobs', 4)
                ->where('role.jobs', fn ($jobs) => collect($jobs)->firstWhere('id', $returned->id)['note'] === 'Usulan dikembalikan, perlu diperbaiki')
                // The task inbox is still there.
                ->where('tasks.myProposals.total', 2));
    }

    public function test_a_proposer_who_leads_a_team_also_sees_its_waiting_proposals(): void
    {
        $division = WorkDirector::factory()->create();
        $leader = User::factory()->proposer($division)->create();
        $staff = User::factory()->proposer($division)->create(['team_leader_id' => $leader->id]);

        $this->proposal($staff, ProposalState::MenungguPersetujuanTl);

        $this->actingAs($leader)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('role.cards', fn ($cards) => collect($cards)->firstWhere('key', 'team')['value'] === 1));
    }

    public function test_a_director_sees_their_division(): void
    {
        $division = WorkDirector::factory()->create();
        $other = WorkDirector::factory()->create();
        $director = User::factory()->workDirector($division)->create();
        $proposer = User::factory()->proposer($division)->create();

        $this->proposal($proposer, ProposalState::MenungguPersetujuan);
        $this->proposal($proposer, ProposalState::Disetujui, ['hpe_value' => 50_000_000]);
        $this->proposal(User::factory()->proposer($other)->create(), ProposalState::Disetujui, ['work_director_id' => $other->id]);

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', 'direksi')
                // Rupiah travel as JSON numbers, so compared by value.
                ->where('role.cards', fn ($cards) => collect($cards)->pluck('value', 'key')->all() == [
                    'waiting' => 1,
                    'running' => 1,
                    'done' => 0,
                    'value' => 50_000_000,
                ])
                ->has('role.jobs', 2));
    }

    public function test_a_planner_sees_their_planning_work(): void
    {
        $planner = User::factory()->planner()->create();

        Procurement::factory()->plannedBy($planner)->create(['planning_approval_state' => PlanningApprovalState::Ditolak]);
        Procurement::factory()->plannedBy($planner)->planningSubmitted()->create();
        Procurement::factory()->plannedBy($planner)->planningApproved()->create();
        Procurement::factory()->plannedBy(User::factory()->planner()->create())->create();

        $this->actingAs($planner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', 'perencana')
                ->where('role.cards', fn ($cards) => collect($cards)->pluck('value', 'key')->only(['waiting', 'returned', 'approved'])->all() === [
                    'waiting' => 1,
                    'returned' => 1,
                    'approved' => 1,
                ])
                ->has('role.jobs', 3)
                // A returned planning comes first.
                ->where('role.jobs.0.note', 'Perencanaan dikembalikan untuk revisi')
                ->where('summary.needsRevision', 1));
    }

    public function test_an_executor_and_keuangan_see_their_part_of_the_job(): void
    {
        $executor = User::factory()->executor()->create();

        Procurement::factory()->executedBy($executor)->planningApproved()->create();
        Procurement::factory()->executedBy($executor)->planningApproved()->create([
            'finance_submitted_at' => now(),
            'hpe_value' => 10_000_000,
            'value_after_negotiation' => 9_000_000,
        ]);

        $this->actingAs($executor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', 'pelaksana')
                ->where('role.cards', fn ($cards) => collect($cards)->pluck('value', 'key')->only(['running', 'finance'])->all() === [
                    'running' => 1,
                    'finance' => 1,
                ]));

        $this->actingAs(User::factory()->role(UserRole::Keuangan)->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', 'keuangan')
                ->where('role.cards', fn ($cards) => collect($cards)->pluck('value', 'key')->only(['waiting', 'value'])->all() == [
                    'waiting' => 1,
                    'value' => 9_000_000,
                ])
                ->has('role.jobs', 1));
    }

    /**
     * A work proposal raised by a proposer.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function proposal(User $proposer, ProposalState $state, array $attributes = []): Procurement
    {
        return Procurement::factory()->create([
            'created_by' => $proposer->id,
            'work_director_id' => $proposer->work_director_id,
            'proposal_state' => $state,
            'proposal_submitted_at' => now(),
            ...$attributes,
        ]);
    }
}
