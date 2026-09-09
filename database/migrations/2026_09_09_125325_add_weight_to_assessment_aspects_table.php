<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The percentage weight each aspect carries on the performance certificate.
 *
 * Stored as a whole percent (0-100). The certificate multiplies an aspect's
 * average level by this weight, and the weights across the active aspects are
 * meant to add up to 100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_aspects', function (Blueprint $table): void {
            $table->unsignedInteger('weight')->default(0)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_aspects', function (Blueprint $table): void {
            $table->dropColumn('weight');
        });
    }
};
