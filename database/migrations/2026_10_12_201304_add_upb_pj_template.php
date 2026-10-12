<?php

use Database\Seeders\PjTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Install the Usulan Pengadaan Barang / Jasa used by PJ contracts. Only this
 * template is touched; the other PJ templates keep any edits made to them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $seeder = new PjTemplateSeeder;

        $seeder->seedTemplate('upb', null, 'UPB PJ (Usulan Pengadaan Barang / Jasa)', $seeder->upb());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
