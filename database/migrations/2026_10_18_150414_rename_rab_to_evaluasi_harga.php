<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The RAB planning step becomes the Evaluasi Harga: the calon mitra's prices
 * compared item by item against the HPE, generated from the Rincian Item
 * instead of uploaded. The document type keeps its code (rab).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('checklist_items')
            ->where('stage', 'perencanaan')
            ->where('name', 'RAB (Rencana Anggaran Biaya)')
            ->update(['name' => 'Evaluasi Harga', 'updated_at' => now()]);

        DB::table('document_types')
            ->where('code', 'rab')
            ->update(['name' => 'Evaluasi Harga', 'upload_only' => false, 'updated_at' => now()]);

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['umum.rab'], force: true);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->where('stage', 'perencanaan')
            ->where('name', 'Evaluasi Harga')
            ->update(['name' => 'RAB (Rencana Anggaran Biaya)', 'updated_at' => now()]);

        DB::table('document_types')
            ->where('code', 'rab')
            ->update(['name' => 'RAB (Rencana Anggaran Biaya)', 'upload_only' => true, 'updated_at' => now()]);
    }
};
