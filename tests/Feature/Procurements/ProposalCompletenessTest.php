<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\PlanningApprovalState;
use App\Enums\ProposalState;
use App\Models\BudgetSource;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProposalSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A proposal reaches its TL / Asman only once the proposer has filled in
 * every item they owe: the identity data, the signed TOR and the Nota Dinas
 * Usulan. Until then it is held with the proposer.
 */
class ProposalCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected WorkDirector $division;

    protected DocumentType $memo;

    protected DocumentType $tor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->division = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
        $this->tor = DocumentType::factory()->create(['code' => 'tor', 'name' => 'TOR (Term of Reference)', 'upload_only' => true]);
        $this->memo = DocumentType::factory()->create(['code' => 'nota-dinas-usulan', 'name' => 'Nota Dinas Usulan', 'upload_only' => true]);
    }

    public function test_an_incomplete_proposal_is_held_until_its_papers_are_filed(): void
    {
        $proposer = User::factory()->proposer($this->division)->create();
        $director = User::factory()->workDirector($this->division)->create();

        $this->actingAs($proposer)
            ->post(route('procurements.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->sole();

        // Held: not announced, not in the queue, not approvable.
        $this->assertNull($procurement->proposal_completed_at);
        $this->assertTrue($procurement->isProposalHeld());
        Notification::assertNotSentTo($director, ProposalSubmitted::class);
        $this->assertSame(0, $procurement->activities()->where('type', ActivityType::UsulanDiajukan->value)->count());

        $this->actingAs($director)
            ->get(route('proposals.index'))
            ->assertInertia(fn (Assert $page) => $page->has('awaiting', 0));

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($proposer)
            ->get(route('proposals.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('proposal.is_held', true)
                ->where('proposal.missing', ['Unggahan TOR', 'Unggahan Nota Dinas Usulan']));

        // The proposer files both papers: the proposal moves on by itself.
        $this->upload($proposer, $procurement, $this->tor);
        $this->assertTrue($procurement->refresh()->isProposalHeld());

        $this->upload($proposer, $procurement, $this->memo);

        $procurement->refresh();
        $this->assertNotNull($procurement->proposal_completed_at);
        $this->assertFalse($procurement->isProposalHeld());
        Notification::assertSentToTimes($director, ProposalSubmitted::class, 1);
        $this->assertSame(1, $procurement->activities()->where('type', ActivityType::UsulanDiajukan->value)->count());

        $this->actingAs($director)
            ->get(route('proposals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('awaiting', 1)
                ->where('awaiting.0.id', $procurement->id));

        $this->actingAs($director)
            ->post(route('proposals.decide', $procurement), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame(ProposalState::Disetujui, $procurement->refresh()->proposal_state);
    }

    public function test_missing_identity_data_holds_a_proposal_too(): void
    {
        $proposer = User::factory()->proposer($this->division)->create();
        $director = User::factory()->workDirector($this->division)->create();

        $procurement = Procurement::factory()->create([
            'work_director_id' => $this->division->id,
            'created_by' => $proposer->id,
            'coa_number' => null,
            'proposal_memo_date' => null,
            'proposal_submitted_at' => now(),
            'proposal_state' => ProposalState::MenungguPersetujuan,
        ]);
        $this->upload($proposer, $procurement, $this->tor);
        $this->upload($proposer, $procurement, $this->memo);

        $procurement->refresh();
        $this->assertSame(['Nomor COA', 'Tanggal Nota Dinas Usulan'], $procurement->missingProposalRequirements());
        $this->assertTrue($procurement->isProposalHeld());
        $this->assertFalse($director->can('approveProposal', $procurement));

        // Filling the data in hands it on, and only once.
        $procurement->forceFill(['coa_number' => '5110100000', 'proposal_memo_date' => '2026-10-13'])->save();
        $procurement->forceFill(['notes' => 'Diperbarui lagi.'])->save();

        $procurement->refresh();
        $this->assertFalse($procurement->isProposalHeld());
        $this->assertTrue($director->can('approveProposal', $procurement));
        Notification::assertSentToTimes($director, ProposalSubmitted::class, 1);

        // Removing a paper while it waits takes it back to the proposer.
        $procurement->documentFor($this->memo->id)?->signedUploads()->each(fn ($upload) => $upload->delete());

        $this->assertTrue($procurement->refresh()->isProposalHeld());
        $this->assertFalse($director->can('approveProposal', $procurement));
    }

    public function test_the_existing_queue_skips_held_proposals_on_the_dashboard(): void
    {
        $proposer = User::factory()->proposer($this->division)->create();
        $director = User::factory()->workDirector($this->division)->create();

        Procurement::factory()->create([
            'work_director_id' => $this->division->id,
            'created_by' => $proposer->id,
            'pr_po_number' => null,
            'proposal_submitted_at' => now(),
            'proposal_state' => ProposalState::MenungguPersetujuan,
        ]);

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('tasks.proposals.total', 0));
    }

    public function test_planning_cannot_be_submitted_while_identity_data_is_missing(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'coa_number' => null,
            'planning_approval_state' => PlanningApprovalState::BelumDiajukan,
        ]);

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.submitPlanning', false)
                ->where('detail.missing_identity_data', ['Nomor COA'])
                ->where('detail.pending_required_planning.0', 'Nomor COA (data identitas)'));
    }

    /**
     * File a signed scan of one proposal paper.
     */
    protected function upload(User $proposer, Procurement $procurement, DocumentType $type): void
    {
        $this->actingAs($proposer)
            ->post(route('procurements.documents.upload', $procurement), [
                'document_type_id' => $type->id,
                'files' => [UploadedFile::fake()->create("{$type->code}.pdf", 40, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * A complete proposal form, without its papers.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'name' => 'Penggantian Pompa Pendingin',
            'work_director_id' => $this->division->id,
            'target_unit_ids' => [TargetUnit::factory()->create()->id],
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'progress_status_id' => ProgressStatus::factory()->asDefault()->create()->id,
            'prk_number' => '2026.AO.01.001',
            'pr_po_number' => '1000012345',
            'coa_number' => '5110100000',
            'proposal_memo_number' => 'ND-021/USL/2026',
            'proposal_memo_date' => '2026-10-13',
        ];
    }
}
