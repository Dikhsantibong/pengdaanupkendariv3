<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "Rentang Waktu" step of SPK / PJ was a bare tick, while its SPPL
 * counterpart ("Rentang Waktu Pelaksanaan") asks for the start date and the
 * number of days. Both now ask for them: the same procurement fields
 * (execution_start_date, execution_duration_days) that the documents read.
 *
 * Only a step still without an input is given one, so a choice made on the
 * Data Master screen is kept. Steps already ticked stay ticked.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Rentang Waktu')
            ->whereNull('input_kind')
            ->update(['input_kind' => 'contract_period', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Rentang Waktu')
            ->where('input_kind', 'contract_period')
            ->update(['input_kind' => null, 'updated_at' => now()]);
    }
};
