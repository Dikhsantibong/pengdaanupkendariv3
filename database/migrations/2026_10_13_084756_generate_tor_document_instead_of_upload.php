<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The TOR is generated from its template when work is proposed, rather than
 * prepared outside the system and uploaded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('document_types')->where('code', 'tor')->update(['upload_only' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('document_types')->where('code', 'tor')->update(['upload_only' => true]);
    }
};
