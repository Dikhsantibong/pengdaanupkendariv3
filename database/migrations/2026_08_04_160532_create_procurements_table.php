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
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('name');
            $table->foreignId('work_director_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('pr_ro_number_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prk_number')->nullable();
            $table->decimal('hpe_value', 20, 2)->default(0);
            $table->foreignId('progress_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('planner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('planning_approval_state')->default('belum_diajukan');
            $table->timestamp('planning_submitted_at')->nullable();
            $table->timestamp('planning_reviewed_at')->nullable();
            $table->foreignId('planning_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('planning_review_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['progress_status_id', 'created_at']);
            $table->index('planner_id');
            $table->index('executor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
