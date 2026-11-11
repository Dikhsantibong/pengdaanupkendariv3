<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Data Master step added to one procurement that would otherwise skip it
 * (left out for its contract format, method or category). The step itself
 * stays as it is in Data Master; only this procurement takes it on.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_step_additions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Named short: MySQL refuses the generated name as too long.
            $table->unique(['procurement_id', 'checklist_item_id'], 'step_additions_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_step_additions');
    }
};
