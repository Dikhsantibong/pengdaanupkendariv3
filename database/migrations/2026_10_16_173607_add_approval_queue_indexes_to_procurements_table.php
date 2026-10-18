<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approval queues filter on the approval state and sort on the
 * submission or decision time; these keep them quick on a long history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->index(['planning_approval_state', 'planning_submitted_at'], 'procurements_planning_queue_index');
            $table->index(['planning_approval_state', 'planning_reviewed_at'], 'procurements_planning_decided_index');
            $table->index(['execution_approval_state', 'execution_submitted_at'], 'procurements_execution_queue_index');
            $table->index(['execution_approval_state', 'execution_reviewed_at'], 'procurements_execution_decided_index');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropIndex('procurements_planning_queue_index');
            $table->dropIndex('procurements_planning_decided_index');
            $table->dropIndex('procurements_execution_queue_index');
            $table->dropIndex('procurements_execution_decided_index');
        });
    }
};
