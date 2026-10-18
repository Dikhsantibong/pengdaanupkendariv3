<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The TOR is approved, at the bottom of its signature page, by the unit's
 * Manager (Data Master -> Manager). The other signatories are typed in by
 * hand in the document editor.
 *
 * Installed as a new version of the default TOR template, unless an
 * administrator has already customised it; documents drawn before keep
 * their own wording.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['umum.tor']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The previous version stays in the template history.
     */
    public function down(): void
    {
        //
    }
};
