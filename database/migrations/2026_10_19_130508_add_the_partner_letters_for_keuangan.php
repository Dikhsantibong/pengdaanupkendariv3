<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The letters the partner writes for Keuangan: the Surat Jalan, the
     * Surat Pengantar Penyerahan Pekerjaan, the Berita Acara Pemeriksaan /
     * Penyelesaian Pekerjaan, the Surat Pernyataan Garansi Material and the
     * Surat Permohonan Pembayaran, downloaded from their step as Word and
     * uploaded back once signed.
     */
    public function up(): void
    {
        if (! DB::table('document_types')->exists()) {
            return;
        }

        $letters = [
            'surat-jalan' => 'Surat Jalan',
            'surat-pengantar-penyerahan' => 'Surat Pengantar Penyerahan Pekerjaan',
            'berita-acara-pemeriksaan' => 'Berita Acara Pemeriksaan Pekerjaan',
            'surat-pernyataan-garansi' => 'Surat Pernyataan Garansi Material',
            'surat-permohonan-pembayaran' => 'Surat Permohonan Pembayaran',
        ];

        foreach ($letters as $code => $name) {
            if (DB::table('document_types')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('document_types')->insert([
                'code' => $code,
                'name' => $name,
                'stage' => 'keuangan',
                'sort_order' => (int) DB::table('document_types')->max('sort_order') + 1,
                'upload_only' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['keuangan.surat-jalan', 'keuangan.pengantar-penyerahan', 'keuangan.berita-acara-pemeriksaan', 'keuangan.pernyataan-garansi', 'keuangan.permohonan-pembayaran']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $types = DB::table('document_types')
            ->whereIn('code', ['surat-jalan', 'surat-pengantar-penyerahan', 'berita-acara-pemeriksaan', 'surat-pernyataan-garansi', 'surat-permohonan-pembayaran'])
            ->pluck('id');

        DB::table('document_templates')->whereIn('document_type_id', $types)->delete();
        DB::table('document_types')->whereIn('id', $types)->delete();
    }
};
