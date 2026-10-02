<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('checklist_items')->where('stage', 'pelaksanaan')->exists()) {
            return;
        }

        $now = now();

        $poType = DB::table('document_types')->where('code', 'purchase-order')->first();

        if ($poType === null) {
            $order = (int) DB::table('document_types')->max('sort_order');
            $typeId = DB::table('document_types')->insertGetId([
                'code' => 'purchase-order',
                'name' => 'Purchase Order (PO)',
                'stage' => 'pelaksanaan',
                'upload_only' => false,
                'sort_order' => ++$order,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $typeId = $poType->id;
            DB::table('document_types')
                ->where('id', $typeId)
                ->update([
                    'upload_only' => false,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        }

        $poStepId = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Purchase Order (PO)')
            ->value('id');

        if ($poStepId !== null && $typeId !== null) {
            $exists = DB::table('checklist_item_document_type')
                ->where('checklist_item_id', $poStepId)
                ->where('document_type_id', $typeId)
                ->exists();

            if (! $exists) {
                DB::table('checklist_item_document_type')->insert([
                    'checklist_item_id' => $poStepId,
                    'document_type_id' => $typeId,
                    'sort_order' => 1,
                    'is_alternative' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('document_types')
            ->where('code', 'surat-pesanan-barang')
            ->update(['name' => 'Lampiran SP Barang', 'updated_at' => $now]);

        DB::table('document_types')
            ->where('code', 'surat-pesanan-jasa')
            ->update(['name' => 'Lampiran SP Jasa', 'updated_at' => $now]);

        $spplFormatId = DB::table('contract_number_formats')->where('code', 'SPPL')->value('id');
        $penyusunanKontrakStepId = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Penyusunan Kontrak')
            ->value('id');

        if ($spplFormatId !== null && $penyusunanKontrakStepId !== null) {
            $alreadyExcluded = DB::table('contract_format_checklist_item_exclusions')
                ->where('contract_number_format_id', $spplFormatId)
                ->where('checklist_item_id', $penyusunanKontrakStepId)
                ->exists();

            if (! $alreadyExcluded) {
                DB::table('contract_format_checklist_item_exclusions')->insert([
                    'contract_number_format_id' => $spplFormatId,
                    'checklist_item_id' => $penyusunanKontrakStepId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $poStepId = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Purchase Order (PO)')
            ->value('id');

        $typeId = DB::table('document_types')->where('code', 'purchase-order')->value('id');

        if ($poStepId !== null && $typeId !== null) {
            DB::table('checklist_item_document_type')
                ->where('checklist_item_id', $poStepId)
                ->where('document_type_id', $typeId)
                ->delete();
        }
    }
};
