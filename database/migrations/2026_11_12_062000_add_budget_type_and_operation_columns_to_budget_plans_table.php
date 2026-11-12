<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds budget type (Investasi / AI, Operasi / AO Pemeliharaan, Energi Primer)
 * and the specific columns for AO & Energi Primer: pagu, sisa pagu,
 * rencana disburse, realisasi, and notes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->string('budget_type', 50)->default('investasi')->after('district');
            $table->decimal('pagu_value', 20, 2)->nullable()->after('sub_program_description');
            $table->decimal('remaining_pagu_value', 20, 2)->nullable()->after('pagu_value');
            $table->string('disburse_plan', 50)->nullable()->after('remaining_pagu_value');
            $table->decimal('realization_value', 20, 2)->nullable()->after('disburse_plan');
            $table->text('notes')->nullable()->after('payment_progress');

            $table->index('budget_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropIndex(['budget_type']);
            $table->dropColumn([
                'budget_type',
                'pagu_value',
                'remaining_pagu_value',
                'disburse_plan',
                'realization_value',
                'notes',
            ]);
        });
    }
};
