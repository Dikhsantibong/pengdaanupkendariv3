<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planning steps that are only uploaded, and steps that depend on the format.
 *
 * - A document type can be "upload only": nothing is generated from a
 *   template, the signed or prepared file is simply uploaded. The six
 *   opening planning documents become upload only.
 * - A checklist step can be switched off for a contract number format, the
 *   same way it already can for a procurement method. SPPL is added as a
 *   format with the RAB step switched off, so its Penawaran covers both.
 */
return new class extends Migration
{
    /**
     * The planning documents that are now uploaded rather than generated.
     *
     * @var array<int, string>
     */
    private const UPLOAD_ONLY = [
        'nota-dinas-usulan',
        'tor',
        'rab',
        'penawaran',
        'csms',
        'nota-dinas-perintah-pekerjaan',
    ];

    /**
     * Step names updated to the wording used by the unit, keyed by the name
     * they had. Only rows still carrying the old name are touched, so a name
     * already changed by an administrator is left alone.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'TOR (Term of Reference)' => 'TOR / KAK',
        'CSMS' => 'CSMS (Sertifikat)',
        'Nota Dinas Perintah Pekerjaan' => 'Nota Dinas ke Pengadaan',
    ];

    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->boolean('upload_only')->default(false)->after('stage');
        });

        Schema::create('checklist_item_format_exclusions', function (Blueprint $table): void {
            $table->id();
            // Named explicitly: the generated names exceed MySQL's 64 characters.
            $table->foreignId('checklist_item_id')
                ->constrained(indexName: 'checklist_format_exclusion_item_fk')
                ->cascadeOnDelete();
            $table->foreignId('contract_number_format_id')
                ->constrained(indexName: 'checklist_format_exclusion_format_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['checklist_item_id', 'contract_number_format_id'], 'checklist_format_exclusion_unique');
        });

        DB::table('document_types')->whereIn('code', self::UPLOAD_ONLY)->update(['upload_only' => true]);

        foreach (self::RENAMES as $from => $to) {
            DB::table('checklist_items')
                ->where('stage', 'perencanaan')
                ->where('name', $from)
                ->update(['name' => $to]);
        }

        $this->addSppl();
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_item_format_exclusions');

        Schema::table('document_types', function (Blueprint $table): void {
            $table->dropColumn('upload_only');
        });

        foreach (self::RENAMES as $from => $to) {
            DB::table('checklist_items')
                ->where('stage', 'perencanaan')
                ->where('name', $to)
                ->update(['name' => $from]);
        }
    }

    /**
     * Add the SPPL format, sharing the unit's numbering shape, and switch the
     * RAB step off for it. Skipped quietly on a database where either is
     * missing, such as a fresh one that is seeded afterwards.
     */
    private function addSppl(): void
    {
        $sppl = DB::table('contract_number_formats')->where('code', 'SPPL')->value('id');

        if ($sppl === null && DB::table('contract_number_formats')->exists()) {
            $sppl = DB::table('contract_number_formats')->insertGetId([
                'code' => 'SPPL',
                'name' => 'SPPL',
                'prefix' => 'KDD',
                'unit_segment' => '612/UPKD',
                'sequence_length' => 3,
                'starting_sequence' => 1,
                'sort_order' => (int) DB::table('contract_number_formats')->max('sort_order') + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($sppl === null) {
            return;
        }

        $rab = DB::table('checklist_items')
            ->join('checklist_item_document_type', 'checklist_item_document_type.checklist_item_id', '=', 'checklist_items.id')
            ->join('document_types', 'document_types.id', '=', 'checklist_item_document_type.document_type_id')
            ->where('document_types.code', 'rab')
            ->where('checklist_items.stage', 'perencanaan')
            ->value('checklist_items.id');

        if ($rab !== null) {
            DB::table('checklist_item_format_exclusions')->insertOrIgnore([
                'checklist_item_id' => $rab,
                'contract_number_format_id' => $sppl,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
