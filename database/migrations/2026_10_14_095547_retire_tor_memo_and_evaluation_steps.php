<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire three checklist steps:
 *
 * - "Nota Dinas Usulan" and "TOR / KAK" in planning: both documents are
 *   filed on the proposer's tab of the procurement, and are still required
 *   before planning is submitted.
 * - "Evaluasi Dokumen" in execution, no longer a step of its own.
 *
 * The steps are switched off and taken off every procurement's checklist.
 * The documents already filed are untouched, and the steps stay in the
 * master data so an administrator can switch them back on.
 */
return new class extends Migration
{
    /**
     * The retired steps, by stage.
     *
     * @var array<string, array<int, string>>
     */
    private const STEPS = [
        'perencanaan' => ['Nota Dinas Usulan', 'TOR / KAK'],
        'pelaksanaan' => ['Evaluasi Dokumen'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::STEPS as $stage => $names) {
            $itemIds = DB::table('checklist_items')
                ->where('stage', $stage)
                ->whereIn('name', $names)
                ->pluck('id');

            if ($itemIds->isEmpty()) {
                continue;
            }

            DB::table('checklist_items')->whereIn('id', $itemIds)->update(['is_active' => false]);
            DB::table('procurement_checklists')->whereIn('checklist_item_id', $itemIds)->delete();
        }
    }

    /**
     * Reverse the migrations.
     *
     * The steps come back on each procurement the next time it is saved.
     */
    public function down(): void
    {
        foreach (self::STEPS as $stage => $names) {
            DB::table('checklist_items')
                ->where('stage', $stage)
                ->whereIn('name', $names)
                ->update(['is_active' => true]);
        }
    }
};
