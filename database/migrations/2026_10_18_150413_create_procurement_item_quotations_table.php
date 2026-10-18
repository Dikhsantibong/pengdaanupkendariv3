<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The unit price each calon mitra quoted for each item of the Rincian Item,
 * compared side by side in the Evaluasi Harga.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_item_quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_contract_item_id')
                ->constrained(indexName: 'item_quotations_item_fk')
                ->cascadeOnDelete();
            $table->foreignId('procurement_partner_candidate_id')
                ->constrained(indexName: 'item_quotations_candidate_fk')
                ->cascadeOnDelete();
            $table->decimal('unit_price', 20, 2);
            $table->timestamps();

            $table->unique(['procurement_contract_item_id', 'procurement_partner_candidate_id'], 'item_quotations_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_item_quotations');
    }
};
