<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->date('proposal_memo_date')->nullable()->after('proposal_memo_number');
            $table->date('icc_memo_date')->nullable()->after('icc_memo_number');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn(['proposal_memo_date', 'icc_memo_date']);
        });
    }
};
