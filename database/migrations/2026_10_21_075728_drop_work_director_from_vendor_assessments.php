<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Direksi Pekerjaan is named on the vendor's request, not on the
 * assessment itself: the column added for it is taken off again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vendor_assessments', 'work_director_name')) {
            Schema::table('vendor_assessments', function (Blueprint $table): void {
                $table->dropColumn('work_director_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_assessments', function (Blueprint $table): void {
            $table->string('work_director_name')->nullable()->after('vendor_name');
        });
    }
};
