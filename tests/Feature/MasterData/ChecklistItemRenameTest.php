<?php

namespace Tests\Feature\MasterData;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reseeding an installation that still carries the previous wording must
 * rename the checklist item, not add a second one beside it.
 */
class ChecklistItemRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_states_the_current_wording(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertTrue(
            ChecklistItem::query()
                ->forStage(ProcurementStage::Pelaksanaan)
                ->where('name', 'Proses SMART SCM')
                ->exists(),
        );

        $this->assertFalse(
            ChecklistItem::query()->where('name', 'Progress Pengadaan')->exists(),
        );
        $this->assertFalse(
            ChecklistItem::query()->where('name', 'Smart SCM')->exists(),
        );
    }

    public function test_reseeding_renames_in_place_and_keeps_the_ticks(): void
    {
        $this->seed(MasterDataSeeder::class);

        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Pelaksanaan)
            ->where('name', 'Proses SMART SCM')
            ->firstOrFail();

        $procurement = Procurement::factory()->create();
        $checklist = ProcurementChecklist::query()->create([
            'procurement_id' => $procurement->id,
            'checklist_item_id' => $item->id,
            'stage' => ProcurementStage::Pelaksanaan,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Put the installation back on the old wording, then reseed.
        $item->forceFill(['name' => 'Progress Pengadaan'])->save();

        $this->seed(MasterDataSeeder::class);

        $this->assertSame(
            1,
            ChecklistItem::query()
                ->forStage(ProcurementStage::Pelaksanaan)
                ->whereIn('name', ['Proses SMART SCM', 'Progress Pengadaan'])
                ->count(),
            'Reseeding harus mengganti nama, bukan menambah item baru.',
        );

        $this->assertSame('Proses SMART SCM', $item->refresh()->name);
        $this->assertSame($item->id, $checklist->refresh()->checklist_item_id);
        $this->assertTrue($checklist->is_completed);
    }
}
