<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyerahan and Pembayaran each go through an approval of their own.
 *
 * - Penyerahan: the execution PIC files the hand-over papers and submits
 *   them to TL Pengadaan, who approves them on to Pembayaran or returns them.
 * - Pembayaran: a Keuangan staff member files the payment papers and submits
 *   them to their TL (TL Keuangan), then the Asman of that TL's division
 *   (Asman Business Support). The Asman's approval records the payment and
 *   closes the job.
 *
 * Rights:
 * - "Setujui / kembalikan penyerahan" goes to whoever approves the execution
 *   (TL Pengadaan by default).
 * - The Penyerahan menu opens to TL Pengadaan, TL ICC and the execution PIC.
 * - Keuangan no longer proposes work and gains sight of every procurement.
 *
 * Jobs already with Keuangan and not yet paid keep going: their Penyerahan
 * counts as approved, so they wait for the payment to be submitted. Paid
 * ones count as approved throughout.
 */
return new class extends Migration
{
    /**
     * Rights given, each to the roles listed.
     *
     * @var array<string, array<int, string>>
     */
    private const GRANTS = [
        'menu.handover' => ['team_leader_pengadaan', 'team_leader_icc', 'pic_pelaksana'],
        'procurement.view-all' => ['keuangan'],
    ];

    /**
     * Rights taken from Keuangan: it pays, it no longer proposes work.
     *
     * @var array<int, string>
     */
    private const KEUANGAN_DROPS = ['procurement.propose', 'menu.proposals'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->string('handover_approval_state')->default('belum_diajukan')->after('finance_submitted_by');
            $table->timestamp('handover_submitted_at')->nullable()->after('handover_approval_state');
            $table->foreignId('handover_submitted_by')->nullable()->after('handover_submitted_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('handover_reviewed_at')->nullable()->after('handover_submitted_by');
            $table->foreignId('handover_reviewed_by')->nullable()->after('handover_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('handover_review_note')->nullable()->after('handover_reviewed_by');
            $table->unsignedInteger('handover_revision')->default(0)->after('handover_review_note');

            $table->string('payment_approval_state')->default('belum_diajukan')->after('handover_revision');
            $table->timestamp('payment_submitted_at')->nullable()->after('payment_approval_state');
            $table->foreignId('payment_submitted_by')->nullable()->after('payment_submitted_at')
                ->constrained('users')->nullOnDelete();
            // Who decides, fixed when it is submitted: the submitter's TL and
            // the division of the Asman after them.
            $table->foreignId('payment_team_leader_id')->nullable()->after('payment_submitted_by')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('payment_work_director_id')->nullable()->after('payment_team_leader_id')
                ->constrained('work_directors')->nullOnDelete();
            $table->timestamp('payment_tl_reviewed_at')->nullable()->after('payment_work_director_id');
            $table->foreignId('payment_tl_reviewed_by')->nullable()->after('payment_tl_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('payment_reviewed_at')->nullable()->after('payment_tl_reviewed_by');
            $table->foreignId('payment_reviewed_by')->nullable()->after('payment_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('payment_review_note')->nullable()->after('payment_reviewed_by');
            $table->unsignedInteger('payment_revision')->default(0)->after('payment_review_note');
        });

        // Already with Keuangan: the hand-over stands as approved.
        DB::table('procurements')
            ->whereNotNull('finance_submitted_at')
            ->update([
                'handover_approval_state' => 'disetujui',
                'handover_submitted_at' => DB::raw('finance_submitted_at'),
                'handover_reviewed_at' => DB::raw('finance_submitted_at'),
                'handover_reviewed_by' => DB::raw('finance_submitted_by'),
            ]);

        // Paid: the payment stands as approved by whoever recorded it.
        DB::table('procurements')
            ->whereNotNull('payment_completed_at')
            ->update([
                'payment_approval_state' => 'disetujui',
                'payment_submitted_at' => DB::raw('payment_completed_at'),
                'payment_submitted_by' => DB::raw('payment_completed_by'),
                'payment_reviewed_at' => DB::raw('payment_completed_at'),
                'payment_reviewed_by' => DB::raw('payment_completed_by'),
            ]);

        // Whoever approves the execution approves the hand-over too.
        $grants = self::GRANTS;
        $grants['procurement.review-handover'] = DB::table('role_permissions')
            ->where('permission', 'procurement.review-execution')
            ->pluck('role')
            ->all();

        foreach ($grants as $permission => $roles) {
            foreach ($roles as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $role,
                    'permission' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('role_permissions')
            ->where('role', 'keuangan')
            ->whereIn('permission', self::KEUANGAN_DROPS)
            ->delete();

        Cache::forget('access-rights.grants');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission', ['menu.handover', 'procurement.review-handover'])
            ->delete();
        DB::table('role_permissions')
            ->where('role', 'keuangan')
            ->where('permission', 'procurement.view-all')
            ->delete();

        foreach (self::KEUANGAN_DROPS as $permission) {
            DB::table('role_permissions')->insertOrIgnore([
                'role' => 'keuangan',
                'permission' => $permission,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('access-rights.grants');

        Schema::table('procurements', function (Blueprint $table) {
            foreach (['handover_submitted_by', 'handover_reviewed_by', 'payment_submitted_by', 'payment_team_leader_id', 'payment_work_director_id', 'payment_tl_reviewed_by', 'payment_reviewed_by'] as $foreign) {
                $table->dropConstrainedForeignId($foreign);
            }

            $table->dropColumn([
                'handover_approval_state', 'handover_submitted_at', 'handover_reviewed_at', 'handover_review_note', 'handover_revision',
                'payment_approval_state', 'payment_submitted_at', 'payment_tl_reviewed_at', 'payment_reviewed_at', 'payment_review_note', 'payment_revision',
            ]);
        });
    }
};
