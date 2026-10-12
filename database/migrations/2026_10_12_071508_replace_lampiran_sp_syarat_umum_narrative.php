<?php

use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the Syarat Umum narrative of the Lampiran SP Barang / Jasa
 * templates with the UP Kendari paper form (sections A to G). Documents
 * already generated keep the wording they were issued with.
 */
return new class extends Migration
{
    /**
     * The document types carrying the Syarat Umum, with their kind.
     *
     * @var array<string, string>
     */
    private const TYPES = [
        'lampiran-sp-barang' => 'BARANG',
        'lampiran-sp-jasa' => 'JASA',
        'surat-pesanan-barang' => 'BARANG',
        'surat-pesanan-jasa' => 'JASA',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $seeder = new SpplDocumentTemplateSeeder;

        foreach (self::TYPES as $code => $kind) {
            $typeId = DB::table('document_types')->where('code', $code)->value('id');

            if ($typeId === null) {
                continue;
            }

            $body = $seeder->syaratUmum($kind);
            preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

            DB::table('document_templates')
                ->where('document_type_id', $typeId)
                ->whereNull('procurement_method_id')
                ->whereNull('deleted_at')
                ->update([
                    'body' => $body,
                    'placeholders' => json_encode(array_values(array_unique(array_map('strtolower', $matches[1])))),
                    'updated_at' => now(),
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
