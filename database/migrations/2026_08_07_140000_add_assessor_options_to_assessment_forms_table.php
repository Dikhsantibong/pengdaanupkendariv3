<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The names a sheet may be signed by.
 *
 * Direksi Pekerjaan is represented by one Asman per bidang, so its sheet is
 * signed by whichever of them owns the work rather than by a fixed person.
 * Null keeps the free text box for the sheets that have a standing assessor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->json('assessor_options')->nullable()->after('assessor_name');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->dropColumn('assessor_options');
        });
    }
};
