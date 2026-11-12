<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The budget plan (Perencanaan Anggaran) the Anggaran role keeps: one row per
 * PRK with its planned AI and AKI, and the WO and PR numbers it carries.
 *
 * Each PRK is meant for one bidang (Direksi Pekerjaan), whose proposers pick
 * it on the work proposal form.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budget_plans', function (Blueprint $table) {
            $table->id();
            $table->string('prk_induk', 100);
            $table->foreignId('work_director_id')->nullable()->constrained('work_directors')->nullOnDelete();
            $table->text('prk_induk_description')->nullable();
            $table->text('sub_program_description')->nullable();
            // Rencana AI / Nilai RAB, and Rencana AKI, in rupiah.
            $table->decimal('ai_plan_value', 20, 2)->nullable();
            $table->decimal('aki_plan_value', 20, 2)->nullable();
            $table->string('wo_number')->nullable();
            $table->string('pr_number')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('prk_induk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_plans');
    }
};
