<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both columns are nullable so procurements registered before this release
     * keep loading; the form requires them from now on.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->foreignId('procurement_method_id')
                ->nullable()
                ->after('target_unit_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('budget_source_id')
                ->nullable()
                ->after('procurement_method_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_method_id');
            $table->dropConstrainedForeignId('budget_source_id');
        });
    }
};
