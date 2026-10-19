<?php

namespace App\Support;

use App\Enums\AmendmentState;
use App\Enums\DeliveryOrderState;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementDeliveryOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The amendments and Delivery Orders waiting on a user, for the Approval
 * menu. The rules are the policy's own (ProcurementPolicy::decideAmendment,
 * issueAmendment, decideDeliveryOrder, forwardDeliveryOrder,
 * issueDeliveryOrder), written as queries:
 *
 * - TL: the request of a proposer they lead, waiting at the TL level;
 * - Asman (Direksi Pekerjaan): a request of their division at the Asman level;
 * - Rendan (DO only): the planning PIC of the job, or a team leader;
 * - Laksdan: the execution PIC of the job, TL Pengadaan or the administrator.
 *
 * Nobody decides on their own request; the administrator may stand in for
 * every step.
 */
class ContractRequestQueue
{
    /**
     * The amendments waiting on the user.
     *
     * @return Builder<ProcurementAmendment>
     */
    public static function amendmentsAwaiting(User $user): Builder
    {
        $query = ProcurementAmendment::query()->whereHas('procurement', fn (Builder $procurement) => $procurement->whereNull('deleted_at'));

        if ($user->role === UserRole::Administrator) {
            return $query->whereIn('state', [
                AmendmentState::MenungguPersetujuanTl->value,
                AmendmentState::MenungguPersetujuanAsman->value,
                AmendmentState::MenungguTlPengadaan->value,
            ]);
        }

        return $query->where(function (Builder $query) use ($user): void {
            self::approvals($query, $user, AmendmentState::MenungguPersetujuanTl->value, AmendmentState::MenungguPersetujuanAsman->value);
            self::onSide($query, $user, AmendmentState::MenungguTlPengadaan->value, 'executor_id', self::leadsExecution($user));
        });
    }

    /**
     * The Delivery Orders waiting on the user.
     *
     * @return Builder<ProcurementDeliveryOrder>
     */
    public static function deliveryOrdersAwaiting(User $user): Builder
    {
        $query = ProcurementDeliveryOrder::query()->whereHas('procurement', fn (Builder $procurement) => $procurement->whereNull('deleted_at'));

        if ($user->role === UserRole::Administrator) {
            return $query->whereIn('state', [
                DeliveryOrderState::MenungguPersetujuanTl->value,
                DeliveryOrderState::MenungguPersetujuanAsman->value,
                DeliveryOrderState::DiRendan->value,
                DeliveryOrderState::DiLaksdan->value,
            ]);
        }

        return $query->where(function (Builder $query) use ($user): void {
            self::approvals($query, $user, DeliveryOrderState::MenungguPersetujuanTl->value, DeliveryOrderState::MenungguPersetujuanAsman->value);
            self::onSide($query, $user, DeliveryOrderState::DiRendan->value, 'planner_id', $user->isSupervisor());
            self::onSide($query, $user, DeliveryOrderState::DiLaksdan->value, 'executor_id', self::leadsExecution($user));
        });
    }

    /**
     * The amendments the user decided or issued, latest first.
     *
     * @return Builder<ProcurementAmendment>
     */
    public static function amendmentsDecided(User $user): Builder
    {
        return ProcurementAmendment::query()
            ->where(fn (Builder $query) => $query
                ->where('tl_reviewed_by', $user->id)
                ->orWhere('director_reviewed_by', $user->id)
                ->orWhere('issued_by', $user->id))
            ->latest('updated_at')
            ->latest('id');
    }

    /**
     * The Delivery Orders the user decided, forwarded or issued, latest first.
     *
     * @return Builder<ProcurementDeliveryOrder>
     */
    public static function deliveryOrdersDecided(User $user): Builder
    {
        return ProcurementDeliveryOrder::query()
            ->where(fn (Builder $query) => $query
                ->where('tl_reviewed_by', $user->id)
                ->orWhere('director_reviewed_by', $user->id)
                ->orWhere('forwarded_by', $user->id)
                ->orWhere('issued_by', $user->id))
            ->latest('updated_at')
            ->latest('id');
    }

    /**
     * Whether the user takes part in amendments or Delivery Orders at all:
     * then the Approval menu shows them, whatever its own right says.
     */
    public static function involves(User $user): bool
    {
        return self::involvesIn($user, 'amendment') || self::involvesIn($user, 'delivery-order');
    }

    /**
     * Whether the user takes part in one kind of request: as TL of a
     * proposer, as Asman, as one who issues them (Laksdan) or forwards a DO
     * (Rendan), or through a request waiting on or decided by them.
     *
     * @param  'amendment'|'delivery-order'  $kind
     */
    public static function involvesIn(User $user, string $kind): bool
    {
        $amendments = $kind === 'amendment';

        return self::leadsExecution($user)
            // Rendan, for a DO: every team leader may write its memo.
            || (! $amendments && $user->isSupervisor())
            || ($user->work_director_id !== null && $user->hasPermission(Permission::ApproveProposal))
            || User::query()->where('team_leader_id', $user->id)->exists()
            || ($amendments ? self::amendmentsAwaiting($user) : self::deliveryOrdersAwaiting($user))->exists()
            || ($amendments ? self::amendmentsDecided($user) : self::deliveryOrdersDecided($user))->exists();
    }

    /**
     * The TL and Asman levels: the TL of the proposer, then the Asman of
     * the job's division, never on their own request.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    protected static function approvals(Builder $query, User $user, string $atTeamLeader, string $atDirector): void
    {
        $query->orWhere(fn (Builder $step) => $step
            ->where('state', $atTeamLeader)
            ->where('created_by', '!=', $user->id)
            ->whereHas('creator', fn (Builder $creator) => $creator->where('team_leader_id', $user->id)));

        if ($user->work_director_id !== null && $user->hasPermission(Permission::ApproveProposal)) {
            $query->orWhere(fn (Builder $step) => $step
                ->where('state', $atDirector)
                ->where('created_by', '!=', $user->id)
                ->whereHas('procurement', fn (Builder $procurement) => $procurement->where('work_director_id', $user->work_director_id)));
        }
    }

    /**
     * A step done by one side of the procurement team: everyone leading it,
     * or the PIC of that side on the job.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    protected static function onSide(Builder $query, User $user, string $state, string $picColumn, bool $leadsSide): void
    {
        $query->orWhere(fn (Builder $step) => $step
            ->where('state', $state)
            ->when(! $leadsSide, fn (Builder $mine) => $mine
                ->whereHas('procurement', fn (Builder $procurement) => $procurement->where($picColumn, $user->id))));
    }

    /**
     * Whether the user works on the execution of every job.
     */
    protected static function leadsExecution(User $user): bool
    {
        return in_array($user->role, [UserRole::Administrator, UserRole::TeamLeaderPengadaan], true);
    }
}
