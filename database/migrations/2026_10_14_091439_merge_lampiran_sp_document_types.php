<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The SP appendix is one document for goods and services alike — its terms
 * already cover both — so the separate "Lampiran SP Barang" and "Lampiran SP
 * Jasa" types (and their older duplicates) become one "Lampiran SP Barang dan
 * Jasa". Documents already drawn from the old types keep their content and
 * signed scans and are moved onto the new type, so a step they completed
 * stays completed.
 */
return new class extends Migration
{
    /**
     * The types that are merged.
     *
     * @var array<int, string>
     */
    private const OLD_CODES = ['surat-pesanan-barang', 'surat-pesanan-jasa', 'lampiran-sp-barang', 'lampiran-sp-jasa'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $oldIds = DB::table('document_types')->whereIn('code', self::OLD_CODES)->pluck('id');

        // A fresh database has nothing to merge; the master data seeder
        // creates the one appendix type there.
        if ($oldIds->isEmpty()) {
            return;
        }

        $merged = DB::table('document_types')->where('code', 'lampiran-sp')->value('id');

        if ($merged === null) {
            $merged = DB::table('document_types')->insertGetId([
                'code' => 'lampiran-sp',
                'name' => 'Lampiran SP Barang dan Jasa',
                'stage' => 'pelaksanaan',
                'upload_only' => false,
                'sort_order' => (int) DB::table('document_types')->whereIn('id', $oldIds)->min('sort_order'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('procurement_documents')
            ->whereIn('document_type_id', $oldIds)
            ->get(['id', 'title'])
            ->each(fn (object $document) => DB::table('procurement_documents')
                ->where('id', $document->id)
                ->update(['title' => preg_replace(
                    '/^Lampiran SP (Barang|Jasa) - /',
                    'Lampiran SP Barang dan Jasa - ',
                    (string) $document->title,
                )]));

        DB::table('procurement_documents')->whereIn('document_type_id', $oldIds)->update(['document_type_id' => $merged]);

        // Every step that offered the old appendices now asks for the one.
        $steps = DB::table('checklist_item_document_type')
            ->whereIn('document_type_id', $oldIds)
            ->pluck('checklist_item_id')
            ->unique();

        DB::table('checklist_item_document_type')->whereIn('document_type_id', $oldIds)->delete();

        foreach ($steps as $stepId) {
            DB::table('checklist_item_document_type')->insertOrIgnore([
                'checklist_item_id' => $stepId,
                'document_type_id' => $merged,
                'sort_order' => 1,
                'is_alternative' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Kept on record, no longer offered.
        DB::table('document_templates')->whereIn('document_type_id', $oldIds)->update(['is_active' => false]);
        DB::table('document_types')->whereIn('id', $oldIds)->update(['is_active' => false]);

        // Where the default templates are installed, install this one too; a
        // fresh database gets it with the rest from the template seeders.
        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['sppl.lampiran-sp']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The merge is not undone: documents drawn since cannot be told apart.
     */
    public function down(): void
    {
        //
    }
};
