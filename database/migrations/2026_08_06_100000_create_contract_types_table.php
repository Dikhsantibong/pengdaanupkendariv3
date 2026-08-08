<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kind of contract a procurement will be awarded under.
 *
 * Modular like the other reference data: an administrator adds, renames or
 * deactivates entries without a code change, and soft deletes keep the
 * history of procurements that already used one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('procurements', function (Blueprint $table): void {
            // Nullable: the planning PIC fills this in after being appointed,
            // so a procurement is registered before its contract type is known.
            $table->foreignId('contract_type_id')->nullable()->after('budget_source_id')
                ->constrained('contract_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_type_id');
        });

        Schema::dropIfExists('contract_types');
    }
};
