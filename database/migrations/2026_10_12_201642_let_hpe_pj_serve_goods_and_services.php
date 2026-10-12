<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The PJ HPE also serves PJ contracts for services until a services layout
 * is supplied; a services-only template added later outranks it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hpeTypeId = DB::table('document_types')->where('code', 'hpe')->value('id');
        $pjId = DB::table('contract_number_formats')->where('code', 'PJ')->value('id');

        if ($hpeTypeId === null || $pjId === null) {
            return;
        }

        DB::table('document_templates')
            ->where('document_type_id', $hpeTypeId)
            ->where('contract_number_format_id', $pjId)
            ->where('work_category', 'barang')
            ->whereNull('deleted_at')
            ->update([
                'work_category' => null,
                'name' => 'HPE PJ (Barang / Jasa) - Template Standar UP Kendari',
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
