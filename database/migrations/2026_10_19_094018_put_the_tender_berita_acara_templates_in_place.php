<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The tender's Berita Acara follow the unit's own forms: Pemberian
 * Penjelasan (Aanwijzing), Catatan Hasil Pembukaan Penawaran (Lampiran
 * BAPP), Evaluasi Kualifikasi (Teknis), Hasil Evaluasi Penawaran (Harga),
 * Evaluasi Administrasi, Teknik dan Harga, and Klarifikasi Teknik, filled
 * from the calon mitra, their offers and the officials who sign.
 *
 * They replace the installed templates, even ones an administrator changed.
 * Documents nobody edited follow them the next time they are opened.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: [
                'berita-acara.aanwijzing',
                'berita-acara.lampiran-bapp',
                'berita-acara.evaluasi-teknis',
                'berita-acara.evaluasi-harga',
                'berita-acara.hasil-evaluasi',
                'berita-acara.klarifikasi',
            ], force: true);
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
