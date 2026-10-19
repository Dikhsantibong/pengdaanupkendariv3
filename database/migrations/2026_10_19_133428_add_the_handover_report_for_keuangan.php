<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Berita Acara Serah Terima Pekerjaan the partner writes for
     * Keuangan, downloaded from the BAST step as Word and uploaded back once
     * signed with the Manager.
     */
    public function up(): void
    {
        if (! DB::table('document_types')->exists()) {
            return;
        }

        if (! DB::table('document_types')->where('code', 'berita-acara-serah-terima')->exists()) {
            DB::table('document_types')->insert([
                'code' => 'berita-acara-serah-terima',
                'name' => 'Berita Acara Serah Terima Pekerjaan',
                'stage' => 'keuangan',
                'sort_order' => (int) DB::table('document_types')->max('sort_order') + 1,
                'upload_only' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['keuangan.berita-acara-serah-terima']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $type = DB::table('document_types')->where('code', 'berita-acara-serah-terima')->value('id');

        if ($type !== null) {
            DB::table('document_templates')->where('document_type_id', $type)->delete();
            DB::table('document_types')->where('id', $type)->delete();
        }
    }
};
