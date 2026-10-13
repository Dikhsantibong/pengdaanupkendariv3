<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A work proposal can carry several PRK, PR, COA, SR and WO numbers. They are
 * kept as one comma separated list per column, so the columns are widened
 * to text and the SR number is added.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->text('prk_number')->nullable()->change();
            $table->text('pr_po_number')->nullable()->change();
            $table->text('coa_number')->nullable()->change();
            $table->text('wo_number')->nullable()->change();
            $table->text('sr_number')->nullable()->after('wo_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn('sr_number');
            $table->string('prk_number')->nullable()->change();
            $table->string('pr_po_number')->nullable()->change();
            $table->string('coa_number')->nullable()->change();
            $table->string('wo_number')->nullable()->change();
        });
    }
};
