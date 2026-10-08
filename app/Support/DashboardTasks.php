<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Collects the work waiting on the signed-in user for the dashboard.
 *
 * Every list follows the same rules as the screens it links to: approvals
 * need the review right, appointments need the assign right, and planning or
 * execution work belongs to the PIC it was handed to.
 */
class DashboardTasks
{
    /**
     * How many rows of each list are sent to the dashboard.
     */
    protected const LIMIT = 6;

    /**
     * Build every task list for a user.
     *
     * @return array<string, array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}|null>
     */
    public static function for(User $user): array
    {
        return [
            'approvals' => $user->hasPermission(Permission::ReviewPlanning)
                ? self::approvals($user)
                : null,
            'assignments' => $user->hasPermission(Permission::AssignPic)
                ? self::assignments($user)
                : null,
            'planning' => self::planning($user),
            'execution' => self::execution($user),
        ];
    }

    /**
     * Planning submissions waiting for a decision.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function approvals(User $user): array
    {
        $query = Procurement::query()
            ->visibleTo($user)
            ->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
            ->with('planner')
            ->oldest('planning_submitted_at');

        return self::collect($query, fn (Procurement $procurement): string => 'Diajukan oleh '
            .($procurement->planner->name ?? 'PIC Perencana')
            .($procurement->planning_revision > 0 ? ' · revisi ke-'.$procurement->planning_revision : ''),
            fn (Procurement $procurement): ?string => $procurement->planning_submitted_at?->toDateTimeString());
    }

    /**
     * Unfinished procurements still missing a PIC.
     *
     * A PIC Pelaksana is only needed once the planning has been approved, so
     * an approved procurement without one is listed even when the planning
     * PIC is already set.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function assignments(User $user): array
    {
        $query = Procurement::query()
            ->visibleTo($user)
            ->inProgress()
            ->where(fn (Builder $inner) => $inner
                ->whereNull('planner_id')
                ->orWhereNull('executor_id'))
            ->oldest('created_at');

        return self::collect($query, function (Procurement $procurement): string {
            $missing = array_filter([
                $procurement->planner_id === null ? 'PIC Perencana' : null,
                $procurement->executor_id === null ? 'PIC Pelaksana' : null,
            ]);

            return 'Belum ada '.implode(' & ', $missing);
        }, fn (Procurement $procurement): ?string => $procurement->created_at?->toDateTimeString());
    }

    /**
     * Planning work handed to the user as PIC Perencana.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function planning(User $user): array
    {
        $query = Procurement::query()
            ->inProgress()
            ->where('planner_id', $user->id)
            ->whereIn('planning_approval_state', [
                PlanningApprovalState::BelumDiajukan->value,
                PlanningApprovalState::Ditolak->value,
            ])
            // Returned work first: someone is waiting on the revision.
            ->orderByRaw('case when planning_approval_state = ? then 0 else 1 end', [PlanningApprovalState::Ditolak->value])
            ->oldest('created_at');

        return self::collect($query, fn (Procurement $procurement): string => $procurement->planning_approval_state === PlanningApprovalState::Ditolak
            ? 'Dikembalikan untuk revisi'
            : 'Lengkapi checklist lalu ajukan perencanaan',
            fn (Procurement $procurement): ?string => $procurement->target_completion_date?->toDateString());
    }

    /**
     * Execution work handed to the user as PIC Pelaksana.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function execution(User $user): array
    {
        $query = Procurement::query()
            ->inProgress()
            ->where('executor_id', $user->id)
            ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
            ->withCount([
                'checklists as open_execution_steps' => fn (Builder $checklists) => $checklists
                    ->where('stage', ProcurementStage::Pelaksanaan->value)
                    ->where('is_completed', false),
            ])
            ->orderByRaw('target_completion_date is null')
            ->orderBy('target_completion_date');

        return self::collect($query, function (Procurement $procurement): string {
            $open = (int) $procurement->getAttribute('open_execution_steps');

            return $open > 0
                ? "{$open} tahapan pelaksanaan belum selesai"
                : 'Semua tahapan selesai, siap ditutup';
        }, fn (Procurement $procurement): ?string => $procurement->target_completion_date?->toDateString());
    }

    /**
     * Count a task query and take its first rows.
     *
     * @param  Builder<Procurement>  $query
     * @param  callable(Procurement): string  $note
     * @param  callable(Procurement): (string|null)  $date
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function collect(Builder $query, callable $note, callable $date): array
    {
        $total = (clone $query)->count();

        $items = $query->limit(self::LIMIT)->get()
            ->map(fn (Procurement $procurement): array => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'name' => $procurement->name,
                'note' => $note($procurement),
                'date' => $date($procurement),
            ])
            ->all();

        return ['total' => $total, 'items' => $items];
    }
}
