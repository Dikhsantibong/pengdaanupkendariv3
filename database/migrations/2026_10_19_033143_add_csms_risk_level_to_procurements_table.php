<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CSMS risk level (Low, Moderat, High, Extreme) the user states when
 * proposing or registering a job.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->string('csms_risk_level', 20)->nullable()->after('contract_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn('csms_risk_level');
        });
    }
};
