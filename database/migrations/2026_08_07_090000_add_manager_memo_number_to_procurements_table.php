<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The manager's memo number that hands the work over to procurement.
 *
 * Free text rather than a lookup: the number is issued outside this system by
 * the manager's office, so there is no list to pick it from. The planning PIC
 * copies it in once the memo reaches them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->string('manager_memo_number')->nullable()->after('contract_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn('manager_memo_number');
        });
    }
};
