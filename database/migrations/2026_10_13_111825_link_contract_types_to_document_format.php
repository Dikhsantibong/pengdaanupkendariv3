<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The category asked for SPK and PJ now comes from the Jenis Kontrak master
 * data instead of a fixed barang/jasa choice. Each jenis kontrak says which
 * document format (barang or jasa) it uses, so the matching SPK/PJ template
 * is still picked. Barang and Jasa are added as jenis kontrak so that the
 * procurements already categorised keep their format.
 */
return new class extends Migration
{
    /**
     * The jenis kontrak that carry the former barang/jasa choice.
     *
     * @var array<string, string>
     */
    private const CATEGORIES = ['barang' => 'Barang', 'jasa' => 'Jasa'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contract_types', function (Blueprint $table) {
            $table->string('work_category', 10)->nullable()->after('description');
        });

        $sortOrder = (int) DB::table('contract_types')->max('sort_order');

        foreach (self::CATEGORIES as $category => $name) {
            $existing = DB::table('contract_types')->where('code', $category)->whereNull('deleted_at')->first();

            if ($existing === null) {
                DB::table('contract_types')->insert([
                    'code' => $category,
                    'name' => $name,
                    'description' => "Format dokumen {$name}.",
                    'work_category' => $category,
                    'sort_order' => ++$sortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('contract_types')->where('id', $existing->id)->update(['work_category' => $category]);
            }

            $typeId = DB::table('contract_types')->where('code', $category)->whereNull('deleted_at')->value('id');

            DB::table('procurements')
                ->whereNull('contract_type_id')
                ->where('work_category', $category)
                ->update(['contract_type_id' => $typeId]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The added jenis kontrak stay: procurements may already point at them.
     */
    public function down(): void
    {
        Schema::table('contract_types', function (Blueprint $table) {
            $table->dropColumn('work_category');
        });
    }
};
