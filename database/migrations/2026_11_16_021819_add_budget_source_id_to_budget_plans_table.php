<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->foreignId('budget_source_id')
                ->nullable()
                ->after('budget_type')
                ->constrained('budget_sources')
                ->nullOnDelete();
        });

        $aiId = DB::table('budget_sources')->where('code', 'AI')->whereNull('deleted_at')->value('id');
        $aoId = DB::table('budget_sources')->where('code', 'AO')->whereNull('deleted_at')->value('id');

        if ($aiId !== null) {
            DB::table('budget_plans')
                ->whereNull('budget_source_id')
                ->where(function ($query): void {
                    $query->where('budget_type', 'investasi')->orWhereNull('budget_type');
                })
                ->update(['budget_source_id' => $aiId]);
        }

        if ($aoId !== null) {
            DB::table('budget_plans')
                ->whereNull('budget_source_id')
                ->where('budget_type', 'operasi')
                ->update(['budget_source_id' => $aoId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_source_id');
        });
    }
};
