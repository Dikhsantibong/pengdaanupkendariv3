<?php

namespace Tests\Feature;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The approval menu shows each team leader only the queue they decide on,
 * filtered and paginated on the server.
 */
class ApprovalQueueTest extends TestCase
{
    use RefreshDatabase;

    protected Procurement $planningWaiting;

    protected Procurement $executionWaiting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planningWaiting = Procurement::factory()->planningSubmitted()->create(['name' => 'Perencanaan Menunggu']);
        $this->executionWaiting = Procurement::factory()->planningApproved()->create([
            'name' => 'Pelaksanaan Menunggu',
            'execution_approval_state' => PlanningApprovalState::MenungguPersetujuan,
            'execution_submitted_at' => now(),
        ]);
    }

    public function test_tl_icc_sees_the_planning_queue_and_the_do_queue_as_rendan(): void
    {
        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('approvals/index')
                ->where('queue', 'planning')
                // Not the execution or amendment queues: those are not theirs.
                ->where('queues', fn ($queues): bool => collect($queues)->pluck('key')->all() === ['planning', 'delivery-order'])
                ->where('queues.0.waiting', 1)
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $this->planningWaiting->id));

        // Asking for the other queue falls back to their own.
        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->get(route('approvals.index', ['queue' => 'execution']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('queue', 'planning')
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $this->planningWaiting->id));
    }

    public function test_each_submission_carries_what_the_reviewer_reads_before_deciding(): void
    {
        $type = DocumentType::factory()->create(['stage' => ProcurementStage::Pelaksanaan, 'name' => 'Kontrak']);
        $document = $this->executionWaiting->documents()->create([
            'document_type_id' => $type->id,
            'title' => 'Kontrak',
            'file_name' => 'kontrak.pdf',
            'template_version' => 0,
            'rendered_body' => '',
            'generated_at' => now(),
        ]);
        $document->signedUploads()->create([
            'path' => 'signed/kontrak-ttd.pdf',
            'file_name' => 'kontrak-ttd.pdf',
            'mime' => 'application/pdf',
        ]);
        $this->executionWaiting->forceFill(['execution_revision' => 1, 'execution_review_note' => 'Lengkapi jaminan.'])->save();

        $id = $this->executionWaiting->id;

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('approvals.index', ['queue' => 'execution']))
            ->assertInertia(fn (Assert $page) => $page
                ->where("reviews.{$id}.can_decide", true)
                ->where("reviews.{$id}.revision", 1)
                ->where("reviews.{$id}.note", 'Lengkapi jaminan.')
                ->has("reviews.{$id}.papers", 1)
                ->where("reviews.{$id}.papers.0.name", 'Kontrak · kontrak-ttd.pdf')
                ->where("reviews.{$id}.papers.0.is_image", false));

        // Seen in the decided list, it is no longer theirs to decide.
        $this->executionWaiting->forceFill(['execution_approval_state' => PlanningApprovalState::Ditolak, 'execution_reviewed_at' => now()])->save();

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('approvals.index', ['queue' => 'execution', 'view' => 'decided']))
            ->assertInertia(fn (Assert $page) => $page->where("reviews.{$id}.can_decide", false));
    }

    public function test_tl_pengadaan_sees_the_execution_queue_and_the_contract_requests(): void
    {
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('approvals.index', ['queue' => 'planning']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('queue', 'execution')
                // Not the planning queue; amendments and DOs are issued by Laksdan.
                ->where('queues', fn ($queues): bool => collect($queues)->pluck('key')->all() === ['execution', 'handover', 'amendment', 'delivery-order'])
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $this->executionWaiting->id));
    }

    public function test_the_administrator_switches_between_both_queues(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('approvals.index', ['queue' => 'execution']))
            ->assertInertia(fn (Assert $page) => $page
                // Perencanaan, Pelaksanaan, Penyerahan, Pembayaran, Amandemen, DO.
                ->has('queues', 6)
                ->where('queue', 'execution')
                ->where('procurements.data.0.id', $this->executionWaiting->id));
    }

    public function test_a_user_without_review_rights_sees_no_queue(): void
    {
        $this->actingAs(User::factory()->planner()->create())
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('queues', 0)
                ->where('queue', null)
                ->where('procurements', null));
    }

    public function test_the_history_lists_only_the_decisions_of_that_queue(): void
    {
        $decided = Procurement::factory()->planningApproved()->create([
            'execution_approval_state' => PlanningApprovalState::Disetujui,
            'execution_submitted_at' => now()->subDay(),
            'execution_reviewed_at' => now(),
        ]);

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('approvals.index', ['view' => 'decided']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('view', 'decided')
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $decided->id));
    }

    public function test_the_queue_is_searchable_and_paginated(): void
    {
        Procurement::factory()->planningSubmitted()->count(20)->create();

        $teamLeader = User::factory()->teamLeaderIcc()->create();

        $this->actingAs($teamLeader)
            ->get(route('approvals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('queues.0.waiting', 21)
                ->has('procurements.data', 15)
                ->where('procurements.meta.total', 21));

        $this->actingAs($teamLeader)
            ->get(route('approvals.index', ['search' => 'Perencanaan Menunggu']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $this->planningWaiting->id)
                ->where('filters.search', 'Perencanaan Menunggu'));
    }
}
