<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last purchase of an item (its PO number and date, the supplier and
 * the price paid), printed under "Harga" in the Evaluasi Harga.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_contract_items', function (Blueprint $table): void {
            $table->string('previous_po_number', 100)->nullable()->after('unit_price');
            $table->string('previous_po_date', 50)->nullable()->after('previous_po_number');
            $table->string('previous_supplier', 150)->nullable()->after('previous_po_date');
            $table->decimal('previous_price', 20, 2)->nullable()->after('previous_supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_contract_items', function (Blueprint $table): void {
            $table->dropColumn(['previous_po_number', 'previous_po_date', 'previous_supplier', 'previous_price']);
        });
    }
};
