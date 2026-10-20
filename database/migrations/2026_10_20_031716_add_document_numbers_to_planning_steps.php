<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The planning steps RKS, HPE and UPB each ask for the number of their
     * document, kept on the procurement for the documents to print.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->string('rks_number')->nullable()->after('icc_memo_date');
            $table->string('hpe_number')->nullable()->after('rks_number');
            $table->string('upb_number')->nullable()->after('hpe_number');
        });

        // A step an administrator already gave another input keeps it.
        $steps = [
            'RKS (Rencana Kerja dan Syarat)' => 'rks_number',
            'HPE (Harga Perkiraan Engineer)' => 'hpe_number',
            'UPB' => 'upb_number',
        ];

        foreach ($steps as $name => $kind) {
            DB::table('checklist_items')
                ->where('stage', 'perencanaan')
                ->whereNull('procurement_id')
                ->where('name', $name)
                ->whereNull('input_kind')
                ->update(['input_kind' => $kind, 'updated_at' => now()]);
        }

        // The HPE and UPB print their number from the new field.
        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['umum.hpe', 'umum.upb']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->whereIn('input_kind', ['rks_number', 'hpe_number', 'upb_number'])
            ->update(['input_kind' => null]);

        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn(['rks_number', 'hpe_number', 'upb_number']);
        });
    }
};
