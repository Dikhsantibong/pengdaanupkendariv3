<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the Anggaran sheet on each PRK: its district, the contract
 * (PO Ellipse, supplier, value, delivery), the physical progress and BA, and
 * the payment (invoice and proof of payment).
 *
 * Every column is optional, so the rows already entered stay valid.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->string('district', 50)->nullable()->after('id');
            // Kontrak.
            $table->string('po_stock_type', 50)->nullable()->after('pr_number');
            $table->text('po_number')->nullable()->after('po_stock_type');
            $table->string('allocation', 50)->nullable()->after('po_number');
            $table->string('po_item', 100)->nullable()->after('allocation');
            $table->string('supplier')->nullable()->after('po_item');
            $table->date('po_date')->nullable()->after('supplier');
            $table->decimal('po_value', 20, 2)->nullable()->after('po_date');
            $table->date('delivery_date')->nullable()->after('po_value');
            $table->text('contract_progress')->nullable()->after('delivery_date');
            // Fisik / BA.
            $table->text('physical_progress')->nullable()->after('contract_progress');
            // Pembayaran: several invoices may be paid on several days.
            $table->decimal('invoice_value', 20, 2)->nullable()->after('physical_progress');
            $table->text('invoice_number')->nullable()->after('invoice_value');
            $table->text('payment_dates')->nullable()->after('invoice_number');
            $table->text('payment_proof_number')->nullable()->after('payment_dates');
            $table->text('payment_progress')->nullable()->after('payment_proof_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropColumn([
                'district', 'po_stock_type', 'po_number', 'allocation', 'po_item', 'supplier',
                'po_date', 'po_value', 'delivery_date', 'contract_progress', 'physical_progress',
                'invoice_value', 'invoice_number', 'payment_dates', 'payment_proof_number', 'payment_progress',
            ]);
        });
    }
};
