<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a checklist item be switched off for particular procurement methods.
 *
 * Exclusions rather than inclusions: a checklist item applies to every method
 * by default, so adding a new procurement method never silently drops steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_item_method_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procurement_method_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['checklist_item_id', 'procurement_method_id'], 'checklist_method_exclusion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_item_method_exclusions');
    }
};
