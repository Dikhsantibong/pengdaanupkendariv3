<?php

use App\Models\ProcurementDocument;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // 1. Rename execution checklist items based on attached document types to be completely deterministic
        $poDocTypeId = DB::table('document_types')->where('code', 'purchase-order')->value('id');
        $spBarangDocTypeId = DB::table('document_types')->where('code', 'surat-pesanan-barang')->value('id');

        if ($spBarangDocTypeId !== null) {
            $lampiranItemId = DB::table('checklist_item_document_type')
                ->where('document_type_id', $spBarangDocTypeId)
                ->value('checklist_item_id');
            if ($lampiranItemId !== null) {
                DB::table('checklist_items')
                    ->where('id', $lampiranItemId)
                    ->update(['name' => 'Lampiran Surat Pesanan', 'updated_at' => $now]);
            }
        }

        if ($poDocTypeId !== null) {
            $poItemId = DB::table('checklist_item_document_type')
                ->where('document_type_id', $poDocTypeId)
                ->value('checklist_item_id');
            if ($poItemId !== null) {
                DB::table('checklist_items')
                    ->where('id', $poItemId)
                    ->update(['name' => 'Surat Pesanan', 'updated_at' => $now]);
            }
        }

        DB::table('document_types')
            ->where('code', 'purchase-order')
            ->update(['name' => 'Surat Pesanan', 'updated_at' => $now]);

        // 2. Re-seed the SPPL and Kontrak document templates
        $spplSeeder = new SpplDocumentTemplateSeeder;
        $spplSeeder->run();

        $kontrakSeeder = new KontrakTemplateSeeder;
        $kontrakSeeder->run();

        // 3. Update existing generated documents for ba-negosiasi-sppl and ba-negosiasi to landscape
        $baSpplType = DB::table('document_types')->where('code', 'ba-negosiasi-sppl')->first();
        if ($baSpplType !== null) {
            $docs = ProcurementDocument::query()
                ->where('document_type_id', $baSpplType->id)
                ->get();

            foreach ($docs as $doc) {
                $body = $doc->rendered_body;
                $body = preg_replace('/@page\s*\{\s*size:\s*A4\s*portrait[^}]*\}/i', '@page { size: A4 landscape; margin: 15mm 20mm; }', $body);
                $doc->rendered_body = $body;
                $doc->save();
            }
        }

        $baKontrakType = DB::table('document_types')->where('code', 'ba-negosiasi')->first();
        if ($baKontrakType !== null) {
            $docs = ProcurementDocument::query()
                ->where('document_type_id', $baKontrakType->id)
                ->get();

            foreach ($docs as $doc) {
                $body = $doc->rendered_body;
                $body = preg_replace('/@page\s*\{\s*size:\s*A4\s*portrait[^}]*\}/i', '@page { size: A4 landscape; margin: 15mm 20mm; }', $body);
                $doc->rendered_body = $body;
                $doc->save();
            }
        }

        // 4. Update existing generated documents for surat-pesanan-jasa
        $spJasaType = DB::table('document_types')->where('code', 'surat-pesanan-jasa')->first();
        if ($spJasaType !== null) {
            $docs = ProcurementDocument::query()
                ->where('document_type_id', $spJasaType->id)
                ->get();

            foreach ($docs as $doc) {
                $body = $doc->rendered_body;
                $body = str_replace(
                    'sampai diterbitkannya Berita Acara Penerimaan Material yang dilengkapi Foto Dokumentasi.',
                    'sampai diterbitkannya Berita Acara Penerimaan jasa yang dilengkapi Foto Dokumentasi.',
                    $body,
                );
                if (! str_contains($body, 'Entry permit / working permit') && str_contains($body, 'Surat Pernyataan Garansi</li>')) {
                    $body = str_replace(
                        'Surat Pernyataan Garansi</li>',
                        "Surat Pernyataan Garansi</li>\n                            <li style=\"margin-bottom: 1px;\">Entry permit / working permit</li>",
                        $body,
                    );
                }
                $doc->rendered_body = $body;
                $doc->save();
            }
        }

        // 5. Prune cached PDFs so they re-render with new landscape format and wording
        try {
            $disk = Storage::disk('local');
            if ($disk->exists('documents/pdf')) {
                foreach ($disk->files('documents/pdf') as $file) {
                    $disk->delete($file);
                }
            }
        } catch (Throwable) {
            // Ignore cache prune errors
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $now = now();

        DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Surat Pesanan')
            ->update(['name' => 'Purchase Order (PO)', 'updated_at' => $now]);

        DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Lampiran Surat Pesanan')
            ->update(['name' => 'Surat Pesanan', 'updated_at' => $now]);

        DB::table('document_types')
            ->where('code', 'purchase-order')
            ->update(['name' => 'Purchase Order (PO)', 'updated_at' => $now]);
    }
};
