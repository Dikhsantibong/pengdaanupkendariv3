<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A checklist item can be switched off for particular procurement methods, so
 * a Surat Pesanan does not carry the tender paperwork steps.
 */
class ChecklistMethodExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_surat_pesanan_skips_the_seeded_planning_steps(): void
    {
        $this->seed(MasterDataSeeder::class);

        $suratPesanan = ProcurementMethod::query()->where('code', 'surat-pesanan')->firstOrFail();

        $procurement = $this->makeProcurement($suratPesanan);

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

        $this->assertContains('Nota Dinas Usulan', $names);
        $this->assertContains('TOR (Term of Reference)', $names);
    }

    public function test_other_methods_keep_the_full_checklist(): void
    {
        $this->seed(MasterDataSeeder::class);

        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();

        $names = $this->makeProcurement($tender)->checklists()
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

    public function test_switching_method_adds_and_drops_the_right_steps(): void
    {
        $this->seed(MasterDataSeeder::class);

        $teamLeader = User::factory()->teamLeader()->create();
        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();
        $suratPesanan = ProcurementMethod::query()->where('code', 'surat-pesanan')->firstOrFail();

        $procurement = $this->makeProcurement($tender);
        $rksItem = ChecklistItem::query()->where('name', 'RKS (Rencana Kerja dan Syarat)')->firstOrFail();

        $this->assertTrue(
            $procurement->checklists()->where('checklist_item_id', $rksItem->id)->exists(),
        );

        $this->actingAs($teamLeader)
            ->put(route('procurements.update', $procurement), [
                ...$this->payloadFrom($procurement),
                'procurement_method_id' => $suratPesanan->id,
            ])
            ->assertRedirect();

        $this->assertFalse(
            $procurement->checklists()->where('checklist_item_id', $rksItem->id)->exists(),
        );

        $this->actingAs($teamLeader)
            ->put(route('procurements.update', $procurement), [
                ...$this->payloadFrom($procurement),
                'procurement_method_id' => $tender->id,
            ])
            ->assertRedirect();

        $this->assertTrue(
            $procurement->checklists()->where('checklist_item_id', $rksItem->id)->exists(),
        );
    }

    public function test_a_completed_step_survives_a_method_change(): void
    {
        $this->seed(MasterDataSeeder::class);

        $tender = ProcurementMethod::query()->where('code', 'tender')->firstOrFail();
        $suratPesanan = ProcurementMethod::query()->where('code', 'surat-pesanan')->firstOrFail();

        $procurement = $this->makeProcurement($tender);
        $rksItem = ChecklistItem::query()->where('name', 'RKS (Rencana Kerja dan Syarat)')->firstOrFail();

        $procurement->checklists()
            ->where('checklist_item_id', $rksItem->id)
            ->update(['is_completed' => true, 'completed_at' => now()]);

        $procurement->procurement_method_id = $suratPesanan->id;
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
        $item = ChecklistItem::query()->where('name', 'CSMS')->firstOrFail();

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
