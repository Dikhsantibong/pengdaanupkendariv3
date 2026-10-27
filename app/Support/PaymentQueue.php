<?php

namespace App\Support;

use App\Enums\PaymentApprovalState;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Procurement;
use App\Models\User;
use App\Services\AccessRights;
use Illuminate\Database\Eloquent\Builder;

/**
 * The payments waiting on, or decided by, a user. Like the amendments and
 * DOs their deciders are not one right but the people around the
 * submitter: the submitter's TL (TL Keuangan), then the Asman of that TL's
 * division (Asman Business Support). Nobody decides a payment they
 * submitted; the administrator sees every step.
 */
class PaymentQueue
{
    /**
     * The payments waiting on the user's decision.
     *
     * @return Builder<Procurement>
     */
    public static function awaiting(User $user): Builder
    {
        $query = Procurement::query()
            ->whereNotNull('finance_submitted_at')
            ->whereNull('payment_completed_at')
            ->where(fn (Builder $mine) => $mine
                ->whereNull('payment_submitted_by')
                ->orWhere('payment_submitted_by', '!=', $user->id));

        if ($user->isAdministrator()) {
            return $query->whereIn('payment_approval_state', [
                PaymentApprovalState::MenungguTl->value,
                PaymentApprovalState::MenungguAsman->value,
            ]);
        }

        return $query->where(function (Builder $steps) use ($user): void {
            $steps->where(fn (Builder $step) => $step
                ->where('payment_approval_state', PaymentApprovalState::MenungguTl->value)
                ->where('payment_team_leader_id', $user->id));

            if (self::leadsDivision($user)) {
                $steps->orWhere(fn (Builder $step) => $step
                    ->where('payment_approval_state', PaymentApprovalState::MenungguAsman->value)
                    ->where('payment_work_director_id', $user->work_director_id));
            }
        });
    }

    /**
     * The payments the user approved (at either step) or returned.
     *
     * @return Builder<Procurement>
     */
    public static function decided(User $user): Builder
    {
        return Procurement::query()
            ->where(fn (Builder $decided) => $decided
                ->where('payment_tl_reviewed_by', $user->id)
                ->orWhere('payment_reviewed_by', $user->id));
    }

    /**
     * Whether the user takes part in deciding payments at all: then the
     * Approval menu shows them the Pembayaran queue.
     */
    public static function involves(User $user): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        // The roles that pay partners; the administrator holds every right.
        $payers = array_values(array_unique([
            UserRole::Administrator->value,
            ...array_keys(array_filter(
                AccessRights::grants(),
                fn (array $rights): bool => in_array(Permission::ProcessPayment->value, $rights, true),
            )),
        ]));

        return User::query()->active()->where('team_leader_id', $user->id)->whereIn('role', $payers)->exists()
            || (self::leadsDivision($user)
                && User::query()->active()->where('work_director_id', $user->work_director_id)->whereIn('role', $payers)->exists())
            || self::awaiting($user)->exists()
            || self::decided($user)->exists();
    }

    /**
     * Whether the user is the Asman of a division.
     */
    protected static function leadsDivision(User $user): bool
    {
        return $user->work_director_id !== null && $user->hasPermission(Permission::ApproveProposal);
    }
}
