<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\ProposalState;
use App\Enums\UserRole;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Database\Eloquent\Builder;

/**
 * The work proposals a user decides, as one rule for every place they are
 * listed (menu Usulan Pekerjaan, menu Approval, dashboard): the proposer's
 * TL first, then the division's Direksi Pekerjaan; a TL leading K3 KAM or
 * Lingkungan, or of a division without an Asman account, decides last. The
 * administrator sees both levels, and nobody decides their own proposal.
 */
class ProposalQueue
{
    /**
     * The proposals waiting on the user's decision.
     *
     * @return Builder<Procurement>
     */
    public static function awaiting(User $user): Builder
    {
        // An incomplete proposal is still with its proposer.
        $query = Procurement::query()->whereNotNull('proposal_completed_at');

        if ($user->role === UserRole::Administrator) {
            return $query->whereIn('proposal_state', [
                ProposalState::MenungguPersetujuanTl->value,
                ProposalState::MenungguPersetujuan->value,
            ]);
        }

        return $query
            ->where('created_by', '!=', $user->id)
            ->where(fn (Builder $levels) => $levels
                ->where(fn (Builder $team) => $team
                    ->where('proposal_state', ProposalState::MenungguPersetujuanTl->value)
                    ->whereIn('created_by', User::query()->select('id')->where('team_leader_id', $user->id)))
                ->when($user->hasPermission(Permission::ApproveProposal), fn (Builder $levels) => $levels
                    ->orWhere(fn (Builder $division) => $division
                        ->where('proposal_state', ProposalState::MenungguPersetujuan->value)
                        ->where('work_director_id', $user->work_director_id ?? 0)))
                // A division without a Direksi Pekerjaan account: its TL decides.
                ->orWhereIn('id', app(ProcurementService::class)->proposalsLeftToTeamLeader($user)));
    }

    /**
     * The proposals the user approved (at either level) or returned.
     *
     * @return Builder<Procurement>
     */
    public static function decided(User $user): Builder
    {
        return Procurement::query()
            ->where(fn (Builder $query) => $query
                ->where('proposal_reviewed_by', $user->id)
                ->orWhere('proposal_tl_reviewed_by', $user->id));
    }

    /**
     * Whether the user decides proposals at all: a division's Direksi
     * Pekerjaan, a TL with staff, or the administrator.
     */
    public static function involves(User $user): bool
    {
        return $user->role === UserRole::Administrator
            || $user->hasPermission(Permission::ApproveProposal)
            || $user->leadsTeam()
            || self::decided($user)->exists();
    }
}
