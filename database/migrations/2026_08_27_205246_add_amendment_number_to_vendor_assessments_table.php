<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_assessments', function (Blueprint $table) {
            $table->string('amendment_number', 255)->nullable()->after('po_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_assessments', function (Blueprint $table) {
            $table->dropColumn('amendment_number');
        });
    }
};
