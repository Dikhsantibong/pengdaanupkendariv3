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
     * Determine whether the user may fill in the planning identity fields.
     *
     * These are the parts of the identity the appointed planning PIC supplies
     * once the work is theirs — the kind of contract and the manager's memo
     * number. Supervisors keep the ability to correct them. Editing closes
     * with the planning stage, because the RKS and SPK are drawn up on them.
     */
    public function updatePlanningIdentity(User $user, Procurement $procurement): bool
    {
        if ($procurement->isPlanningApproved()) {
            return $user->isSupervisor();
        }

        return $user->isSupervisor() || $procurement->planner_id === $user->id;
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
     *
     * Submitting and approving are deliberately kept apart. Only the appointed
     * planning PIC submits: a supervisor may approve, so letting them submit
     * too would let the same person do both halves of the review. It is also
     * refused until every mandatory planning step is ticked, so nothing
     * unfinished reaches the approval queue. Optional steps do not count.
     */
    public function submitPlanning(User $user, Procurement $procurement): bool
    {
        if (in_array($procurement->planning_approval_state, [
            PlanningApprovalState::MenungguPersetujuan,
            PlanningApprovalState::Disetujui,
        ], true)) {
            return false;
        }

        if ($procurement->planner_id !== $user->id) {
            return false;
        }

        return $procurement->isPlanningChecklistComplete();
    }

    /**
     * Determine whether the user may approve or reject the planning stage.
     *
     * A supervisor never reviews their own submission, because they cannot be
     * the submitter in the first place.
     */
    public function reviewPlanning(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor()
            && $procurement->planning_approval_state === PlanningApprovalState::MenungguPersetujuan;
    }

    /**
     * Determine whether the user may withdraw a rejection they issued.
     *
     * The escape hatch for a rejection made in error, or one whose PIC cannot
     * act. It puts the submission back in the approval queue rather than
     * submitting anything, so a supervisor still never submits their own work.
     */
    public function revertPlanningRejection(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor()
            && $procurement->planning_approval_state === PlanningApprovalState::Ditolak;
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
