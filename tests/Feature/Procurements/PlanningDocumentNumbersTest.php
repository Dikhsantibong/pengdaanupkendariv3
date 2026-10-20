<?php

namespace Tests\Feature\Procurements;

use App\Enums\ChecklistInput;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The RKS, HPE and UPB planning steps each ask for the number of their
 * document, which the documents print.
 */
class PlanningDocumentNumbersTest extends TestCase
{
    use RefreshDatabase;

    protected User $planner;

    protected Procurement $procurement;

    /**
     * Each step with its input and the field it fills.
     *
     * @var array<string, array{0: ChecklistInput, 1: string}>
     */
    protected const STEPS = [
        'RKS (Rencana Kerja dan Syarat)' => [ChecklistInput::RksNumber, 'rks_number'],
        'HPE (Harga Perkiraan Engineer)' => [ChecklistInput::HpeNumber, 'hpe_number'],
        'UPB' => [ChecklistInput::UpbNumber, 'upb_number'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);

        $this->planner = User::factory()->planner()->create();
        $this->procurement = Procurement::factory()->plannedBy($this->planner)->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'procurement_method_id' => null,
            'number' => 'KDD017.SPK/612/UPKD/2026',
            'proposal_memo_number' => 'ND-001/UPKD/2026',
        ]);

        app(ProcurementService::class)->syncChecklists($this->procurement->refresh());
    }

    public function test_each_step_asks_for_its_document_number(): void
    {
        foreach (self::STEPS as $name => [$kind]) {
            $this->assertSame($kind, $this->step($name)->checklistItem->input_kind, $name);
        }

        $this->actingAs($this->planner)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page->where('checklists.perencanaan', function (Collection $rows): bool {
                $kinds = $rows->pluck('input.kind')->filter()->values()->all();

                $this->assertContains('rks_number', $kinds);
                $this->assertContains('hpe_number', $kinds);
                $this->assertContains('upb_number', $kinds);

                return true;
            }));
    }

    public function test_the_planner_fills_in_the_numbers(): void
    {
        foreach (self::STEPS as $name => [$kind, $field]) {
            $step = $this->step($name);

            $this->actingAs($this->planner)
                ->put(route('procurements.checklists.input', [$this->procurement, $step]), [$field => ''])
                ->assertSessionHasErrors($field);

            $this->actingAs($this->planner)
                ->put(route('procurements.checklists.input', [$this->procurement, $step]), [$field => "  {$kind->label()} 001  "])
                ->assertSessionHasNoErrors();

            $this->assertSame("{$kind->label()} 001", $this->procurement->refresh()->getAttribute($field));
            $this->assertTrue($kind->isFilledOn($this->procurement));
        }
    }

    public function test_a_step_without_its_number_holds_up_the_planning(): void
    {
        $step = $this->step('RKS (Rencana Kerja dan Syarat)');
        $step->update(['is_completed' => true]);

        $pending = fn (): array => $this->procurement->refresh()->load('checklists.checklistItem')
            ->pendingRequiredPlanningChecklists()
            ->map(fn (ProcurementChecklist $row): string => $row->checklistItem->name)
            ->all();

        $this->assertContains('RKS (Rencana Kerja dan Syarat)', $pending());

        $this->procurement->update(['rks_number' => '017.RKS-SPK/612/UPKD/2026']);

        $this->assertNotContains('RKS (Rencana Kerja dan Syarat)', $pending());
    }

    public function test_the_documents_print_the_numbers_given(): void
    {
        $generator = app(DocumentGenerator::class);

        // Until given, the numbers the documents printed before.
        $values = $generator->placeholderValues($this->procurement);
        $this->assertSame('017.RKS-SPK/612/UPKD/2026', $values['nomor_rks']);
        $this->assertSame('ND-001/UPKD/2026-HPE(A)', $values['nomor_hpe']);
        $this->assertSame('ND-001/UPKD/2026', $values['nomor_upb']);

        $this->procurement->update(['rks_number' => 'RKS-9', 'hpe_number' => 'HPE-9', 'upb_number' => 'UPB-9']);

        $values = $generator->placeholderValues($this->procurement->refresh());
        $this->assertSame('RKS-9', $values['nomor_rks']);
        $this->assertSame('HPE-9', $values['nomor_hpe']);
        $this->assertSame('UPB-9', $values['nomor_upb']);
    }

    /**
     * A planning step of the procurement, by name.
     */
    protected function step(string $name): ProcurementChecklist
    {
        return $this->procurement->checklists()
            ->with('checklistItem')
            ->whereHas('checklistItem', fn ($query) => $query->where('name', $name))
            ->firstOrFail();
    }
}
