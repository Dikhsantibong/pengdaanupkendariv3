<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Tanggal Terbit of the TOR, given by the user who proposes or registers
 * the job. Left empty, the TOR carries the day it was generated.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->date('tor_issue_date')->nullable()->after('csms_risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn('tor_issue_date');
        });
    }
};
