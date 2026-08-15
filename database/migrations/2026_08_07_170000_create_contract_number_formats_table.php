<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shape of a contract number, and which running sequence it draws from.
 *
 * UP Kendari numbers contracts as KDD075.SPK/612/UPKD/2026, where SPK and PJ
 * each keep their own count within a year. The parts are stored rather than
 * hard coded so the unit segment, the padding and the list of kinds can all be
 * changed without a deploy, the same way the other reference data works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_number_formats', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('prefix')->default('KDD');
            $table->string('unit_segment')->default('612/UPKD');
            $table->unsignedTinyInteger('sequence_length')->default(3);
            // Where the count begins. UP Kendari is already partway through
            // the year when this arrives, so SPK picks up at 075 and PJ at 020
            // rather than starting over at 001.
            $table->unsignedInteger('starting_sequence')->default(1);
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('procurements', function (Blueprint $table): void {
            // Nullable so procurements numbered before this existed keep the
            // number they were given.
            $table->foreignId('contract_number_format_id')->nullable()->after('number')
                ->constrained('contract_number_formats')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_number_format_id');
        });

        Schema::dropIfExists('contract_number_formats');
    }
};
