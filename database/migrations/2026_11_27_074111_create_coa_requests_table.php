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
        Schema::create('coa_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('work_director_id')->nullable()->constrained('work_directors')->nullOnDelete();
            $table->string('segment_1', 32);
            $table->string('segment_2', 32)->default('KD');
            $table->string('segment_3', 32);
            $table->string('segment_4', 32);
            $table->string('segment_5', 32);
            $table->string('segment_6', 32);
            $table->string('segment_7', 32);
            $table->string('expense_element', 64);
            $table->string('coa_code', 120)->index();
            $table->string('description', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coa_requests');
    }
};
