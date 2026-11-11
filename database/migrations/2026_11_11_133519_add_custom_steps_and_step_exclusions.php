<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The steps of one procurement can be adjusted on that procurement alone.
 *
 * A step added by hand is a checklist item owned by the procurement and
 * marked custom, so the contract reader (which owns the other per-procurement
 * items) never switches it off. A step removed by hand is recorded as an
 * exclusion of that item for that procurement: Data Master and every other
 * procurement keep it, and the step comes back the moment the exclusion is
 * lifted.
 *
 * Nothing existing changes: every current item stays non-custom and no step
 * is excluded. The new right goes to TL Pengadaan and TL ICC, who approve the
 * stages.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->boolean('is_custom')->default(false)->after('procurement_id');
        });

        Schema::create('procurement_step_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Named short: MySQL refuses the generated name as too long.
            $table->unique(['procurement_id', 'checklist_item_id'], 'step_exclusions_unique');
        });

        foreach (['team_leader_pengadaan', 'team_leader_icc'] as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'role' => $role,
                'permission' => 'procurement.manage-steps',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('access-rights.grants');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'procurement.manage-steps')->delete();
        Cache::forget('access-rights.grants');

        Schema::dropIfExists('procurement_step_exclusions');

        // The steps added by hand go with the column.
        DB::table('checklist_items')->where('is_custom', true)->delete();

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropColumn('is_custom');
        });
    }
};
