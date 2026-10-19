<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The TOR is signed by four: drawn up by the user, checked by the Asman of
 * the user's division (left) and the Asman Engineering (right), approved by
 * the Manager. The names are filled in from the data.
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
