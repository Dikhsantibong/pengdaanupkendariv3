<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\ProposalState;
use App\Models\BudgetSource;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProposalReviewed;
use App\Notifications\ProposalSubmitted;
use Database\Seeders\DivisionUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A staff proposal is approved by the proposer's team leader first and the
 * division's Asman second, then reaches planning for a PIC to be appointed.
 */
class TeamLeaderApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected WorkDirector $division;

    protected User $teamLeader;

    protected User $staff;

    protected User $asman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
        $this->teamLeader = User::factory()->proposer($this->division)->create(['position' => 'TL Operasi']);
        $this->staff = User::factory()->proposer($this->division)->create(['team_leader_id' => $this->teamLeader->id]);
        $this->asman = User::factory()->workDirector($this->division)->create();
    }

    public function test_a_staff_proposal_goes_to_the_team_leader_first(): void
    {
        Notification::fake();

        $procurement = $this->propose($this->staff);

        $this->assertSame(ProposalState::MenungguPersetujuanTl, $procurement->proposal_state);
        Notification::assertSentTo($this->teamLeader, ProposalSubmitted::class);
        Notification::assertNotSentTo($this->asman, ProposalSubmitted::class);

        // The TL sees it in their queue and on the dashboard.
        $this->actingAs($this->teamLeader)
            ->get(route('proposals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canApprove', true)
                ->has('awaiting', 1)
                ->where('awaiting.0.state', 'menunggu_persetujuan_tl'));

        $this->actingAs($this->teamLeader)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('tasks.proposals.total', 1));

        // The Asman waits for the TL.
        $this->actingAs($this->asman)
            ->get(route('proposals.index'))
            ->assertInertia(fn ($page) => $page->has('awaiting', 0));

        $this->actingAs($this->asman)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_the_team_leader_approval_passes_it_to_the_asman(): void
    {
        Notification::fake();

        $procurement = $this->propose($this->staff);

        $this->actingAs($this->teamLeader)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertRedirect();

        $procurement->refresh();
        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->proposal_state);
        $this->assertSame($this->teamLeader->id, $procurement->proposal_tl_reviewed_by);
        $this->assertNull($procurement->proposal_reviewed_by);
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::UsulanDisetujuiTl->value,
        ]);

        Notification::assertSentTo($this->asman, ProposalSubmitted::class);
        Notification::assertSentTo($this->staff, ProposalReviewed::class);

        // Still out of planning until the Asman approves.
        $this->assertFalse($procurement->isProposalApproved());

        $this->actingAs($this->asman)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertTrue($procurement->refresh()->isProposalApproved());
        $this->assertSame($this->asman->id, $procurement->proposal_reviewed_by);
    }

    public function test_the_team_leader_can_return_it_and_a_resubmission_starts_over(): void
    {
        $procurement = $this->propose($this->staff);

        $this->actingAs($this->teamLeader)
            ->post(route('proposals.decide', $procurement), ['decision' => 'return', 'note' => 'Lengkapi PRK.'])
            ->assertRedirect();

        $this->assertSame(ProposalState::Dikembalikan, $procurement->refresh()->proposal_state);

        $this->actingAs($this->staff)
            ->get(route('proposals.show', $procurement))
            ->assertInertia(fn ($page) => $page
                ->where('proposal.reviewed_by', $this->teamLeader->name)
                ->where('proposal.review_note', 'Lengkapi PRK.'));

        $this->actingAs($this->staff)
            ->post(route('proposals.resubmit', $procurement))
            ->assertRedirect();

        $this->assertSame(ProposalState::MenungguPersetujuanTl, $procurement->refresh()->proposal_state);
        $this->assertNull($procurement->proposal_tl_reviewed_by);
    }

    public function test_another_team_leader_cannot_decide_or_see_it(): void
    {
        $procurement = $this->propose($this->staff);
        $otherLeader = User::factory()->proposer($this->division)->create();
        User::factory()->proposer($this->division)->create(['team_leader_id' => $otherLeader->id]);

        $this->actingAs($otherLeader)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($otherLeader)
            ->get(route('procurements.show', $procurement))
            ->assertForbidden();

        $this->actingAs($this->teamLeader)
            ->get(route('procurements.show', $procurement))
            ->assertOk();
    }

    public function test_a_team_leader_proposal_and_staff_without_tl_go_straight_to_the_asman(): void
    {
        $this->assertSame(ProposalState::MenungguPersetujuan, $this->propose($this->teamLeader)->proposal_state);

        $loner = User::factory()->proposer($this->division)->create();
        $this->assertSame(ProposalState::MenungguPersetujuan, $this->propose($loner)->proposal_state);
    }

    public function test_a_team_leader_who_is_also_the_direksi_pekerjaan_approves_once(): void
    {
        // As for K3 KAM and Lingkungan: the TL is the division's approver.
        $k3 = WorkDirector::factory()->create(['name' => 'Team Leader K3']);
        $tlK3 = User::factory()->workDirector($k3)->create();
        $staffK3 = User::factory()->proposer($k3)->create(['team_leader_id' => $tlK3->id]);

        $procurement = $this->propose($staffK3, $k3);

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->proposal_state);

        $this->actingAs($tlK3)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertTrue($procurement->refresh()->isProposalApproved());
    }

    public function test_an_administrator_sets_the_team_leader_but_never_the_user_themselves(): void
    {
        $administrator = User::factory()->administrator()->create();
        $payload = [
            'name' => $this->staff->name,
            'email' => $this->staff->email,
            'role' => $this->staff->role->value,
            'work_director_id' => $this->division->id,
            'is_active' => true,
        ];

        $this->actingAs($administrator)
            ->put(route('users.update', $this->staff), [...$payload, 'team_leader_id' => $this->staff->id])
            ->assertSessionHasErrors('team_leader_id');

        $this->actingAs($administrator)
            ->put(route('users.update', $this->staff), [...$payload, 'team_leader_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->staff->refresh()->team_leader_id);
    }

    public function test_the_division_seeder_links_staff_to_their_team_leaders(): void
    {
        $this->seed(DivisionUserSeeder::class);

        $tl = User::query()->where('email', 'akmal.hidayat@upkendari.go.id')->firstOrFail();
        $staff = User::query()->where('email', 'rohmat.hidayat@upkendari.go.id')->firstOrFail();
        $finance = User::query()->where('email', 'muhammad.jaya.junaid@upkendari.go.id')->firstOrFail();

        $this->assertSame($tl->id, $staff->team_leader_id);
        $this->assertNull($tl->team_leader_id);
        $this->assertSame('keuangan', $finance->role->value);
        $this->assertTrue($finance->can('create', Procurement::class));
    }

    /**
     * Raise a proposal as the given user.
     */
    protected function propose(User $proposer, ?WorkDirector $division = null): Procurement
    {
        $this->actingAs($proposer)->post(route('procurements.store'), [
            'name' => 'Usulan '.$proposer->name,
            'work_director_id' => ($division ?? $this->division)->id,
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'target_unit_ids' => [TargetUnit::factory()->create()->id],
            'prk_number' => '2026.AO.01.001',
            'pr_po_number' => '1000012345',
            'coa_number' => '5110100000',
            'proposal_memo_number' => 'ND-021/USL/2026',
            'proposal_memo_date' => '2026-10-13',
            'progress_status_id' => ProgressStatus::factory()->asDefault()->create()->id,
        ])->assertSessionHasNoErrors();

        return Procurement::query()->latest('id')->firstOrFail();
    }
}
