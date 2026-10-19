<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The TOR's signatures follow the format: SPK and PJ are signed by four (the
 * user, the Asman of the user's division, the Asman Engineering and the
 * Manager), an SPPL by three (the Asman on the left, the user on the right,
 * the Manager below). The template draws them through {{pengesahan_tor}}.
 *
 * Put in the installed template; TORs nobody has edited follow it the next
 * time they are opened, edited ones keep their own wording.
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
     */
    public function down(): void
    {
        //
    }
};
