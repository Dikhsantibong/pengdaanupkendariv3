<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The SPK gets a Pelaksanaan step for the partner's Pakta Integritas: the
 * signed scan is uploaded, nothing is generated. It comes right after the
 * Hasil Pembukaan Penawaran Harga (or Penyusunan HPS).
 *
 * PJ and SPPL skip it. A job whose execution is already submitted or approved
 * does not get it either, so nothing it has done is reopened.
 */
return new class extends Migration
{
    private const STEP = 'Pakta Integritas';

    private const TYPE = 'pakta-integritas';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('document_types')->exists()) {
            // A fresh database: the master data seeder lays it all out.
            return;
        }

        $typeId = DB::table('document_types')->where('code', self::TYPE)->value('id')
            ?? DB::table('document_types')->insertGetId([
                'code' => self::TYPE,
                'name' => 'Pakta Integritas',
                'stage' => 'pelaksanaan',
                'sort_order' => (int) DB::table('document_types')->max('sort_order') + 1,
                // Signed by the partner and uploaded; no template.
                'upload_only' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $stepId = DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', self::STEP)
            ->whereNull('deleted_at')
            ->value('id');

        if ($stepId === null) {
            $after = DB::table('checklist_items')
                ->whereNull('procurement_id')
                ->where('stage', 'pelaksanaan')
                ->whereIn('name', ['Hasil Pembukaan Penawaran Harga', 'Penyusunan HPS'])
                ->whereNull('deleted_at')
                ->max('sort_order');

            $sortOrder = $after === null
                ? (int) DB::table('checklist_items')->whereNull('procurement_id')->where('stage', 'pelaksanaan')->max('sort_order') + 1
                : (int) $after + 1;

            if ($after !== null) {
                DB::table('checklist_items')
                    ->whereNull('procurement_id')
                    ->where('stage', 'pelaksanaan')
                    ->where('sort_order', '>=', $sortOrder)
                    ->increment('sort_order');
            }

            $stepId = DB::table('checklist_items')->insertGetId([
                'stage' => 'pelaksanaan',
                'name' => self::STEP,
                'description' => 'Unggah Pakta Integritas yang sudah ditandatangani penyedia. Khusus SPK.',
                'is_optional' => false,
                'is_custom' => false,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Jobs past their execution keep their steps as they are.
            $done = DB::table('procurements')
                ->where(fn ($query) => $query
                    ->whereIn('execution_approval_state', ['menunggu_persetujuan', 'disetujui'])
                    ->orWhereNotNull('finance_submitted_at')
                    ->orWhereNotNull('completed_at'))
                ->pluck('id');

            foreach ($done->chunk(500) as $chunk) {
                DB::table('procurement_step_exclusions')->insertOrIgnore($chunk->map(fn (int $id): array => [
                    'procurement_id' => $id,
                    'checklist_item_id' => $stepId,
                    'removed_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }
        }

        DB::table('checklist_item_document_type')->insertOrIgnore([
            'checklist_item_id' => $stepId,
            'document_type_id' => $typeId,
            'sort_order' => 1,
            'is_alternative' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // SPK only: PJ and SPPL skip it.
        foreach (DB::table('contract_number_formats')->whereIn('code', ['PJ', 'SPPL'])->pluck('id') as $formatId) {
            DB::table('checklist_item_format_exclusions')->insertOrIgnore([
                'checklist_item_id' => $stepId,
                'contract_number_format_id' => $formatId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The step goes only while no job has used it.
     */
    public function down(): void
    {
        $stepId = DB::table('checklist_items')->whereNull('procurement_id')->where('name', self::STEP)->value('id');

        if ($stepId !== null && ! DB::table('procurement_checklists')->where('checklist_item_id', $stepId)->where('is_completed', true)->exists()) {
            DB::table('procurement_checklists')->where('checklist_item_id', $stepId)->delete();
            DB::table('checklist_items')->where('id', $stepId)->delete();
        }
    }
};
