<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The SPK gets its own Pelaksanaan step, Hasil Pembukaan Penawaran Harga, with
 * the form SMT-FM-DAN-01.02 drawn from the calon mitra's offers. It comes
 * right after Penyusunan HPS.
 *
 * Every other step and document stays as it was; PJ and SPPL skip the new
 * step. A job whose execution is already submitted or approved does not get
 * it either, so nothing it has done is reopened.
 */
return new class extends Migration
{
    private const STEP = 'Hasil Pembukaan Penawaran Harga';

    private const TYPE = 'hasil-pembukaan-penawaran-harga';

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
                'name' => 'Hasil Pembukaan Penawaran Harga',
                'stage' => 'pelaksanaan',
                'sort_order' => (int) DB::table('document_types')->max('sort_order') + 1,
                'upload_only' => false,
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
                ->where('name', 'Penyusunan HPS')
                ->whereNull('deleted_at')
                ->value('sort_order');

            $sortOrder = $after === null
                ? (int) DB::table('checklist_items')->whereNull('procurement_id')->where('stage', 'pelaksanaan')->max('sort_order') + 1
                : (int) $after + 1;

            // Make room right after Penyusunan HPS.
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
                'description' => 'Formulir Hasil Pembukaan Penawaran Harga (SMT-FM-DAN-01.02) dari penawaran calon mitra. Khusus SPK.',
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

        app(DocumentTemplateLibrary::class)->sync(keys: ['standar.hasil-pembukaan-penawaran-harga']);
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
