<?php

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every staff proposal is decided by the staff member's team leader before
 * its Direksi Pekerjaan. Only the staff of K3 KAM and Lingkungan, whose TL is
 * the division's sole Direksi Pekerjaan, go straight to that TL.
 *
 * Some staff proposals skipped their TL: the staff account had no team
 * leader set, or its TL held the Direksi Pekerjaan right in a division with
 * an Asman. This links each staff account still without a TL to the one its
 * position names ("Staf Energi Primer" → "TL Energi Primer"), and sends the
 * staff proposals now waiting at the Direksi Pekerjaan level, never decided
 * by a TL, back to that TL.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        User::query()
            ->active()
            ->whereNull('team_leader_id')
            ->whereNotNull('work_director_id')
            ->where('position', 'like', 'Staf %')
            ->get()
            ->each(function (User $staff): void {
                $leader = $staff->teamLeaderByPosition();

                if ($leader !== null) {
                    DB::table('users')->where('id', $staff->id)->update(['team_leader_id' => $leader->id]);
                }
            });

        $skipped = DB::table('procurements')
            ->join('users as proposers', 'proposers.id', '=', 'procurements.created_by')
            ->join('users as leaders', 'leaders.id', '=', 'proposers.team_leader_id')
            ->whereNull('procurements.deleted_at')
            ->where('procurements.proposal_state', 'menunggu_persetujuan')
            ->whereNull('procurements.proposal_tl_reviewed_at')
            ->where('leaders.is_active', true)
            ->whereNull('leaders.deleted_at')
            ->whereColumn('leaders.id', '!=', 'proposers.id')
            ->get(['procurements.id', 'procurements.work_director_id', 'procurements.created_by', 'leaders.id as leader_id', 'leaders.name as leader_name']);

        foreach ($skipped as $row) {
            $leader = User::query()->whereKey((int) $row->leader_id)->first();

            // The TL of K3 KAM / Lingkungan decides at the Direksi level.
            if ($leader === null || $this->soleDivisionApprover($leader, (int) $row->work_director_id, (int) $row->created_by)) {
                continue;
            }

            DB::table('procurements')->where('id', $row->id)->update([
                'proposal_state' => 'menunggu_persetujuan_tl',
                'updated_at' => now(),
            ]);

            DB::table('procurement_activities')->insert([
                'procurement_id' => $row->id,
                'user_id' => null,
                'type' => 'usulan_diajukan',
                'description' => "Usulan pekerjaan dialihkan ke TL {$row->leader_name} untuk disetujui lebih dulu sebelum Direksi Pekerjaan.",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: the proposals moved back are decided through the
     * normal flow.
     */
    public function down(): void
    {
        //
    }

    /**
     * Whether the TL is the division's only Direksi Pekerjaan account.
     */
    private function soleDivisionApprover(User $leader, int $divisionId, int $proposerId): bool
    {
        if ($leader->work_director_id !== $divisionId || ! $leader->hasPermission(Permission::ApproveProposal)) {
            return false;
        }

        return ! User::query()
            ->active()
            ->where('work_director_id', $divisionId)
            ->whereKeyNot([$leader->id, $proposerId])
            ->get()
            ->contains(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal));
    }
};
