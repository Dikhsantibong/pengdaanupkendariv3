<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire the bare "Checklist Perencanaan" tick at the top of the planning
 * checklist. It carried no document or data, only repeated the stage name,
 * so it is switched off and its ticks are taken off every procurement. The
 * item itself stays in the master data and can be switched back on there.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $itemIds = DB::table('checklist_items')
            ->where('stage', 'perencanaan')
            ->where('name', 'Checklist Perencanaan')
            ->pluck('id');

        if ($itemIds->isEmpty()) {
            return;
        }

        DB::table('checklist_items')->whereIn('id', $itemIds)->update(['is_active' => false]);
        DB::table('procurement_checklists')->whereIn('checklist_item_id', $itemIds)->delete();
    }

    /**
     * Reverse the migrations.
     *
     * The step comes back on each procurement the next time it is saved.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->where('stage', 'perencanaan')
            ->where('name', 'Checklist Perencanaan')
            ->update(['is_active' => true]);
    }
};
