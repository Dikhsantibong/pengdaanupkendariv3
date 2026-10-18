<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every signatory's name under a signature prints in capitals (the Manager,
 * the Direktur, the Direksi Pekerjaan, the PICs), as a style on the name
 * only: the data stays as it is kept, and a name in the body text is left
 * as written.
 *
 * Installed as a new version of each default template, unless an
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
            app(DocumentTemplateLibrary::class)->sync(keys: [
                'umum.nota-dinas-usulan', 'umum.tor', 'umum.rab', 'umum.hpe', 'umum.upb', 'umum.rks', 'umum.berita-acara',
                'standar.nota-dinas-perintah-pekerjaan', 'standar.proses-smart-scm', 'standar.penyusunan-kontrak',
                'berita-acara.evaluasi-teknis', 'berita-acara.evaluasi-harga',
                'kontrak.spk-tabel', 'kontrak.spk-jasa', 'kontrak.spk-barang', 'kontrak.amandemen',
                'kontrak.delivery-order', 'kontrak.ba-negosiasi',
                'rks.spk', 'rks.tender',
                'sppl.ba-negosiasi', 'sppl.purchase-order', 'sppl.surat-pesanan',
                'pj.hpe', 'pj.rks-jasa', 'pj.surat-perjanjian', 'pj.upb',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The previous versions stay in the template history.
     */
    public function down(): void
    {
        //
    }
};
