<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;

/**
 * The HPS is the SMT-FM-DAN-01.01 form alone, on one A4 portrait sheet: the
 * Harga Satuan and Jumlah Harga calculation sheets are dropped, the proposal
 * rests on the PRK, and the proposer signs as Officer Pengadaan.
 *
 * Documents already drawn keep their own text; only new ones use this.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(DocumentTemplateLibrary::class)->sync(keys: ['standar.penyusunan-hps'], force: true);
    }

    /**
     * Reverse the migrations.
     *
     * The earlier wording is not kept in the library, so nothing is undone.
     */
    public function down(): void
    {
        //
    }
};
