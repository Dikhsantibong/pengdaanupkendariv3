<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reshape the "Usulan Pekerjaan" part of a procurement.
 *
 * - The PRK number and the Nota Dinas Usulan number become separate fields,
 *   and a Nota Dinas ICC number, a COA number, a WO number, the partner who
 *   carries the work out and the value after negotiation are added.
 * - The PR/PO number is typed by hand instead of being chosen from a master
 *   list. Every number already chosen is copied onto its procurement before
 *   the list is dropped, so no procurement loses its PR/PO number.
 * - A procurement may now serve more than one target unit. The units live in
 *   a pivot; `target_unit_id` stays as the first of them so every existing
 *   query that reads a single unit keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->string('partner_name')->nullable()->after('name');
            $table->string('pr_po_number')->nullable()->after('pr_ro_number_id');
            $table->string('proposal_memo_number')->nullable()->after('prk_number');
            $table->string('icc_memo_number')->nullable()->after('proposal_memo_number');
            $table->string('coa_number')->nullable()->after('icc_memo_number');
            $table->string('wo_number')->nullable()->after('coa_number');
            $table->decimal('value_after_negotiation', 20, 2)->nullable()->after('hpe_value');
        });

        // The old field was labelled "Nomor PRK (Nota Dinas Usulan)", so what
        // it holds served as both. It is copied, not moved: each half can then
        // be corrected on its own without anything being lost.
        DB::table('procurements')
            ->whereNotNull('prk_number')
            ->update(['proposal_memo_number' => DB::raw('prk_number')]);

        // Carry every chosen PR/RO number onto its procurement as plain text.
        DB::table('procurements')
            ->whereNotNull('pr_ro_number_id')
            ->orderBy('id')
            ->get(['id', 'pr_ro_number_id'])
            ->each(function (object $row): void {
                $number = DB::table('pr_ro_numbers')->where('id', $row->pr_ro_number_id)->value('number');

                if ($number !== null) {
                    DB::table('procurements')->where('id', $row->id)->update(['pr_po_number' => $number]);
                }
            });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pr_ro_number_id');
        });

        Schema::dropIfExists('pr_ro_numbers');

        Schema::create('procurement_target_unit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            // Target units are soft deleted, never removed outright, so a
            // procurement's history keeps naming the units it served.
            $table->foreignId('target_unit_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['procurement_id', 'target_unit_id']);
        });

        DB::table('procurements')
            ->whereNotNull('target_unit_id')
            ->orderBy('id')
            ->get(['id', 'target_unit_id'])
            ->each(function (object $row): void {
                DB::table('procurement_target_unit')->insert([
                    'procurement_id' => $row->id,
                    'target_unit_id' => $row->target_unit_id,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_target_unit');

        Schema::create('pr_ro_numbers', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('description')->nullable();
            $table->string('source')->default('Smart SCM');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->foreignId('pr_ro_number_id')->nullable()->after('budget_source_id')
                ->constrained()->nullOnDelete();
        });

        // Rebuild the list from the typed numbers and point each procurement
        // back at its entry.
        DB::table('procurements')
            ->whereNotNull('pr_po_number')
            ->orderBy('id')
            ->get(['id', 'pr_po_number'])
            ->each(function (object $row): void {
                $id = DB::table('pr_ro_numbers')->where('number', $row->pr_po_number)->value('id')
                    ?? DB::table('pr_ro_numbers')->insertGetId([
                        'number' => $row->pr_po_number,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('procurements')->where('id', $row->id)->update(['pr_ro_number_id' => $id]);
            });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn([
                'partner_name',
                'pr_po_number',
                'proposal_memo_number',
                'icc_memo_number',
                'coa_number',
                'wo_number',
                'value_after_negotiation',
            ]);
        });
    }
};
