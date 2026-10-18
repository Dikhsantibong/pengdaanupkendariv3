<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every name under a signature prints in capitals, including the names
 * still to be typed in (the "( Nama Jelas )" and dotted lines), the partner
 * heading its signature, and the names that relied on a template class the
 * visual editor may drop.
 *
 * Put in the installed templates themselves, unless an administrator has
 * already customised them; documents drawn before keep their own wording.
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
                'umum.tor', 'umum.hpe', 'umum.upb',
                'standar.penawaran', 'standar.csms', 'standar.nota-dinas-perintah-pekerjaan', 'standar.penyusunan-hps',
                'standar.proses-smart-scm', 'standar.penyusunan-kontrak', 'standar.jaminan-bank', 'standar.amandemen',
                'standar.masa-pemeliharaan',
                'berita-acara.aanwijzing', 'berita-acara.lampiran-bapp', 'berita-acara.evaluasi-teknis',
                'berita-acara.evaluasi-harga', 'berita-acara.hasil-evaluasi', 'berita-acara.klarifikasi',
                'kontrak.spk-tabel', 'kontrak.spk-jasa', 'kontrak.spk-barang', 'kontrak.amandemen',
                'kontrak.delivery-order', 'kontrak.lampiran-spk', 'kontrak.ba-negosiasi',
                'rks.spk', 'rks.tender',
                'sppl.ba-negosiasi', 'sppl.purchase-order', 'sppl.surat-pesanan',
                'pj.rks-jasa', 'pj.surat-perjanjian',
            ]);
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
