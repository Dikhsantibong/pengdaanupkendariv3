<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An SPPL gives its contract number on the Surat Pesanan step instead of the
 * Lampiran Surat Pesanan step.
 *
 * - A step's input can now be limited to one contract format: Surat Pesanan
 *   asks the number of the SPPL alone (SPK and PJ give theirs on Kontrak).
 * - A Surat Pesanan step removed in Data Master comes back for the SPPL only,
 *   with its Surat Pesanan document, so SPK and PJ are left as they are. A
 *   step switched off, skipped by the SPPL or given another input is the
 *   administrator's choice and stays; the Lampiran then keeps the number.
 * - The number itself lives on the procurement, so one already given on the
 *   Lampiran shows as filled on the Surat Pesanan step.
 * - Running SPPL jobs get the step now: as a step to do while their
 *   execution is still being worked on, as done history once it was
 *   submitted, approved or paid, so nothing already handed on is held back.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safe to run again after an interrupted run: the column may be there.
        if (! Schema::hasColumn('checklist_items', 'input_contract_number_format_id')) {
            Schema::table('checklist_items', function (Blueprint $table): void {
                $table->foreignId('input_contract_number_format_id')
                    ->nullable()
                    ->after('input_kind')
                    ->constrained('contract_number_formats')
                    ->nullOnDelete();
            });
        }

        $formats = DB::table('contract_number_formats')->pluck('id', 'code');
        $sppl = $formats['SPPL'] ?? null;

        if ($sppl === null) {
            return;
        }

        $item = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Surat Pesanan')
            ->whereNull('procurement_id')
            ->orderByRaw('deleted_at is null desc')
            ->orderByDesc('id')
            ->first();

        if ($item === null) {
            return;
        }

        $now = now();

        if ($item->deleted_at !== null) {
            $this->restoreForSpplOnly($item->id, array_filter([$formats['SPK'] ?? null, $formats['PJ'] ?? null]), $now);
        } elseif (! $item->is_active
            || ! in_array($item->input_kind, [null, 'contract_number'], true)
            || $this->skips($item->id, $sppl)) {
            return;
        }

        DB::table('checklist_items')->where('id', $item->id)->update([
            'input_kind' => 'contract_number',
            'input_contract_number_format_id' => $sppl,
            'updated_at' => $now,
        ]);

        DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Lampiran Surat Pesanan')
            ->whereNull('procurement_id')
            ->where('input_kind', 'contract_number')
            ->update(['input_kind' => null, 'updated_at' => $now]);

        $this->addToRunningJobs($item->id, $sppl, $now);
    }

    /**
     * Bring a removed Surat Pesanan step back, for the SPPL alone.
     *
     * @param  array<int, int>  $otherFormats
     */
    protected function restoreForSpplOnly(int $itemId, array $otherFormats, mixed $now): void
    {
        DB::table('checklist_items')->where('id', $itemId)->update([
            'deleted_at' => null,
            'is_active' => true,
            'is_optional' => false,
            'updated_at' => $now,
        ]);

        // The step comes back for the SPPL, so the SPPL must not skip it.
        $sppl = DB::table('contract_number_formats')->where('code', 'SPPL')->value('id');

        if ($sppl !== null) {
            DB::table('checklist_item_format_exclusions')
                ->where('checklist_item_id', $itemId)
                ->where('contract_number_format_id', $sppl)
                ->delete();
        }

        foreach ($otherFormats as $formatId) {
            if (! $this->skips($itemId, $formatId)) {
                DB::table('checklist_item_format_exclusions')->insert([
                    'checklist_item_id' => $itemId,
                    'contract_number_format_id' => $formatId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // The Surat Pesanan document, generated then uploaded validated.
        $linked = DB::table('checklist_item_document_type')->where('checklist_item_id', $itemId)->exists();
        $document = DB::table('document_types')->whereIn('code', ['purchase-order', 'surat-pesanan'])
            ->whereNull('deleted_at')
            ->orderByRaw("code = 'purchase-order' desc")
            ->value('id');

        if (! $linked && $document !== null) {
            DB::table('checklist_item_document_type')->insert([
                'checklist_item_id' => $itemId,
                'document_type_id' => $document,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Whether a step is skipped by a contract format.
     */
    protected function skips(int $itemId, int $formatId): bool
    {
        return DB::table('checklist_item_format_exclusions')
            ->where('checklist_item_id', $itemId)
            ->where('contract_number_format_id', $formatId)
            ->exists();
    }

    /**
     * Give the running SPPL jobs the step.
     */
    protected function addToRunningJobs(int $itemId, int $sppl, mixed $now): void
    {
        DB::table('procurements')
            ->where('contract_number_format_id', $sppl)
            ->whereNull('deleted_at')
            ->whereNotExists(fn ($query) => $query
                ->from('procurement_checklists')
                ->whereColumn('procurement_checklists.procurement_id', 'procurements.id')
                ->where('procurement_checklists.checklist_item_id', $itemId))
            ->orderBy('id')
            ->get(['id', 'execution_approval_state', 'finance_submitted_at', 'completed_at'])
            ->each(function (object $procurement) use ($itemId, $now): void {
                $handedOn = $procurement->completed_at !== null
                    || $procurement->finance_submitted_at !== null
                    || in_array($procurement->execution_approval_state, ['menunggu_persetujuan', 'disetujui'], true);

                DB::table('procurement_checklists')->insert([
                    'procurement_id' => $procurement->id,
                    'checklist_item_id' => $itemId,
                    'stage' => 'pelaksanaan',
                    'is_completed' => $handedOn,
                    'completed_at' => $handedOn ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     *
     * The input goes back to the Lampiran. A restored step stays: it may have
     * been worked on since.
     */
    public function down(): void
    {
        $steps = fn () => DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->whereNull('procurement_id');

        if ($steps()->where('name', 'Surat Pesanan')->where('input_kind', 'contract_number')->exists()) {
            $steps()
                ->where('name', 'Lampiran Surat Pesanan')
                ->whereNull('input_kind')
                ->update(['input_kind' => 'contract_number', 'updated_at' => now()]);

            $steps()
                ->where('name', 'Surat Pesanan')
                ->where('input_kind', 'contract_number')
                ->update(['input_kind' => null, 'updated_at' => now()]);
        }

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('input_contract_number_format_id');
        });
    }
};
