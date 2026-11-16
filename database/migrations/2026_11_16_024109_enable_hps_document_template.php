<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('document_types')
            ->where('code', 'penyusunan-hps')
            ->update(['upload_only' => false]);

        app(DocumentTemplateLibrary::class)->sync(keys: ['standar.penyusunan-hps'], force: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('document_types')
            ->where('code', 'penyusunan-hps')
            ->update(['upload_only' => true]);
    }
};
