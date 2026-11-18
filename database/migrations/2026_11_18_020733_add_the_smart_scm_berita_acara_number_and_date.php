<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Proses SMART SCM & Berita Acara" step asks for the number and date of
 * its Berita Acara, kept on the procurement.
 *
 * Only a step that asks for nothing yet gets the input, so one an
 * administrator has set otherwise is left alone; a step already ticked stays
 * ticked.
 */
return new class extends Migration
{
    private const STEP = 'Proses SMART SCM & Berita Acara';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->string('smart_scm_ba_number')->nullable()->after('icc_memo_date');
            $table->date('smart_scm_ba_date')->nullable()->after('smart_scm_ba_number');
        });

        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', self::STEP)
            ->whereNull('input_kind')
            ->update(['input_kind' => 'berita_acara', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->where('name', self::STEP)
            ->where('input_kind', 'berita_acara')
            ->update(['input_kind' => null]);

        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn(['smart_scm_ba_number', 'smart_scm_ba_date']);
        });
    }
};
