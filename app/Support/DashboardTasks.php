<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\UserRole;
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
            'proposals' => $user->hasPermission(Permission::ApproveProposal) || $user->leadsTeam()
                ? self::proposalsToApprove($user)
                : null,
            'finance' => $user->hasPermission(Permission::ProcessPayment)
                ? self::payments($user)
                : null,
            'myProposals' => self::myProposals($user),
            'approvals' => $user->hasPermission(Permission::ReviewPlanning)
                ? self::approvals($user)
                : null,
            'executionApprovals' => $user->hasPermission(Permission::ReviewExecution)
                ? self::executionApprovals($user)
                : null,
            'assignments' => $user->hasPermission(Permission::AssignPlanner) || $user->hasPermission(Permission::AssignExecutor)
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
     * Executions submitted by their PIC and waiting for TL Pengadaan.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function executionApprovals(User $user): array
    {
        $query = Procurement::query()
            ->visibleTo($user)
            ->where('execution_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
            ->whereNull('finance_submitted_at')
            ->with('executor')
            ->oldest('execution_submitted_at');

        return self::collect($query, fn (Procurement $procurement): string => 'Diajukan oleh '
            .($procurement->executor->name ?? 'PIC Pelaksana')
            .($procurement->execution_revision > 0 ? ' · revisi ke-'.$procurement->execution_revision : ''),
            fn (Procurement $procurement): ?string => $procurement->execution_submitted_at?->toDateTimeString());
    }

    /**
     * Unfinished procurements still missing the PIC the user appoints.
     *
     * The PIC Perencana is appointed (by TL ICC) once the Asman approved the
     * proposal; the PIC Pelaksana (by TL Pengadaan) once ICC approved the
     * planning.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function assignments(User $user): array
    {
        $assignsPlanner = $user->hasPermission(Permission::AssignPlanner);
        $assignsExecutor = $user->hasPermission(Permission::AssignExecutor);

        $query = Procurement::query()
            ->visibleTo($user)
            ->proposalApproved()
            ->inProgress()
            ->where(function (Builder $inner) use ($assignsPlanner, $assignsExecutor): void {
                if ($assignsPlanner) {
                    $inner->whereNull('planner_id');
                }

                if ($assignsExecutor) {
                    $inner->orWhere(fn (Builder $executor) => $executor
                        ->whereNull('executor_id')
                        ->where('planning_approval_state', PlanningApprovalState::Disetujui->value));
                }
            })
            ->oldest('created_at');

        return self::collect($query, fn (Procurement $procurement): string => $procurement->planner_id === null
            ? 'Belum ada PIC Perencana'
            : 'Perencanaan disetujui, belum ada PIC Pelaksana',
            fn (Procurement $procurement): ?string => $procurement->created_at?->toDateTimeString());
    }

    /**
     * Work proposals of the user's division waiting for their approval.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function proposalsToApprove(User $user): array
    {
        $levels = [ProposalState::MenungguPersetujuanTl->value, ProposalState::MenungguPersetujuan->value];

        $query = Procurement::query()
            ->whereIn('proposal_state', $levels)
            ->when($user->role !== UserRole::Administrator, fn (Builder $query) => $query
                ->where('created_by', '!=', $user->id)
                ->where(fn (Builder $waiting) => $waiting
                    // The team leader decides first ...
                    ->where(fn (Builder $team) => $team
                        ->where('proposal_state', ProposalState::MenungguPersetujuanTl->value)
                        ->whereIn('created_by', User::query()->select('id')->where('team_leader_id', $user->id)))
                    // ... then the division's Direksi Pekerjaan.
                    ->when($user->hasPermission(Permission::ApproveProposal), fn (Builder $waiting) => $waiting
                        ->orWhere(fn (Builder $division) => $division
                            ->where('proposal_state', ProposalState::MenungguPersetujuan->value)
                            ->where('work_director_id', $user->work_director_id ?? 0)))))
            ->with('creator')
            ->oldest('proposal_submitted_at');

        return self::collect(
            $query,
            fn (Procurement $procurement): string => 'Diusulkan oleh '.($procurement->creator->name ?? '-')
                .($procurement->proposal_state === ProposalState::MenungguPersetujuanTl ? ' · persetujuan TL' : ' · persetujuan Asman'),
            fn (Procurement $procurement): ?string => $procurement->proposal_submitted_at?->toDateTimeString(),
        );
    }

    /**
     * The user's own proposals that still need attention: waiting for the
     * Direksi Pekerjaan, or returned to be revised.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function myProposals(User $user): array
    {
        $query = Procurement::query()
            ->where('created_by', $user->id)
            ->whereIn('proposal_state', [
                ProposalState::MenungguPersetujuanTl->value,
                ProposalState::MenungguPersetujuan->value,
                ProposalState::Dikembalikan->value,
            ])
            // Returned proposals first: they wait on the proposer.
            ->orderByRaw('case when proposal_state = ? then 0 else 1 end', [ProposalState::Dikembalikan->value])
            ->latest('proposal_submitted_at');

        return self::collect(
            $query,
            fn (Procurement $procurement): string => match ($procurement->proposal_state) {
                ProposalState::Dikembalikan => 'Dikembalikan: '.($procurement->proposal_review_note ?? 'perlu diperbaiki'),
                ProposalState::MenungguPersetujuanTl => 'Menunggu persetujuan TL',
                default => 'Menunggu persetujuan Asman',
            },
            fn (Procurement $procurement): ?string => $procurement->proposal_submitted_at?->toDateTimeString(),
        );
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
            // Once handed to Keuangan, the job is no longer the executor's.
            ->whereNull('finance_submitted_at')
            ->withCount([
                'checklists as open_execution_steps' => fn (Builder $checklists) => $checklists
                    ->where('stage', ProcurementStage::Pelaksanaan->value)
                    ->where('is_completed', false),
            ])
            ->orderByRaw('target_completion_date is null')
            ->orderBy('target_completion_date');

        return self::collect($query, function (Procurement $procurement): string {
            $open = (int) $procurement->getAttribute('open_execution_steps');

            return match (true) {
                $procurement->execution_approval_state === PlanningApprovalState::MenungguPersetujuan => 'Menunggu approval TL Pengadaan',
                $procurement->execution_approval_state === PlanningApprovalState::Ditolak => 'Dikembalikan: '.($procurement->execution_review_note ?? 'perlu diperbaiki'),
                $open > 0 => "{$open} tahapan pelaksanaan belum selesai",
                default => 'Semua tahapan selesai, siap diajukan ke TL Pengadaan',
            };
        }, fn (Procurement $procurement): ?string => $procurement->target_completion_date?->toDateString());
    }

    /**
     * Executed jobs handed to Keuangan and still waiting to be paid.
     *
     * @return array{total: int, items: array<int, array{id: int, number: string, name: string, note: string, date: string|null}>}
     */
    protected static function payments(User $user): array
    {
        $query = Procurement::query()
            ->visibleTo($user)
            ->whereNotNull('finance_submitted_at')
            ->whereNull('payment_completed_at')
            ->withCount([
                'checklists as open_finance_steps' => fn (Builder $checklists) => $checklists
                    ->where('stage', ProcurementStage::Keuangan->value)
                    ->where('is_completed', false),
            ])
            ->oldest('finance_submitted_at');

        return self::collect($query, function (Procurement $procurement): string {
            $open = (int) $procurement->getAttribute('open_finance_steps');

            return $open > 0
                ? "{$open} tahapan keuangan belum selesai"
                : 'Siap dicatat lunas';
        }, fn (Procurement $procurement): ?string => $procurement->finance_submitted_at?->toDateTimeString());
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
