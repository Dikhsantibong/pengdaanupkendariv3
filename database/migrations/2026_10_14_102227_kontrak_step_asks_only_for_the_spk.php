<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "Kontrak" execution step (SPK and PJ) asks only for the SPK — the
 * document carrying the contract articles. The Lampiran SPK and the BA
 * Negosiasi are no longer required on it. Documents already drawn from them
 * are kept, and they can be linked again on Data Master → Item Checklist.
 */
return new class extends Migration
{
    /**
     * The documents taken off the step.
     *
     * @var array<int, string>
     */
    private const REMOVED = ['lampiran-spk', 'ba-negosiasi'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $step = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Kontrak')
            ->value('id');

        if ($step === null) {
            return;
        }

        DB::table('checklist_item_document_type')
            ->where('checklist_item_id', $step)
            ->whereIn('document_type_id', DB::table('document_types')->whereIn('code', self::REMOVED)->select('id'))
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $step = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Kontrak')
            ->value('id');

        if ($step === null) {
            return;
        }

        foreach (self::REMOVED as $order => $code) {
            $typeId = DB::table('document_types')->where('code', $code)->value('id');

            if ($typeId !== null) {
                DB::table('checklist_item_document_type')->insertOrIgnore([
                    'checklist_item_id' => $step,
                    'document_type_id' => $typeId,
                    'sort_order' => $order + 2,
                    'is_alternative' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
