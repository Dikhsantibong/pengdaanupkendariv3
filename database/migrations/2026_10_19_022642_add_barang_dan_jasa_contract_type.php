<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The planning PIC picks the category from Barang, Jasa, KHS and Barang dan
 * Jasa. The last one is added where the jenis kontrak data is already in
 * place; it prints the general SPK/PJ format.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('contract_types')->exists() || DB::table('contract_types')->where('code', 'barang-jasa')->exists()) {
            return;
        }

        DB::table('contract_types')->insert([
            'code' => 'barang-jasa',
            'name' => 'Barang dan Jasa',
            'description' => 'Pengadaan barang sekaligus jasa.',
            'work_category' => null,
            'sort_order' => (int) DB::table('contract_types')->max('sort_order') + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('contract_types')->where('code', 'barang-jasa')->delete();
    }
};
