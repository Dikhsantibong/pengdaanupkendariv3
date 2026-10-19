<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every date on the TOR (cover, program identity, signature page and the
 * cover's year) is its Tanggal Terbit, and the signature page is placed in
 * Kendari rather than the target units.
 *
 * Put in the installed template, replacing one an administrator changed;
 * `php artisan tor:perbaiki` brings the TORs already drawn along.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['umum.tor'], force: true);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
