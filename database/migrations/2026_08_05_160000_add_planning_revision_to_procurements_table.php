<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counts how many times the planning stage has gone back for revision.
 *
 * A rejection is not the end of the planning stage, it is a round trip: the
 * PIC fixes what was objected to and submits again. Recording the round makes
 * that loop visible instead of leaving a procurement looking simply refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->unsignedInteger('planning_revision')->default(0)->after('planning_review_note');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn('planning_revision');
        });
    }
};
