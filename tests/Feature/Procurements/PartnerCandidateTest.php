<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The planning PIC lists the calon mitra (several, or one for a directly
 * appointed SPPL); the "Pelaksana" step picks the executor among them and
 * asks for its bank account.
 */
class PartnerCandidateTest extends TestCase
{
    use RefreshDatabase;

    protected User $planner;

    protected User $executor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);

        $this->planner = User::factory()->planner()->create();
        $this->executor = User::factory()->executor()->create();
    }

    public function test_the_planner_lists_several_candidates_and_the_executor_picks_one(): void
    {
        $procurement = $this->onFormat('SPK');

        foreach (['PT Satu', 'PT Dua', 'PT Tiga'] as $index => $name) {
            $this->actingAs($this->planner)
                ->post(route('procurements.partner-candidates.store', $procurement), [
                    'name' => $name,
                    'director_name' => "Direktur {$index}",
                    'quotation_number' => "00{$index}/SPH/2026",
                    'quotation_date' => '2026-08-01',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertCount(3, $procurement->partnerCandidates()->get());
        // Listing is not picking.
        $this->assertNull($procurement->refresh()->partner_candidate_id);

        $procurement->forceFill(['planning_approval_state' => PlanningApprovalState::Disetujui])->save();
        app(ProcurementService::class)->syncChecklists($procurement);

        $step = $this->pelaksanaStep($procurement);
        $second = $procurement->partnerCandidates()->where('name', 'PT Dua')->firstOrFail();

        // The bank account alone is not enough: the executor is picked first.
        $this->actingAs($this->executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'bank_account_number' => '123',
                'bank_name' => 'BRI',
                'bank_account_holder' => 'PT Dua',
            ])
            ->assertSessionHasErrors('partner_candidate_id');

        $this->actingAs($this->executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'partner_candidate_id' => $second->id,
                'bank_account_number' => '123',
                'bank_name' => 'BRI',
                'bank_account_holder' => 'PT Dua',
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame($second->id, $procurement->partner_candidate_id);
        // The contract documents read the picked partner.
        $this->assertSame('PT Dua', $procurement->partner_name);
        $this->assertSame('Direktur 1', $procurement->partner_director_name);
        $this->assertSame('001/SPH/2026', $procurement->quotation_number);
        $this->assertTrue($step->checklistItem->input_kind->isFilledOn($procurement));

        // The picked one cannot be removed; another can.
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->delete(route('procurements.partner-candidates.destroy', [$procurement, $second]))
            ->assertRedirect();
        $this->assertModelExists($second);

        // Correcting the picked one follows on the procurement.
        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->put(route('procurements.partner-candidates.update', [$procurement, $second]), ['name' => 'PT Dua Perkasa'])
            ->assertSessionHasNoErrors();
        $this->assertSame('PT Dua Perkasa', $procurement->refresh()->partner_name);
    }

    public function test_a_candidate_of_another_procurement_cannot_be_picked(): void
    {
        $procurement = $this->onFormat('SPK', ['planning_approval_state' => PlanningApprovalState::Disetujui]);
        $other = Procurement::factory()->create();
        $foreign = $other->partnerCandidates()->create(['name' => 'PT Lain']);

        $this->actingAs($this->executor)
            ->put(route('procurements.checklists.input', [$procurement, $this->pelaksanaStep($procurement)]), [
                'partner_candidate_id' => $foreign->id,
                'bank_account_number' => '123',
                'bank_name' => 'BRI',
                'bank_account_holder' => 'PT Lain',
            ])
            ->assertSessionHasErrors('partner_candidate_id');
    }

    public function test_an_sppl_takes_one_partner_who_is_the_executor_at_once(): void
    {
        $procurement = $this->onFormat('SPPL');

        $this->actingAs($this->planner)
            ->post(route('procurements.partner-candidates.store', $procurement), ['name' => 'CV Tunjuk Langsung'])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame('CV Tunjuk Langsung', $procurement->partner_name);
        $this->assertNotNull($procurement->partner_candidate_id);

        // A second one is refused.
        $this->actingAs($this->planner)
            ->post(route('procurements.partner-candidates.store', $procurement), ['name' => 'CV Kedua']);

        $this->assertCount(1, $procurement->partnerCandidates()->get());

        $this->actingAs($this->planner)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('directAppointment', true)
                ->has('partnerCandidates', 1)
                ->where('partnerCandidates.0.is_selected', true));
    }

    public function test_only_who_fills_the_planning_lists_candidates(): void
    {
        $procurement = $this->onFormat('SPK');

        $this->actingAs($this->executor)
            ->post(route('procurements.partner-candidates.store', $procurement), ['name' => 'PT Liar'])
            ->assertForbidden();

        $this->actingAs($this->planner)
            ->post(route('procurements.partner-candidates.store', $procurement), [])
            ->assertSessionHasErrors('name');

        $this->assertCount(0, $procurement->partnerCandidates()->get());
    }

    /**
     * A procurement on a seeded format, planned by the planner and executed
     * by the executor.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function onFormat(string $code, array $attributes = []): Procurement
    {
        $procurement = Procurement::factory()->plannedBy($this->planner)->executedBy($this->executor)->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $code)->value('id'),
            'procurement_method_id' => null,
            ...$attributes,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement->refresh());

        return $procurement;
    }

    protected function pelaksanaStep(Procurement $procurement): ProcurementChecklist
    {
        return $procurement->checklists()
            ->where('stage', ProcurementStage::Pelaksanaan->value)
            ->whereHas('checklistItem', fn ($query) => $query->where('name', 'Pelaksana'))
            ->with('checklistItem')
            ->firstOrFail();
    }
}
