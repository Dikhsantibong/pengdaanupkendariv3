<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the groups of a job's item tables are lettered: A, B, C (upper, as
 * every table has been printed so far) or a, b, c (lower).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->string('group_letters', 5)->default('upper');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn('group_letters');
        });
    }
};
