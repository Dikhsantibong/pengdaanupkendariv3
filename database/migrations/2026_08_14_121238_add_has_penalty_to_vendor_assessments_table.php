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
        Schema::table('vendor_assessments', function (Blueprint $table) {
            $table->boolean('has_penalty')->default(false)->after('vendor_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_assessments', function (Blueprint $table) {
            $table->dropColumn('has_penalty');
        });
    }
};
