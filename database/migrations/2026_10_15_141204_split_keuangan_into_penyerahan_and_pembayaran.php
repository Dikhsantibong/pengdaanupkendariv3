<?php

use Database\Seeders\FinanceRequirementSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Keuangan tab becomes two: Penyerahan and Pembayaran. Their checklists
 * are the delivery and payment requirements written in each contract
 * template (SPK Barang, SPK Jasa, Surat Perjanjian, Lampiran Surat Pesanan),
 * each applying to its own contract format and goods/services category.
 *
 * The six generic Keuangan steps they replace are switched off rather than
 * deleted: steps already ticked on a procurement stay as its history.
 */
return new class extends Migration
{
    /**
     * The generic steps the requirements replace.
     *
     * @var array<int, string>
     */
    private const REPLACED = [
        'Penerimaan Tagihan',
        'Berita Acara Serah Terima',
        'Faktur Pajak',
        'Verifikasi Dokumen Tagihan',
        'Pengajuan Pembayaran',
        'Pembayaran ke Mitra',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            // Which Keuangan tab the step sits on.
            $table->string('section', 20)->nullable()->after('stage');
            // Goods or services only; empty for both.
            $table->string('work_category', 10)->nullable()->after('section');
        });

        DB::table('checklist_items')
            ->where('stage', 'keuangan')
            ->whereIn('name', self::REPLACED)
            ->update(['is_active' => false, 'updated_at' => now()]);

        // Each requirement belongs to a contract format, so they are only
        // laid out where the formats exist; a fresh database gets them from
        // the seeders after its master data.
        if (DB::table('contract_number_formats')->exists()) {
            (new FinanceRequirementSeeder)->run();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ids = DB::table('checklist_items')->where('stage', 'keuangan')->whereNotNull('section')->pluck('id');

        DB::table('checklist_item_format_exclusions')->whereIn('checklist_item_id', $ids)->delete();
        DB::table('procurement_checklists')->whereIn('checklist_item_id', $ids)->delete();
        DB::table('checklist_items')->whereIn('id', $ids)->delete();

        DB::table('checklist_items')
            ->where('stage', 'keuangan')
            ->whereIn('name', self::REPLACED)
            ->update(['is_active' => true, 'updated_at' => now()]);

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropColumn(['section', 'work_category']);
        });
    }
};
