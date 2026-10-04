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
        Schema::table('procurements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurements', 'partner_director_name')) {
                $table->string('partner_director_name')->nullable()->after('partner_name');
            }
            if (! Schema::hasColumn('procurements', 'partner_address')) {
                $table->text('partner_address')->nullable()->after('partner_director_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            if (Schema::hasColumn('procurements', 'partner_address')) {
                $table->dropColumn('partner_address');
            }
            if (Schema::hasColumn('procurements', 'partner_director_name')) {
                $table->dropColumn('partner_director_name');
            }
        });
    }
};
