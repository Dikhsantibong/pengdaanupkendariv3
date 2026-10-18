<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nothing incomplete moves on.
 *
 * - A proposal reaches its TL / Asman only once every item the proposer
 *   owes is in (the identity data, the signed TOR and Nota Dinas Usulan):
 *   `proposal_completed_at` records when it did. A waiting proposal that is
 *   still incomplete is held with its proposer.
 * - The calon mitra carry their offer: its total value before negotiation
 *   and the scanned quotation.
 * - The "Penawaran" planning step lists the calon mitra (their quotations
 *   replace the step's separate upload), and the "Nota Dinas ke Pengadaan"
 *   step asks for the memo's number and date beside its upload.
 */
return new class extends Migration
{
    /**
     * The identity data a proposal must carry.
     *
     * @var array<int, string>
     */
    protected const REQUIRED_DATA = [
        'name', 'budget_source_id',
        'prk_number', 'pr_po_number', 'coa_number', 'proposal_memo_number', 'proposal_memo_date',
    ];

    /**
     * The required items held as text.
     *
     * @var array<int, string>
     */
    protected const REQUIRED_TEXT = ['name', 'prk_number', 'pr_po_number', 'coa_number', 'proposal_memo_number'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->timestamp('proposal_completed_at')->nullable()->after('proposal_submitted_at');
        });

        Schema::table('procurement_partner_candidates', function (Blueprint $table) {
            $table->decimal('quotation_value', 20, 2)->nullable()->after('quotation_date');
            $table->string('quotation_file_path')->nullable()->after('quotation_value');
            $table->string('quotation_file_name')->nullable()->after('quotation_file_path');
            $table->string('quotation_file_mime')->nullable()->after('quotation_file_name');
            $table->unsignedBigInteger('quotation_file_size')->nullable()->after('quotation_file_mime');
        });

        $this->markCompleteProposals();
        $this->attachPlanningInputs();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->whereIn('input_kind', ['partner_candidates', 'procurement_memo'])
            ->update(['input_kind' => null]);

        Schema::table('procurement_partner_candidates', function (Blueprint $table) {
            $table->dropColumn(['quotation_value', 'quotation_file_path', 'quotation_file_name', 'quotation_file_mime', 'quotation_file_size']);
        });

        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn('proposal_completed_at');
        });
    }

    /**
     * Every proposal already past its first approval counts as complete when
     * it was submitted; a waiting one only when nothing is missing.
     */
    protected function markCompleteProposals(): void
    {
        $query = DB::table('procurements')->whereNotNull('proposal_submitted_at');

        (clone $query)
            ->whereNotIn('proposal_state', ['menunggu_persetujuan_tl', 'menunggu_persetujuan'])
            ->update(['proposal_completed_at' => DB::raw('proposal_submitted_at')]);

        $waiting = (clone $query)->whereIn('proposal_state', ['menunggu_persetujuan_tl', 'menunggu_persetujuan']);

        foreach (self::REQUIRED_DATA as $column) {
            $waiting->whereNotNull($column);
        }

        // Only text can be blank; a date or a key compared with '' trips
        // MySQL's strict mode.
        foreach (self::REQUIRED_TEXT as $column) {
            $waiting->where($column, '!=', '');
        }

        foreach (['tor', 'nota-dinas-usulan'] as $code) {
            $waiting->whereExists(fn (Builder $signed) => $signed->selectRaw('1')
                ->from('procurement_documents as d')
                ->join('document_types as t', 't.id', '=', 'd.document_type_id')
                ->join('procurement_document_uploads as u', 'u.procurement_document_id', '=', 'd.id')
                ->whereColumn('d.procurement_id', 'procurements.id')
                ->where('t.code', $code));
        }

        $waiting->update(['proposal_completed_at' => DB::raw('proposal_submitted_at')]);
    }

    /**
     * Give the two planning steps their inputs, unless an administrator has
     * already set one, and let the calon mitra carry the offer instead of
     * the Penawaran step's separate upload.
     */
    protected function attachPlanningInputs(): void
    {
        foreach (['Penawaran' => 'partner_candidates', 'Nota Dinas ke Pengadaan' => 'procurement_memo'] as $name => $kind) {
            DB::table('checklist_items')
                ->whereNull('procurement_id')
                ->where('stage', 'perencanaan')
                ->where('name', $name)
                ->whereNull('input_kind')
                ->update(['input_kind' => $kind, 'updated_at' => now()]);
        }

        $offer = DB::table('document_types')->where('code', 'penawaran')->value('id');

        if ($offer === null) {
            return;
        }

        DB::table('checklist_item_document_type')
            ->where('document_type_id', $offer)
            ->whereIn('checklist_item_id', DB::table('checklist_items')
                ->whereNull('procurement_id')
                ->where('stage', 'perencanaan')
                ->where('name', 'Penawaran')
                ->select('id'))
            ->delete();
    }
};
