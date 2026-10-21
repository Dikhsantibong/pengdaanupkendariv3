<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Direksi Pekerjaan a vendor names on the request link, carried over to
 * the assessment opened from it. Requests sent before stay without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_assessment_requests', function (Blueprint $table): void {
            $table->string('work_director_name')->nullable()->after('vendor_name');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_assessment_requests', function (Blueprint $table): void {
            $table->dropColumn('work_director_name');
        });
    }
};
