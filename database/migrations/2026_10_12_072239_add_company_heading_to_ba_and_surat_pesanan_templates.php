<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Formerly: company name and address added to the BA Negosiasi SPPL and Surat Pesanan letterheads.
 *
 * Superseded by the document template library: default template wording now
 * lives in resources/document-templates and is installed or refreshed by
 * DocumentTemplateLibrary (`php artisan templates:sync`). This migration
 * used to write template text from seeder code; databases that already ran
 * it keep its result, and a fresh install gets the current wording from the
 * library instead. The original version is in the git history.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
