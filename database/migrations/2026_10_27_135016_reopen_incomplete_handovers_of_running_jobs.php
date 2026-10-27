<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Penyerahan approval came with jobs already contracted (Terkontrak)
 * counted as handed over. Most of them had not filed their hand-over papers
 * yet, so the papers were locked before they were uploaded and the payment
 * opened early.
 *
 * Penyerahan is a stage of its own between the contract and the payment:
 * every job not yet paid, whose payment is not submitted, and whose
 * mandatory hand-over papers are not all filed, goes back to Penyerahan
 * (Belum Diajukan). The execution PIC files them and submits them to TL
 * Pengadaan; until approved, Pembayaran stays locked. Jobs whose papers are
 * complete keep their approval.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $incomplete = DB::table('procurement_checklists as steps')
            ->join('checklist_items as items', 'items.id', '=', 'steps.checklist_item_id')
            ->whereColumn('steps.procurement_id', 'procurements.id')
            ->where('steps.stage', 'keuangan')
            ->where('items.section', 'penyerahan')
            ->where('items.is_optional', false)
            ->where('items.is_active', true)
            ->whereNotExists(fn ($files) => $files
                ->from('procurement_checklist_files as files')
                ->whereColumn('files.procurement_checklist_id', 'steps.id'));

        DB::table('procurements')
            ->whereNotNull('finance_submitted_at')
            ->whereNull('payment_completed_at')
            ->whereNull('completed_at')
            ->where('handover_approval_state', 'disetujui')
            ->where('payment_approval_state', 'belum_diajukan')
            ->whereExists($incomplete)
            ->update([
                'handover_approval_state' => 'belum_diajukan',
                'handover_submitted_at' => null,
                'handover_submitted_by' => null,
                'handover_reviewed_at' => null,
                'handover_reviewed_by' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: whichever were reopened are filed and approved again
     * through the normal flow.
     */
    public function down(): void
    {
        //
    }
};
