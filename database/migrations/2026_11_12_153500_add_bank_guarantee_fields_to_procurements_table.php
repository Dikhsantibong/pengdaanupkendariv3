<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Jaminan Bank" execution step now asks for the bank guarantee details
 * (issuing bank, guarantee number, guarantee value, issue date, and expiry
 * date) alongside uploading the validated Jaminan Bank document.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            if (! Schema::hasColumn('procurements', 'guarantee_bank_name')) {
                $table->string('guarantee_bank_name')->nullable()->after('bank_account_holder');
            }

            if (! Schema::hasColumn('procurements', 'guarantee_number')) {
                $table->string('guarantee_number')->nullable()->after('guarantee_bank_name');
            }

            if (! Schema::hasColumn('procurements', 'guarantee_value')) {
                $table->decimal('guarantee_value', 18, 2)->nullable()->after('guarantee_number');
            }

            if (! Schema::hasColumn('procurements', 'guarantee_issue_date')) {
                $table->date('guarantee_issue_date')->nullable()->after('guarantee_value');
            }

            if (! Schema::hasColumn('procurements', 'guarantee_expiry_date')) {
                $table->date('guarantee_expiry_date')->nullable()->after('guarantee_issue_date');
            }
        });

        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Jaminan Bank')
            ->whereNull('input_kind')
            ->update(['input_kind' => 'bank_guarantee', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Jaminan Bank')
            ->where('input_kind', 'bank_guarantee')
            ->update(['input_kind' => null, 'updated_at' => now()]);

        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn([
                'guarantee_bank_name',
                'guarantee_number',
                'guarantee_value',
                'guarantee_issue_date',
                'guarantee_expiry_date',
            ]);
        });
    }
};
