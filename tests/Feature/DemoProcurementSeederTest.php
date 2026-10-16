<?php

namespace Tests\Feature;

use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoProcurementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The demo data walks every job through the real journey, from the staff
 * proposal to the payment.
 */
class DemoProcurementSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_covers_every_point_of_the_journey(): void
    {
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoProcurementSeeder::class);

        $demo = fn () => Procurement::query()->where('notes', DemoProcurementSeeder::MARKER);

        $this->assertSame(72, $demo()->count());

        // Every job started as a staff proposal, numbered as one until it got
        // its contract number.
        $this->assertSame(0, $demo()->whereNull('proposal_submitted_at')->count());
        // Five wait for their TL (or straight for the Asman when the staff
        // member has no TL), five more already passed their TL.
        $this->assertSame(10, $demo()->whereIn('proposal_state', [ProposalState::MenungguPersetujuanTl, ProposalState::MenungguPersetujuan])->count());
        $this->assertGreaterThan(0, $demo()->where('proposal_state', ProposalState::MenungguPersetujuanTl)->count());
        $this->assertSame(3, $demo()->where('proposal_state', ProposalState::Dikembalikan)->count());
        $this->assertTrue($demo()->where('proposal_state', '!=', ProposalState::Disetujui)->get()->every(fn ($job) => str_starts_with($job->number, 'USL/')));

        // Planning: unassigned, being drafted, at ICC, returned.
        $this->assertSame(4, $demo()->proposalApproved()->whereNull('planner_id')->count());
        $this->assertSame(6, $demo()->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan)->count());
        $this->assertSame(4, $demo()->where('planning_approval_state', PlanningApprovalState::Ditolak)->count());

        // Execution, Keuangan, paid.
        $this->assertSame(16, $demo()->where('planning_approval_state', PlanningApprovalState::Disetujui)->whereNull('finance_submitted_at')->count());
        // TL Pengadaan appoints the executor after ICC's approval, then
        // decides on the execution before it reaches Keuangan.
        $this->assertSame(2, $demo()->where('planning_approval_state', PlanningApprovalState::Disetujui)->whereNull('executor_id')->count());
        $this->assertSame(3, $demo()->where('execution_approval_state', PlanningApprovalState::MenungguPersetujuan)->count());
        $this->assertSame(1, $demo()->where('execution_approval_state', PlanningApprovalState::Ditolak)->count());
        $this->assertSame(0, $demo()->whereNotNull('finance_submitted_at')->where('execution_approval_state', '!=', PlanningApprovalState::Disetujui)->count());
        $this->assertSame(8, $demo()->whereNotNull('finance_submitted_at')->whereNull('payment_completed_at')->count());
        $this->assertSame(10, $demo()->whereNotNull('payment_completed_at')->whereNotNull('completed_at')->count());

        // A contract job carries its contract number and its contract.
        $paid = $demo()->whereNotNull('payment_completed_at')->with(['documents.documentType', 'contractNumberFormat'])->get();
        $this->assertTrue($paid->every(fn ($job) => ! str_starts_with($job->number, 'USL/')));
        $this->assertTrue($paid->every(fn ($job) => $job->documents->contains(fn ($document) => in_array($document->documentType->code, ['spk', 'lampiran-sp'], true))));

        // Every Penyerahan and Pembayaran step of a paid job is ticked, and
        // the steps of contract-read jobs belong to those jobs alone.
        $open = ProcurementChecklist::query()
            ->whereIn('procurement_id', $paid->pluck('id'))
            ->where('stage', ProcurementStage::Keuangan)
            ->where('is_completed', false)
            ->count();
        $this->assertSame(0, $open);

        $inFinance = $demo()->whereNotNull('finance_submitted_at')->whereNull('payment_completed_at')->pluck('id');
        $sections = ChecklistItem::query()
            ->whereIn('id', ProcurementChecklist::query()->whereIn('procurement_id', $inFinance)->where('stage', ProcurementStage::Keuangan)->select('checklist_item_id'))
            ->pluck('section')
            ->unique()
            ->map(fn (?FinanceSection $section): ?string => $section?->value)
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['pembayaran', 'penyerahan'], $sections);

        // The approval history runs in order from the proposal to the
        // payment; the papers drawn along the way are dated with their step.
        $closed = $paid->first();
        $approvals = $closed->activities()->oldest('created_at')->oldest('id')->get()
            ->filter(fn ($activity) => $activity->approvalStage() !== null)
            ->map(fn ($activity) => $activity->type->value)
            ->values();

        $this->assertSame(['dibuat', 'usulan_diajukan'], $approvals->take(2)->all());
        $this->assertContains('usulan_disetujui', $approvals->all());
        $this->assertContains('perencanaan_disetujui', $approvals->all());
        $this->assertContains('pelaksanaan_diajukan', $approvals->all());
        $this->assertContains('pelaksanaan_disetujui', $approvals->all());
        $this->assertSame('pembayaran_selesai', $approvals->last());
        $this->assertTrue($closed->documents()->get()->every(fn ($document) => $document->generated_at->lessThan($closed->payment_completed_at)));
    }

    public function test_running_it_again_replaces_the_previous_demo_only(): void
    {
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);
        $other = Procurement::factory()->create(['notes' => 'Pengadaan sungguhan.']);

        $this->seed(DemoProcurementSeeder::class);
        $this->seed(DemoProcurementSeeder::class);

        $this->assertSame(72, Procurement::query()->where('notes', DemoProcurementSeeder::MARKER)->count());
        $this->assertModelExists($other);
    }
}
