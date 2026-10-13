<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\ProposalState;
use App\Models\BudgetSource;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProposalReviewed;
use App\Notifications\ProposalSubmitted;
use Database\Seeders\DivisionUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staff propose work, the Direksi Pekerjaan of their division approves it,
 * and only then does it reach the procurement planners.
 */
class ProposalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected WorkDirector $division;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->division = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
    }

    public function test_a_proposer_sees_the_proposal_form(): void
    {
        $proposer = User::factory()->proposer($this->division)->create();

        $this->actingAs($proposer)
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('procurements/create')
                ->where('proposal.enabled', true)
                ->where('proposal.workDirectorId', $this->division->id)
            );
    }

    public function test_a_proposal_waits_for_the_division_director(): void
    {
        Notification::fake();

        $proposer = User::factory()->proposer($this->division)->create();
        $director = User::factory()->workDirector($this->division)->create();
        $otherDirector = User::factory()->workDirector(WorkDirector::factory()->create())->create();

        $this->actingAs($proposer)
            ->post(route('procurements.store'), [
                ...$this->payload(),
                // A proposer cannot raise work for another division.
                'work_director_id' => WorkDirector::factory()->create()->id,
                'attachments' => [UploadedFile::fake()->create('tor.pdf', 120, 'application/pdf')],
            ])
            ->assertRedirect();

        $procurement = Procurement::query()->firstOrFail();

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->proposal_state);
        $this->assertSame($this->division->id, $procurement->work_director_id);
        $this->assertNotNull($procurement->proposal_submitted_at);
        $this->assertCount(1, $procurement->attachments);
        Storage::disk('local')->assertExists($procurement->attachments->first()->path);

        Notification::assertSentTo($director, ProposalSubmitted::class);
        Notification::assertNotSentTo($otherDirector, ProposalSubmitted::class);

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::UsulanDiajukan->value,
        ]);
    }

    public function test_a_pending_proposal_stays_out_of_planning(): void
    {
        $procurement = $this->proposal();
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();

        $this->actingAs($teamLeader)
            ->get(route('pic-assignments.index'))
            ->assertOk()
            ->assertDontSee($procurement->name);

        $this->actingAs($teamLeader)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertDontSee($procurement->name);

        $this->actingAs($teamLeader)
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => User::factory()->planner()->create()->id,
            ])
            ->assertForbidden();
    }

    public function test_the_director_reviews_and_approves_the_proposal(): void
    {
        Notification::fake();

        $procurement = $this->proposal();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->get(route('proposals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('proposals/index')
                ->where('canApprove', true)
                ->has('awaiting', 1)
                ->where('awaiting.0.id', $procurement->id)
            );

        $this->actingAs($director)
            ->get(route('proposals.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('proposals/show')
                ->where('can.approve', true)
                ->where('can.revise', false)
            );

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertRedirect(route('proposals.index'));

        $procurement->refresh();

        $this->assertSame(ProposalState::Disetujui, $procurement->proposal_state);
        $this->assertSame($director->id, $procurement->proposal_reviewed_by);
        Notification::assertSentTo($procurement->creator, ProposalReviewed::class);

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('pic-assignments.index'))
            ->assertOk()
            ->assertSee($procurement->name);
    }

    public function test_a_returned_proposal_needs_a_note_and_can_be_resubmitted(): void
    {
        Notification::fake();

        $procurement = $this->proposal();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'return'])
            ->assertSessionHasErrors('note');

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), [
                'decision' => 'return',
                'note' => 'Lengkapi spesifikasi teknis.',
            ])
            ->assertRedirect();

        $procurement->refresh();
        $this->assertSame(ProposalState::Dikembalikan, $procurement->proposal_state);
        $this->assertSame('Lengkapi spesifikasi teknis.', $procurement->proposal_review_note);

        $proposer = $procurement->creator;

        $this->actingAs($proposer)
            ->post(route('proposals.attachments.store', $procurement), [
                'attachments' => [UploadedFile::fake()->image('foto.jpg')],
            ])
            ->assertRedirect();

        $this->assertCount(1, $procurement->attachments()->get());

        $this->actingAs($proposer)
            ->post(route('proposals.resubmit', $procurement))
            ->assertRedirect();

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->refresh()->proposal_state);
        Notification::assertSentTo($director, ProposalSubmitted::class);
    }

    public function test_a_director_of_another_division_cannot_decide(): void
    {
        $procurement = $this->proposal();
        $outsider = User::factory()->workDirector(WorkDirector::factory()->create())->create();

        $this->actingAs($outsider)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('proposals.show', $procurement))
            ->assertForbidden();

        $this->assertSame(ProposalState::MenungguPersetujuan, $procurement->refresh()->proposal_state);
    }

    public function test_the_proposer_cannot_approve_their_own_proposal(): void
    {
        $procurement = $this->proposal();

        $this->actingAs($procurement->creator)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_a_director_proposing_for_their_division_is_approved_at_once(): void
    {
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->post(route('procurements.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(ProposalState::Disetujui, Procurement::query()->firstOrFail()->proposal_state);
    }

    public function test_procurement_staff_still_create_procurements_directly(): void
    {
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->post(route('procurements.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(ProposalState::Disetujui, Procurement::query()->firstOrFail()->proposal_state);
    }

    public function test_the_dashboard_lists_the_proposals_waiting_on_the_director(): void
    {
        $procurement = $this->proposal();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tasks.proposals.total', 1)
                ->where('tasks.proposals.items.0.id', $procurement->id)
            );

        $this->actingAs($procurement->creator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tasks.proposals', null)
                ->where('tasks.myProposals.total', 1)
            );
    }

    public function test_the_division_seeder_creates_proposers_and_directors_once(): void
    {
        $this->seed(DivisionUserSeeder::class);

        $count = User::query()->count();
        $this->assertSame(count(DivisionUserSeeder::emails()), $count);

        $director = User::query()->where('email', 'asman.operasi@upkendari.go.id')->firstOrFail();
        $proposer = User::query()->where('email', 'akmal.hidayat@upkendari.go.id')->firstOrFail();

        $this->assertSame($director->work_director_id, $proposer->work_director_id);
        $this->assertTrue($proposer->can('create', Procurement::class));

        $this->seed(DivisionUserSeeder::class);

        $this->assertSame($count, User::query()->count());
    }

    /**
     * A pending proposal raised by a proposer of the test division.
     */
    protected function proposal(): Procurement
    {
        $proposer = User::factory()->proposer($this->division)->create();

        $this->actingAs($proposer)->post(route('procurements.store'), $this->payload());

        return Procurement::query()->latest('id')->firstOrFail();
    }

    /**
     * A valid create payload.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'name' => 'Penggantian Pompa Pendingin',
            'work_director_id' => $this->division->id,
            'target_unit_ids' => [TargetUnit::factory()->create()->id],
            'procurement_method_id' => ProcurementMethod::factory()->create()->id,
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'hpe_value' => 25_000_000,
            'progress_status_id' => ProgressStatus::factory()->asDefault()->create()->id,
        ];
    }
}
