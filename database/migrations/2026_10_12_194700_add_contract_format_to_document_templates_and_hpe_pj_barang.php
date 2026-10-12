<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a template be limited to one contract number format (SPK, PJ, SPPL),
 * and add the HPE used by PJ contracts for goods.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->foreignId('contract_number_format_id')
                ->nullable()
                ->after('procurement_method_id')
                ->constrained(indexName: 'document_templates_contract_format_foreign')
                ->nullOnDelete();
        });

        // The PJ templates come from the template library (group pj).
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropForeign('document_templates_contract_format_foreign');
            $table->dropColumn('contract_number_format_id');
        });
    }
};
