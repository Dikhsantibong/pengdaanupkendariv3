<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A checklist item can be switched off for particular contract formats (and,
 * for older callers, methods), so an SPPL — let by Surat Pesanan — does not
 * carry the tender paperwork steps. The method follows the format, so the
 * seeded exclusions are set on the SPPL format.
 */
class ChecklistMethodExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_surat_pesanan_skips_the_seeded_planning_steps(): void
    {
        $this->seed(MasterDataSeeder::class);

        $procurement = $this->makeProcurementOn('SPPL');

        // The SPPL brings its method along.
        $this->assertSame('surat-pesanan', $procurement->procurementMethod?->code);

        $names = $procurement->checklists()
            ->with('checklistItem')
            ->get()
            ->map(fn ($checklist): string => $checklist->checklistItem->name);

        foreach ([
            'RKS (Rencana Kerja dan Syarat)',
            'Inisiasi SMART SCM',
            'PR / RO',
            'UPB',
            'HPE (Harga Perkiraan Engineer)',
        ] as $skipped) {
            $this->assertNotContains($skipped, $names, "[{$skipped}] seharusnya dilewati Surat Pesanan.");
        }

        // Steps the format does not skip are still there; the RAB is folded
        // into the SPPL's Penawaran.
        $this->assertNotContains('RAB (Rencana Anggaran Biaya)', $names);
        $this->assertContains('Penawaran', $names);

        // The Nota Dinas Usulan and TOR are filed on the proposer's tab.
        $this->assertNotContains('Nota Dinas Usulan', $names);
        $this->assertNotContains('TOR / KAK', $names);
    }

    public function test_other_formats_keep_the_full_checklist(): void
    {
        $this->seed(MasterDataSeeder::class);

        $names = $this->makeProcurementOn('PJ')->checklists()
            ->with('checklistItem')
            ->get()
            ->map(fn ($checklist): string => $checklist->checklistItem->name);

        $this->assertContains('RKS (Rencana Kerja dan Syarat)', $names);
        $this->assertContains('Inisiasi SMART SCM', $names);
        $this->assertContains('UPB', $names);
    }

    public function test_a_procurement_without_a_method_keeps_every_item(): void
    {
        $this->seed(MasterDataSeeder::class);

        $expected = ChecklistItem::query()->active()->count();

        $procurement = $this->makeProcurement(null);

        $this->assertSame($expected, $procurement->checklists()->count());
    }

    public function test_switching_format_adds_and_drops_the_right_steps(): void
    {
        $this->seed(MasterDataSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();
        $sppl = ContractNumberFormat::query()->where('code', 'SPPL')->firstOrFail();

        $procurement = $this->makeProcurementOn('PJ');
        $rksItem = ChecklistItem::query()->where('name', 'RKS (Rencana Kerja dan Syarat)')->firstOrFail();

        $this->assertTrue(
            $procurement->checklists()->where('checklist_item_id', $rksItem->id)->exists(),
        );

        $this->actingAs($teamLeader)
            ->put(route('procurements.update', $procurement), [
                ...$this->payloadFrom($procurement),
                'contract_number_format_id' => $sppl->id,
            ])
            ->assertRedirect();

        $this->assertFalse(
            $procurement->checklists()->where('checklist_item_id', $rksItem->id)->exists(),
        );

        $this->actingAs($teamLeader)
            ->put(route('procurements.update', $procurement), [
                ...$this->payloadFrom($procurement),
                'contract_number_format_id' => $pj->id,
                'contract_type_id' => ContractType::query()->firstOrFail()->id,
            ])
            ->assertRedirect();

        $this->assertTrue(
            $procurement->checklists()->where('checklist_item_id', $rksItem->id)->exists(),
        );
    }

    public function test_a_completed_step_survives_a_format_change(): void
    {
        $this->seed(MasterDataSeeder::class);

        $procurement = $this->makeProcurementOn('PJ');
        $rksItem = ChecklistItem::query()->where('name', 'RKS (Rencana Kerja dan Syarat)')->firstOrFail();

        $procurement->checklists()
            ->where('checklist_item_id', $rksItem->id)
            ->update(['is_completed' => true, 'completed_at' => now()]);

        $procurement->contract_number_format_id = ContractNumberFormat::query()->where('code', 'SPPL')->value('id');
        $procurement->save();

        app(ProcurementService::class)->syncChecklists($procurement);

        $this->assertTrue(
            $procurement->checklists()->where('checklist_item_id', $rksItem->id)->exists(),
            'Langkah yang sudah selesai adalah riwayat dan tidak boleh dihapus.',
        );
    }

    public function test_an_administrator_can_edit_the_exclusions(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();
        $item = ChecklistItem::query()->where('name', 'CSMS (Sertifikat)')->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.checklist-items.update', $item), [
                'stage' => ProcurementStage::Perencanaan->value,
                'name' => $item->name,
                'description' => $item->description,
                'is_optional' => $item->is_optional,
                'sort_order' => $item->sort_order,
                'is_active' => true,
                'excluded_procurement_method_ids' => [$tender->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$tender->id],
            $item->refresh()->excludedProcurementMethods->pluck('id')->all(),
        );

        $names = $this->makeProcurement($tender)->checklists()
            ->with('checklistItem')
            ->get()
            ->map(fn ($checklist): string => $checklist->checklistItem->name);

        $this->assertNotContains('CSMS', $names);
    }

    /**
     * Create a procurement on a seeded contract format, with its checklist.
     */
    protected function makeProcurementOn(string $format): Procurement
    {
        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $format)->value('id'),
            'procurement_method_id' => null,
        ]);

        $procurement->checklists()->delete();

        app(ProcurementService::class)->syncChecklists($procurement->refresh());

        return $procurement;
    }

    /**
     * Create a procurement using the service so its checklist is built.
     */
    protected function makeProcurement(?ProcurementMethod $method): Procurement
    {
        $procurement = Procurement::factory()->create([
            'procurement_method_id' => $method?->id,
        ]);

        $procurement->checklists()->delete();

        app(ProcurementService::class)->syncChecklists($procurement);

        return $procurement;
    }

    /**
     * The update payload of an existing procurement.
     *
     * @return array<string, mixed>
     */
    protected function payloadFrom(Procurement $procurement): array
    {
        $procurement->refresh();

        return [
            'name' => $procurement->name,
            'work_director_id' => $procurement->work_director_id,
            'target_unit_id' => $procurement->target_unit_id,
            'budget_source_id' => $procurement->budget_source_id,
            'pr_ro_number_id' => $procurement->pr_ro_number_id,
            'prk_number' => $procurement->prk_number,
            'hpe_value' => $procurement->hpe_value,
            'progress_status_id' => $procurement->progress_status_id,
            'target_completion_date' => $procurement->target_completion_date?->toDateString(),
            'notes' => $procurement->notes,
        ];
    }
}
