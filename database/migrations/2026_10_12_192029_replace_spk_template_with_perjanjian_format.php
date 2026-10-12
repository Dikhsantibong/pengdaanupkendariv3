<?php

use Database\Seeders\KontrakTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the general SPK template with the full UP Kendari agreement
 * layout (parties, basis documents, Pasal 1 to 20 and signatures).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $typeId = DB::table('document_types')->where('code', 'spk')->value('id');

        if ($typeId === null) {
            return;
        }

        $body = (new KontrakTemplateSeeder)->spk();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
