<?php

use Database\Seeders\KontrakTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split SPK and PJ contracts into goods (barang) and services (jasa).
 *
 * - Contract number formats say whether they ask for the category.
 * - A procurement records its category.
 * - A template may be limited to one category; the existing SPK template is
 *   the services one, and a goods SPK template is added beside it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contract_number_formats', function (Blueprint $table): void {
            $table->boolean('uses_work_category')->default(false)->after('starting_sequence');
        });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->string('work_category', 10)->nullable()->after('contract_number_format_id');
        });

        Schema::table('document_templates', function (Blueprint $table): void {
            $table->string('work_category', 10)->nullable()->after('procurement_method_id');
        });

        DB::table('contract_number_formats')->whereIn('code', ['SPK', 'PJ'])->update(['uses_work_category' => true]);

        // Running SPK/PJ procurements were all drafted on the services SPK.
        $formatIds = DB::table('contract_number_formats')->where('uses_work_category', true)->pluck('id');
        DB::table('procurements')->whereIn('contract_number_format_id', $formatIds)->update(['work_category' => 'jasa']);

        $spkTypeId = DB::table('document_types')->where('code', 'spk')->value('id');

        if ($spkTypeId === null) {
            return;
        }

        DB::table('document_templates')
            ->where('document_type_id', $spkTypeId)
            ->whereNull('procurement_method_id')
            ->whereNull('deleted_at')
            ->update(['work_category' => 'jasa']);

        $body = (new KontrakTemplateSeeder)->spk('barang');
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        DB::table('document_templates')->insert([
            'document_type_id' => $spkTypeId,
            'procurement_method_id' => null,
            'work_category' => 'barang',
            'name' => 'SPK (Surat Perintah Kerja) Barang - Template Standar UP Kendari',
            'version' => 1,
            'body' => $body,
            'placeholders' => json_encode(array_values(array_unique(array_map('strtolower', $matches[1])))),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('document_templates')->where('work_category', 'barang')->delete();

        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropColumn('work_category');
        });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn('work_category');
        });

        Schema::table('contract_number_formats', function (Blueprint $table): void {
            $table->dropColumn('uses_work_category');
        });
    }
};
