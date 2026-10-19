<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The contract number and the barang/jasa category are no longer asked when
 * a job is registered: the executor gives them with the contract itself, on
 * the Kontrak step (SPK/PJ) or the SPPL's Lampiran Surat Pesanan step. Each
 * is a step of its own formats only, so every format asks once. Steps that
 * already ask for something else are left alone.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->whereIn('name', ['Kontrak', 'Lampiran Surat Pesanan'])
            ->whereNull('procurement_id')
            ->whereNull('input_kind')
            ->update(['input_kind' => 'contract_number', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->where('input_kind', 'contract_number')
            ->update(['input_kind' => null]);
    }
};
