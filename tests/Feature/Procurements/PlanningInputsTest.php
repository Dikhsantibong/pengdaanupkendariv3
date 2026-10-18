<?php

namespace Tests\Feature\Procurements;

use App\Enums\ChecklistInput;
use App\Enums\ProcurementStage;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The planning steps that ask for data: the "Penawaran" step lists the calon
 * mitra with their offers (total before negotiation and the scanned
 * quotation), and the "Nota Dinas ke Pengadaan" step asks for the memo's
 * number and date beside its upload.
 */
class PlanningInputsTest extends TestCase
{
    use RefreshDatabase;

    protected User $planner;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(MasterDataSeeder::class);

        $this->planner = User::factory()->planner()->create();
        $this->procurement = Procurement::factory()->plannedBy($this->planner)->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'procurement_method_id' => null,
        ]);

        app(ProcurementService::class)->syncChecklists($this->procurement->refresh());
    }

    public function test_the_steps_carry_their_inputs(): void
    {
        $this->assertSame(ChecklistInput::PartnerCandidates, $this->step('Penawaran')->checklistItem->input_kind);
        $this->assertSame(ChecklistInput::ProcurementMemo, $this->step('Nota Dinas ke Pengadaan')->checklistItem->input_kind);
        // The quotation scans live on the candidates now.
        $this->assertFalse($this->step('Penawaran')->checklistItem->requiresDocument());
    }

    public function test_the_penawaran_step_waits_for_every_candidate_offer(): void
    {
        $step = $this->step('Penawaran');

        $this->tick($step)->assertSessionHasErrors('is_completed');

        $this->actingAs($this->planner)
            ->post(route('procurements.partner-candidates.store', $this->procurement), [
                'name' => 'PT Satu',
                'director_name' => 'Budi',
                'address' => 'Kendari',
                'quotation_number' => '001/SPH/2026',
                'quotation_date' => '2026-09-01',
                'quotation_value' => 250_000_000,
            ])
            ->assertSessionHasNoErrors();

        $candidate = $this->procurement->partnerCandidates()->sole();

        // Still without its quotation scan.
        $this->assertSame(['berkas penawaran harga'], $candidate->missing());
        $this->tick($step)->assertSessionHasErrors('is_completed');

        $this->actingAs($this->planner)
            ->post(route('procurements.partner-candidates.update', [$this->procurement, $candidate]), [
                '_method' => 'put',
                'name' => 'PT Satu',
                'director_name' => 'Budi',
                'address' => 'Kendari',
                'quotation_number' => '001/SPH/2026',
                'quotation_date' => '2026-09-01',
                'quotation_value' => 245_000_000,
                'quotation_file' => UploadedFile::fake()->create('penawaran.pdf', 30, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $candidate->refresh();
        $this->assertSame('245000000.00', $candidate->quotation_value);
        $this->assertSame('penawaran.pdf', $candidate->quotation_file_name);
        Storage::disk('local')->assertExists($candidate->quotation_file_path);

        $this->actingAs($this->planner)
            ->get(route('procurements.partner-candidates.quotation', [$this->procurement, $candidate]))
            ->assertOk();

        $this->tick($step)->assertSessionHasNoErrors();
        $this->assertTrue($step->refresh()->is_completed);

        // A replaced scan removes the previous one.
        $previous = $candidate->quotation_file_path;

        $this->actingAs($this->planner)
            ->post(route('procurements.partner-candidates.update', [$this->procurement, $candidate]), [
                '_method' => 'put',
                'name' => 'PT Satu',
                'quotation_file' => UploadedFile::fake()->create('penawaran-revisi.pdf', 30, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($previous);
        Storage::disk('local')->assertExists($candidate->refresh()->quotation_file_path);
    }

    public function test_a_ticked_penawaran_step_with_an_incomplete_offer_still_blocks_submission(): void
    {
        $step = $this->step('Penawaran');
        $step->forceFill(['is_completed' => true, 'completed_at' => now()])->save();
        $this->procurement->partnerCandidates()->create(['name' => 'PT Tanpa Berkas']);

        $pending = $this->procurement->refresh()->load('checklists.checklistItem')
            ->pendingRequiredPlanningChecklists()
            ->map(fn (ProcurementChecklist $checklist): string => $checklist->checklistItem->name);

        $this->assertContains('Penawaran', $pending->all());
        $this->assertFalse($this->planner->can('submitPlanning', $this->procurement));
    }

    public function test_the_nota_dinas_ke_pengadaan_step_asks_for_its_number_and_date(): void
    {
        $step = $this->step('Nota Dinas ke Pengadaan');

        $this->actingAs($this->planner)
            ->put(route('procurements.checklists.input', [$this->procurement, $step]), [])
            ->assertSessionHasErrors(['icc_memo_number', 'icc_memo_date']);

        $this->actingAs($this->planner)
            ->put(route('procurements.checklists.input', [$this->procurement, $step]), [
                'icc_memo_number' => 'ND-031/ICC/2026',
                'icc_memo_date' => '2026-09-10',
            ])
            ->assertSessionHasNoErrors();

        $this->procurement->refresh();
        $this->assertSame('ND-031/ICC/2026', $this->procurement->icc_memo_number);
        $this->assertSame('2026-09-10', $this->procurement->icc_memo_date?->toDateString());

        $this->actingAs($this->planner)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.perencanaan', fn ($rows) => collect($rows)
                    ->firstWhere('name', 'Nota Dinas ke Pengadaan')['input']['values']['icc_memo_number'] === 'ND-031/ICC/2026'));
    }

    public function test_the_calon_mitra_step_has_no_direct_input_endpoint(): void
    {
        $this->actingAs($this->planner)
            ->put(route('procurements.checklists.input', [$this->procurement, $this->step('Penawaran')]), [])
            ->assertNotFound();
    }

    public function test_editing_the_identity_keeps_the_memo_filled_on_the_checklist(): void
    {
        $this->procurement->forceFill(['icc_memo_number' => 'ND-031/ICC/2026', 'icc_memo_date' => '2026-09-10'])->save();

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->get(route('procurements.edit', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page->missing('procurement.icc_memo_number'));
    }

    protected function step(string $name): ProcurementChecklist
    {
        return $this->procurement->checklists()
            ->where('stage', ProcurementStage::Perencanaan->value)
            ->whereHas('checklistItem', fn ($query) => $query->where('name', $name))
            ->with('checklistItem')
            ->firstOrFail();
    }

    protected function tick(ProcurementChecklist $step): TestResponse
    {
        return $this->actingAs($this->planner)
            ->put(route('procurements.checklists.update', [$this->procurement, $step]), ['is_completed' => true]);
    }
}
