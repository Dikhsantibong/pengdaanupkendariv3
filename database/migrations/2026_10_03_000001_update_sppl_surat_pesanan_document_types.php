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

        $types = [
            ['surat-pesanan-barang', 'Surat Pesanan (Barang)'],
            ['surat-pesanan-jasa', 'Surat Pesanan (Jasa)'],
        ];

        $order = (int) DB::table('document_types')->max('sort_order');

        foreach ($types as [$code, $name]) {
            if (! DB::table('document_types')->where('code', $code)->exists()) {
                DB::table('document_types')->insert([
                    'code' => $code,
                    'name' => $name,
                    'stage' => 'pelaksanaan',
                    'upload_only' => false,
                    'sort_order' => ++$order,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $barangId = DB::table('document_types')->where('code', 'surat-pesanan-barang')->value('id');
        $jasaId = DB::table('document_types')->where('code', 'surat-pesanan-jasa')->value('id');

        $suratPesananStepId = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Surat Pesanan')
            ->value('id');

        if ($suratPesananStepId !== null && $barangId !== null && $jasaId !== null) {
            // Replace previous document links for Surat Pesanan with Barang and Jasa alternatives
            DB::table('checklist_item_document_type')
                ->where('checklist_item_id', $suratPesananStepId)
                ->delete();

            DB::table('checklist_item_document_type')->insert([
                [
                    'checklist_item_id' => $suratPesananStepId,
                    'document_type_id' => $barangId,
                    'sort_order' => 1,
                    'is_alternative' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'checklist_item_id' => $suratPesananStepId,
                    'document_type_id' => $jasaId,
                    'sort_order' => 2,
                    'is_alternative' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        $suratPesananStepId = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Surat Pesanan')
            ->value('id');

        $suratPesananId = DB::table('document_types')->where('code', 'surat-pesanan')->value('id');
        $lampiranBarangId = DB::table('document_types')->where('code', 'lampiran-sp-barang')->value('id');
        $lampiranJasaId = DB::table('document_types')->where('code', 'lampiran-sp-jasa')->value('id');

        if ($suratPesananStepId !== null && $suratPesananId !== null) {
            DB::table('checklist_item_document_type')
                ->where('checklist_item_id', $suratPesananStepId)
                ->delete();

            DB::table('checklist_item_document_type')->insert([
                'checklist_item_id' => $suratPesananStepId,
                'document_type_id' => $suratPesananId,
                'sort_order' => 1,
                'is_alternative' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($lampiranBarangId) {
                DB::table('checklist_item_document_type')->insert([
                    'checklist_item_id' => $suratPesananStepId,
                    'document_type_id' => $lampiranBarangId,
                    'sort_order' => 2,
                    'is_alternative' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($lampiranJasaId) {
                DB::table('checklist_item_document_type')->insert([
                    'checklist_item_id' => $suratPesananStepId,
                    'document_type_id' => $lampiranJasaId,
                    'sort_order' => 3,
                    'is_alternative' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
