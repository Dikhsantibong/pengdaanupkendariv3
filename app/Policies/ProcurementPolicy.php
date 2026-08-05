<?php

namespace App\Policies;

use App\Enums\PlanningApprovalState;
use App\Models\Procurement;
use App\Models\User;

class ProcurementPolicy
{
    /**
     * Determine whether the user may browse the procurement list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user may open a specific procurement.
     */
    public function view(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor() || $procurement->isAssignedTo($user);
    }

    /**
     * Determine whether the user may register a new procurement.
     */
    public function create(User $user): bool
    {
        return $user->isSupervisor();
    }

    /**
     * Determine whether the user may edit the procurement identity data.
     */
    public function update(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor();
    }

    /**
     * Determine whether the user may archive the procurement.
     */
    public function delete(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor();
    }

    /**
     * Determine whether the user may appoint the planning and execution PICs.
     */
    public function assignPic(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor();
    }

    /**
     * Determine whether the user may change the progress status.
     */
    public function updateStatus(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor() || $procurement->isAssignedTo($user);
    }

    /**
     * Determine whether the user may tick the planning checklist.
     */
    public function updatePlanningChecklist(User $user, Procurement $procurement): bool
    {
        if ($procurement->planning_approval_state === PlanningApprovalState::Disetujui) {
            return false;
        }

        return $user->isSupervisor() || $procurement->planner_id === $user->id;
    }

    /**
     * Determine whether the user may submit the planning stage for approval.
     */
    public function submitPlanning(User $user, Procurement $procurement): bool
    {
        if (in_array($procurement->planning_approval_state, [
            PlanningApprovalState::MenungguPersetujuan,
            PlanningApprovalState::Disetujui,
        ], true)) {
            return false;
        }

        return $user->isSupervisor() || $procurement->planner_id === $user->id;
    }

    /**
     * Determine whether the user may approve or reject the planning stage.
     */
    public function reviewPlanning(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor()
            && $procurement->planning_approval_state === PlanningApprovalState::MenungguPersetujuan;
    }

    /**
     * Determine whether the user may tick the execution checklist.
     */
    public function updateExecutionChecklist(User $user, Procurement $procurement): bool
    {
        if (! $procurement->isPlanningApproved()) {
            return false;
        }

        return $user->isSupervisor() || $procurement->executor_id === $user->id;
    }

    /**
     * Determine whether the user may close out the procurement.
     */
    public function complete(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor() && $procurement->completed_at === null;
    }

    /**
     * Determine whether the user may generate documents for the procurement.
     */
    public function generateDocument(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor() || $procurement->isAssignedTo($user);
    }

    /**
     * Determine whether the user may correct a generated document.
     *
     * A generated document is a draft until someone has checked its wording
     * and the data pulled into it, so whoever may generate it may correct it.
     */
    public function editDocument(User $user, Procurement $procurement): bool
    {
        return $this->generateDocument($user, $procurement);
    }
}
