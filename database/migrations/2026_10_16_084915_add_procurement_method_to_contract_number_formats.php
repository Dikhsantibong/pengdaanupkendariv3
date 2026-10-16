<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The procurement method is no longer asked on the procurement form: it
 * follows the contract format (SPK by SPK, PJ by tender, SPPL by surat
 * pesanan). Each format names its method, set on the Format No Kontrak
 * master data, and a procurement takes it whenever its format is chosen.
 */
return new class extends Migration
{
    /**
     * The method each seeded format is let by.
     *
     * @var array<string, string>
     */
    private const METHODS = [
        'SPK' => 'spk',
        'PJ' => 'tender',
        'SPPL' => 'surat-pesanan',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contract_number_formats', function (Blueprint $table) {
            $table->foreignId('procurement_method_id')->nullable()->after('uses_work_category')
                ->constrained('procurement_methods')->nullOnDelete();
        });

        foreach (self::METHODS as $format => $method) {
            $methodId = DB::table('procurement_methods')->where('code', $method)->value('id');

            if ($methodId !== null) {
                DB::table('contract_number_formats')->where('code', $format)->update(['procurement_method_id' => $methodId]);
            }
        }

        // Only fill what is missing: a method chosen by hand before stays.
        DB::table('procurements')
            ->whereNull('procurement_method_id')
            ->whereNotNull('contract_number_format_id')
            ->update([
                'procurement_method_id' => DB::raw('(select f.procurement_method_id from contract_number_formats f where f.id = procurements.contract_number_format_id)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_number_formats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_method_id');
        });
    }
};
