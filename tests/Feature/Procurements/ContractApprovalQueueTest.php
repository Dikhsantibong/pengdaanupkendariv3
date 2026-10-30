<?php

namespace Tests\Feature\Procurements;

use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementDeliveryOrder;
use App\Models\User;
use App\Models\WorkDirector;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Amendments and Delivery Orders wait in the Approval menu for whoever
 * acts on them next: the proposer's TL, the division's Asman, Rendan (DO)
 * and Laksdan.
 */
class ContractApprovalQueueTest extends TestCase
{
    use RefreshDatabase;

    protected WorkDirector $division;

    protected User $teamLeader;

    protected User $proposer;

    protected User $director;

    protected User $planner;

    protected User $executor;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);

        $this->division = WorkDirector::factory()->create();
        $this->teamLeader = User::factory()->proposer($this->division)->create();
        $this->proposer = User::factory()->proposer($this->division)->create(['team_leader_id' => $this->teamLeader->id]);
        $this->director = User::factory()->workDirector($this->division)->create();
        $this->planner = User::factory()->planner()->create();
        $this->executor = User::factory()->executor()->create();

        $this->procurement = Procurement::factory()
            ->plannedBy($this->planner)
            ->executedBy($this->executor)
            ->create(['work_director_id' => $this->division->id]);
    }

    public function test_the_proposers_team_leader_finds_the_request_in_the_approval_menu(): void
    {
        $amendment = $this->amendment('menunggu_persetujuan_tl');

        $this->actingAs($this->teamLeader)
            ->get(route('approvals.index', ['queue' => 'amendment']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.menus.approvals', true)
                ->where('queues', fn ($queues): bool => collect($queues)->firstWhere('key', 'amendment')['waiting'] === 1)
                ->where('queue', 'amendment')
                ->where('requests.data.0.id', $amendment->id)
                ->where('requests.data.0.title', 'Amandemen I')
                ->where('requests.data.0.url', route('procurements.show', [$this->procurement, 'tab' => 'amandemen'])));

        // Nobody else at that level, and never the proposer.
        $this->actingAs($this->director)
            ->get(route('approvals.index', ['queue' => 'amendment']))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data', []));
        $this->actingAs($this->proposer)
            ->get(route('approvals.index', ['queue' => 'amendment']))
            ->assertForbidden();
    }

    public function test_the_asman_finds_the_requests_of_their_division(): void
    {
        $this->amendment('menunggu_persetujuan_asman');
        $order = $this->deliveryOrder('menunggu_persetujuan_asman');

        // Another division's request is not theirs.
        $this->deliveryOrder('menunggu_persetujuan_asman', Procurement::factory()->create());

        $this->actingAs($this->director)
            ->get(route('approvals.index', ['queue' => 'delivery-order']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('queue', 'delivery-order')
                ->where('requests.data', fn ($rows): bool => collect($rows)->pluck('id')->all() === [$order->id])
                ->where('requests.data.0.title', 'DO ke-1')
                // Their own proposal queue (empty) first, then the requests.
                ->where('queues', fn ($queues): bool => collect($queues)->pluck('waiting', 'key')->all() === ['proposal' => 0, 'amendment' => 1, 'delivery-order' => 1]));
    }

    public function test_rendan_and_laksdan_find_the_steps_on_their_side(): void
    {
        $atRendan = $this->deliveryOrder('di_rendan');
        $atLaksdan = $this->deliveryOrder('di_laksdan', sequence: 2);
        $amendment = $this->amendment('menunggu_tl_pengadaan');

        $this->actingAs($this->planner)
            ->get(route('approvals.index', ['queue' => 'delivery-order']))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data', fn ($rows): bool => collect($rows)->pluck('id')->all() === [$atRendan->id]));

        $this->actingAs($this->executor)
            ->get(route('approvals.index', ['queue' => 'delivery-order']))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data', fn ($rows): bool => collect($rows)->pluck('id')->all() === [$atLaksdan->id]));

        $this->actingAs($this->executor)
            ->get(route('approvals.index', ['queue' => 'amendment']))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.id', $amendment->id));

        // Another job's PIC has no part in them: no amendment or DO queue.
        $stranger = User::factory()->executor()->create();
        $this->actingAs($stranger)
            ->get(route('approvals.index', ['queue' => 'amendment']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('queue', null)->where('requests', null));
    }

    public function test_what_was_decided_moves_to_the_history(): void
    {
        $amendment = $this->amendment('menunggu_persetujuan_asman', [
            'tl_reviewed_by' => $this->teamLeader->id,
            'tl_reviewed_at' => now(),
        ]);

        $this->actingAs($this->teamLeader)
            ->get(route('approvals.index', ['queue' => 'amendment', 'view' => 'decided']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('view', 'decided')
                ->where('requests.data.0.id', $amendment->id)
                ->where('requests.data.0.state_label', 'Menunggu Persetujuan Direksi Pekerjaan'));
    }

    public function test_someone_with_no_part_in_them_still_has_no_approval_menu(): void
    {
        $outsider = User::factory()->proposer(WorkDirector::factory()->create())->create();
        $this->amendment('menunggu_persetujuan_tl');

        $this->actingAs($outsider)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions.menus.approvals', false));

        $this->actingAs($outsider)->get(route('approvals.index'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function amendment(string $state, array $attributes = []): ProcurementAmendment
    {
        return ProcurementAmendment::factory()->create([
            'procurement_id' => $this->procurement->id,
            'state' => $state,
            'created_by' => $this->proposer->id,
            'submitted_at' => now(),
            ...$attributes,
        ]);
    }

    protected function deliveryOrder(string $state, ?Procurement $procurement = null, int $sequence = 1): ProcurementDeliveryOrder
    {
        return ProcurementDeliveryOrder::factory()->create([
            'procurement_id' => ($procurement ?? $this->procurement)->id,
            'sequence' => $sequence,
            'state' => $state,
            'created_by' => $this->proposer->id,
            'submitted_at' => now(),
        ]);
    }
}
