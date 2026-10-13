<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use Database\Seeders\FinanceStageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions to the procurement journey:
 *
 * - A staff proposal is first approved by the proposer's team leader
 *   (`users.team_leader_id`), then by the division's Asman.
 * - After execution the job is handed to Keuangan, who pays the partner and
 *   closes the procurement.
 */
return new class extends Migration
{
    /**
     * The new rights and the roles that hold them by default.
     *
     * @var array<string, array<int, string>>
     */
    private const GRANTS = [
        'procurement.process-payment' => ['keuangan'],
        'menu.finance' => ['keuangan', 'team_leader_pengadaan', 'team_leader_icc'],
        'procurement.propose' => ['keuangan'],
        'menu.proposals' => ['keuangan'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_leader_id')->nullable()->after('work_director_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('procurements', function (Blueprint $table) {
            $table->timestamp('proposal_tl_reviewed_at')->nullable()->after('proposal_reviewed_by');
            $table->foreignId('proposal_tl_reviewed_by')->nullable()->after('proposal_tl_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('finance_submitted_at')->nullable()->after('completed_at');
            $table->foreignId('finance_submitted_by')->nullable()->after('finance_submitted_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('payment_completed_at')->nullable()->after('finance_submitted_by');
            $table->foreignId('payment_completed_by')->nullable()->after('payment_completed_at')
                ->constrained('users')->nullOnDelete();
        });

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

        // The Keuangan steps, their documents and the closing status.
        (new FinanceStageSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_completed_by');
            $table->dropColumn('payment_completed_at');
            $table->dropConstrainedForeignId('finance_submitted_by');
            $table->dropColumn('finance_submitted_at');
            $table->dropConstrainedForeignId('proposal_tl_reviewed_by');
            $table->dropColumn('proposal_tl_reviewed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_leader_id');
        });

        DB::table('role_permissions')
            ->whereIn('permission', [Permission::ProcessPayment->value, Permission::MenuFinance->value])
            ->delete();
        DB::table('role_permissions')
            ->where('role', UserRole::Keuangan->value)
            ->delete();

        Cache::forget('access-rights.grants');
    }
};
