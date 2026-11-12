<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The partner's mobile number: on Data Master Mitra, on each calon mitra
 * (given with the offer in planning), and on the procurement once the
 * executor is picked.
 *
 * Each column is only added when missing, so a database that already has it
 * is left as it is.
 */
return new class extends Migration
{
    /**
     * The tables and the column each gets.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const COLUMNS = [
        'partners' => ['phone_number', 'director_name'],
        'procurement_partner_candidates' => ['phone_number', 'director_name'],
        'procurements' => ['partner_phone_number', 'partner_director_name'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => [$column, $after]) {
            if (Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $after): void {
                $blueprint->string($column, 50)->nullable()->after($after);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::COLUMNS as $table => [$column]) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};
