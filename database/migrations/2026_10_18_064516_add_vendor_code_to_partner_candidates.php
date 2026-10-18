<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The partner's vendor code (kode rekanan), entered with each calon mitra.
 * The picked partner's code is carried onto the procurement, where the SPK
 * and the Delivery Orders print it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_partner_candidates', function (Blueprint $table) {
            $table->string('vendor_code', 100)->nullable()->after('name');
        });

        Schema::table('procurements', function (Blueprint $table) {
            $table->string('partner_vendor_code', 100)->nullable()->after('partner_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn('partner_vendor_code');
        });

        Schema::table('procurement_partner_candidates', function (Blueprint $table) {
            $table->dropColumn('vendor_code');
        });
    }
};
