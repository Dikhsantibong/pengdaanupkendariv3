<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A checklist item can belong to one procurement.
 *
 * The Penyerahan and Pembayaran requirements are read from each
 * procurement's own contract, which may have been worded differently from
 * the template. Those requirements are kept as checklist items owned by that
 * procurement: they run through the same checklist as every other step but
 * never appear on Data Master or on another procurement.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->foreignId('procurement_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // A contract's item can run longer than a master data name.
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->string('name', 500)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The requirements read from a contract go with the column.
        DB::table('checklist_items')->whereNotNull('procurement_id')->delete();

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('procurement_id');
        });

        $mysql = DB::getDriverName() === 'mysql';

        DB::table('checklist_items')
            ->whereRaw(($mysql ? 'char_length' : 'length').'(name) > 255')
            ->update(['name' => DB::raw($mysql ? 'left(name, 255)' : 'substr(name, 1, 255)')]);

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->string('name', 255)->change();
        });
    }
};
