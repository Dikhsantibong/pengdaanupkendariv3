<?php

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The journey after the Asman's approval becomes:
 *
 * - TL ICC appoints the PIC Perencana and approves the planning.
 * - TL Pengadaan then appoints the PIC Pelaksana, and approves the execution
 *   the PIC submits before the job moves on to Penyerahan and Pembayaran.
 *
 * The single "assign PIC" right keeps its value as the PIC Pelaksana right,
 * so whoever holds it now keeps appointing executors. Two rights are new.
 */
return new class extends Migration
{
    /**
     * The new rights and the roles that hold them by default.
     *
     * @var array<string, array<int, string>>
     */
    private const GRANTS = [
        'procurement.assign-planner' => ['team_leader_icc'],
        'procurement.review-execution' => ['team_leader_pengadaan'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->string('execution_approval_state')->default(PlanningApprovalState::BelumDiajukan->value)->after('planning_revision');
            $table->timestamp('execution_submitted_at')->nullable()->after('execution_approval_state');
            $table->timestamp('execution_reviewed_at')->nullable()->after('execution_submitted_at');
            $table->foreignId('execution_reviewed_by')->nullable()->after('execution_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('execution_review_note')->nullable()->after('execution_reviewed_by');
            $table->unsignedInteger('execution_revision')->default(0)->after('execution_review_note');
        });

        // Jobs already handed to Keuangan went through execution without this
        // approval; their hand-over stands for it, so nothing reopens.
        DB::table('procurements')
            ->whereNotNull('finance_submitted_at')
            ->update([
                'execution_approval_state' => PlanningApprovalState::Disetujui->value,
                'execution_submitted_at' => DB::raw('finance_submitted_at'),
                'execution_reviewed_at' => DB::raw('finance_submitted_at'),
                'execution_reviewed_by' => DB::raw('finance_submitted_by'),
            ]);

        foreach (self::GRANTS as $permission => $roles) {
            foreach ($roles as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $role,
                    'permission' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Cache::forget('access-rights.grants');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('execution_reviewed_by');
            $table->dropColumn([
                'execution_approval_state',
                'execution_submitted_at',
                'execution_reviewed_at',
                'execution_review_note',
                'execution_revision',
            ]);
        });

        DB::table('role_permissions')
            ->whereIn('permission', [Permission::AssignPlanner->value, Permission::ReviewExecution->value])
            ->where('role', '!=', UserRole::Administrator->value)
            ->delete();

        Cache::forget('access-rights.grants');
    }
};
