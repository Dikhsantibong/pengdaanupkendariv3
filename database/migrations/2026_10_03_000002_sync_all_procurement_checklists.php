<?php

use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Synchronizes the checklists of all existing procurements if checklist items exist.
     */
    public function up(): void
    {
        if (! Schema::hasTable('procurements') || ! Schema::hasTable('checklist_items')) {
            return;
        }

        if (! DB::table('checklist_items')->where('stage', 'pelaksanaan')->exists()) {
            return;
        }

        $service = app(ProcurementService::class);

        foreach (Procurement::all() as $procurement) {
            $service->syncChecklists($procurement);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive
    }
};
