<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A template may target a single procurement method. Leaving the column
     * null keeps the template as the general fallback for its document type.
     */
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->foreignId('procurement_method_id')
                ->nullable()
                ->after('document_type_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index(['document_type_id', 'procurement_method_id', 'is_active'], 'document_templates_resolution_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropIndex('document_templates_resolution_index');
            $table->dropConstrainedForeignId('procurement_method_id');
        });
    }
};
