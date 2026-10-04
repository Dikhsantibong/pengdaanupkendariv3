<?php

namespace Tests\Feature\Procurements;

use App\Models\BudgetSource;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\UnitManager;
use App\Models\User;
use App\Models\WorkDirector;
use App\Services\DocumentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Usulan Pekerjaan" fields: separate reference numbers typed by hand, the
 * partner carrying the work out, the values before and after negotiation, and
 * a procurement that may serve several target units.
 */
class ProposalFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_procurement_is_registered_with_the_proposal_fields(): void
    {
        [$first, $second] = TargetUnit::factory()->count(2)->create();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'target_unit_ids' => [$first->id, $second->id],
                'partner_name' => 'PT Konstruksi Indonesia',
                'prk_number' => 'PRK-2026-01',
                'proposal_memo_number' => 'ND-021/USL/2026',
                'proposal_memo_date' => '2026-10-01',
                'icc_memo_number' => 'ND-014/ICC/2026',
                'icc_memo_date' => '2026-10-02',
                'pr_po_number' => 'PR-778899',
                'coa_number' => '5110100000',
                'wo_number' => 'WO-2026-0042',
                'quotation_number' => '012/PMR/X/2026',
                'quotation_date' => '2026-10-03',
                'hpe_value' => 250_000_000,
                'value_after_negotiation' => 230_000_000,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $procurement = Procurement::query()->firstOrFail();

        $this->assertSame('PT Konstruksi Indonesia', $procurement->partner_name);
        $this->assertSame('PRK-2026-01', $procurement->prk_number);
        $this->assertSame('ND-021/USL/2026', $procurement->proposal_memo_number);
        $this->assertSame('2026-10-01', $procurement->proposal_memo_date?->toDateString());
        $this->assertSame('ND-014/ICC/2026', $procurement->icc_memo_number);
        $this->assertSame('2026-10-02', $procurement->icc_memo_date?->toDateString());
        $this->assertSame('PR-778899', $procurement->pr_po_number);
        $this->assertSame('5110100000', $procurement->coa_number);
        $this->assertSame('WO-2026-0042', $procurement->wo_number);
        $this->assertSame('012/PMR/X/2026', $procurement->quotation_number);
        $this->assertSame('2026-10-03', $procurement->quotation_date?->toDateString());
        $this->assertSame('230000000.00', $procurement->value_after_negotiation);

        // Both units are kept in the order chosen; the first stays the single
        // unit every older query reads.
        $this->assertSame([$first->id, $second->id], $procurement->targetUnits->pluck('id')->all());
        $this->assertSame($first->id, $procurement->target_unit_id);
    }

    public function test_the_reference_numbers_and_the_negotiated_value_are_optional(): void
    {
        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'target_unit_ids' => [TargetUnit::factory()->create()->id],
                'pr_po_number' => null,
                'value_after_negotiation' => null,
            ])
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->firstOrFail();

        $this->assertNull($procurement->pr_po_number);
        $this->assertNull($procurement->value_after_negotiation);
    }

    public function test_at_least_one_unit_is_required(): void
    {
        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'target_unit_ids' => [],
            ])
            ->assertSessionHasErrors('target_unit_ids');

        $this->assertDatabaseCount('procurements', 0);
    }

    public function test_a_single_unit_from_an_older_caller_is_still_accepted(): void
    {
        $unit = TargetUnit::factory()->create();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'target_unit_id' => $unit->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [$unit->id],
            Procurement::query()->firstOrFail()->targetUnits->pluck('id')->all(),
        );
    }

    public function test_editing_replaces_the_unit_list(): void
    {
        $procurement = Procurement::factory()->create();
        [$a, $b] = TargetUnit::factory()->count(2)->create();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->put(route('procurements.update', $procurement), [
                'name' => $procurement->name,
                'work_director_id' => $procurement->work_director_id,
                'target_unit_ids' => [$b->id, $a->id],
                'procurement_method_id' => $procurement->procurement_method_id,
                'budget_source_id' => $procurement->budget_source_id,
                'hpe_value' => 1_000_000,
                'progress_status_id' => $procurement->progress_status_id,
                'pr_po_number' => 'PO-123',
                'proposal_memo_number' => 'ND-EDIT-USL',
                'proposal_memo_date' => '2026-10-10',
                'icc_memo_number' => 'ND-EDIT-MGR',
                'icc_memo_date' => '2026-10-11',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $procurement->refresh();

        $this->assertSame([$b->id, $a->id], $procurement->targetUnits->pluck('id')->all());
        $this->assertSame($b->id, $procurement->target_unit_id);
        $this->assertSame('PO-123', $procurement->pr_po_number);
        $this->assertSame('ND-EDIT-USL', $procurement->proposal_memo_number);
        $this->assertSame('2026-10-10', $procurement->proposal_memo_date?->toDateString());
        $this->assertSame('ND-EDIT-MGR', $procurement->icc_memo_number);
        $this->assertSame('2026-10-11', $procurement->icc_memo_date?->toDateString());
    }

    public function test_the_list_filter_finds_a_procurement_by_any_of_its_units(): void
    {
        $administrator = User::factory()->administrator()->create();
        $procurement = Procurement::factory()->create();
        $secondary = TargetUnit::factory()->create();

        $procurement->targetUnits()->attach($secondary->id, ['sort_order' => 2]);

        $this->actingAs($administrator)
            ->get(route('procurements.index', ['target_unit_id' => $secondary->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $procurement->id)
                ->where('procurements.data.0.target_units', [
                    $procurement->targetUnit->name,
                    $secondary->name,
                ]));
    }

    public function test_documents_name_every_unit_and_the_typed_pr_po_number(): void
    {
        $procurement = Procurement::factory()->create(['pr_po_number' => 'PR-555']);
        $secondary = TargetUnit::factory()->create();
        $procurement->targetUnits()->attach($secondary->id, ['sort_order' => 2]);

        $values = app(DocumentGenerator::class)->placeholderValues($procurement->fresh());

        $this->assertSame(
            $procurement->targetUnit->name.', '.$secondary->name,
            $values['unit_tujuan'],
        );

        // Templates written for the old PR/RO key keep filling in.
        $this->assertSame('PR-555', $values['nomor_pr_ro']);
        $this->assertSame('PR-555', $values['nomor_pr_po']);
    }

    public function test_documents_name_manager_and_quotation_details(): void
    {
        UnitManager::factory()->create([
            'name' => 'MUHAMMAD RUSLI',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $procurement = Procurement::factory()->create([
            'quotation_number' => '055/SP-DIR/2026',
            'quotation_date' => '2026-10-04',
            'icc_memo_number' => 'ND-099/MGR/2026',
            'icc_memo_date' => '2026-10-03',
        ]);

        $values = app(DocumentGenerator::class)->placeholderValues($procurement->fresh());

        $this->assertSame('MUHAMMAD RUSLI', $values['nama_manager']);
        $this->assertSame('055/SP-DIR/2026', $values['nomor_surat_penawaran']);
        $this->assertSame('ND-099/MGR/2026', $values['nomor_nota_dinas_manager']);
        $this->assertSame('03 Oktober 2026', $values['tanggal_nota_dinas_manager']);
    }

    public function test_the_pr_ro_master_data_screen_is_gone(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get('/master-data/pr-ro-numbers')
            ->assertNotFound();
    }

    /**
     * The mandatory fields of a procurement.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'name' => 'Pemeliharaan Mesin Unit Poasia',
            'work_director_id' => WorkDirector::factory()->create()->id,
            'procurement_method_id' => ProcurementMethod::factory()->create()->id,
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'hpe_value' => 192_000_000,
            'progress_status_id' => ProgressStatus::factory()->asDefault()->create()->id,
        ];
    }
}
