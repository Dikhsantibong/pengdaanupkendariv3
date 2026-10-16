<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The procurement method follows the contract format now, so "skipped by
 * method" becomes "skipped by format": every step a method skipped is
 * skipped by the formats let under that method (Surat Pesanan → SPPL).
 * The method exclusions are then cleared so the format list is the only
 * place a step is switched off, with nothing hidden behind it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $exclusions = DB::table('checklist_item_method_exclusions')->get();

            foreach ($exclusions as $exclusion) {
                $formatIds = DB::table('contract_number_formats')
                    ->where('procurement_method_id', $exclusion->procurement_method_id)
                    ->pluck('id');

                foreach ($formatIds as $formatId) {
                    DB::table('checklist_item_format_exclusions')->insertOrIgnore([
                        'checklist_item_id' => $exclusion->checklist_item_id,
                        'contract_number_format_id' => $formatId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('checklist_item_method_exclusions')->delete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * The method exclusions are rebuilt from the formats that name a method;
     * the format exclusions stay, as they apply the same steps.
     */
    public function down(): void
    {
        $formats = DB::table('contract_number_formats')->whereNotNull('procurement_method_id')->pluck('procurement_method_id', 'id');

        $exclusions = DB::table('checklist_item_format_exclusions')
            ->whereIn('contract_number_format_id', $formats->keys())
            ->get();

        foreach ($exclusions as $exclusion) {
            DB::table('checklist_item_method_exclusions')->insertOrIgnore([
                'checklist_item_id' => $exclusion->checklist_item_id,
                'procurement_method_id' => $formats[$exclusion->contract_number_format_id],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
