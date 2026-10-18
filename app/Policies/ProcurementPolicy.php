<?php

namespace App\Policies;

use App\Enums\AmendmentState;
use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\StatusCategory;
use App\Enums\UserRole;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\AmendmentService;
use App\Support\ProposalDocuments;

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
        return $user->hasPermission(Permission::ViewAllProcurements)
            || $procurement->isAssignedTo($user)
            || $procurement->created_by === $user->id
            || $this->leadsProposerOf($user, $procurement)
            || $this->leadsDivisionOf($user, $procurement)
            || ($procurement->finance_submitted_at !== null && $user->hasPermission(Permission::ProcessPayment));
    }

    /**
     * Whether the user is the team leader of whoever proposed the work.
     */
    protected function leadsProposerOf(User $user, Procurement $procurement): bool
    {
        return $procurement->created_by !== null
            && $procurement->creator?->team_leader_id === $user->id;
    }

    /**
     * Whether the user is the Direksi Pekerjaan of the procurement's division.
     */
    protected function leadsDivisionOf(User $user, Procurement $procurement): bool
    {
        return $user->work_director_id !== null
            && $user->work_director_id === $procurement->work_director_id
            && $user->hasPermission(Permission::ApproveProposal);
    }

    /**
     * Determine whether the user may approve or return a work proposal.
     *
     * A staff proposal is decided twice: first by the proposer's team leader,
     * then by the Direksi Pekerjaan (Asman) of the proposal's own division.
     * The administrator may stand in for either, and nobody decides on their
     * own proposal.
     */
    public function approveProposal(User $user, Procurement $procurement): bool
    {
        $level = $procurement->proposal_state;

        if (! in_array($level, [ProposalState::MenungguPersetujuanTl, ProposalState::MenungguPersetujuan], true)) {
            return false;
        }

        // Nothing is approved before its data, TOR and Nota Dinas are in.
        if ($procurement->missingProposalRequirements() !== []) {
            return false;
        }

        if ($user->role === UserRole::Administrator) {
            return true;
        }

        if ($procurement->created_by === $user->id) {
            return false;
        }

        return $level === ProposalState::MenungguPersetujuanTl
            ? $this->leadsProposerOf($user, $procurement)
            : $this->leadsDivisionOf($user, $procurement);
    }

    /**
     * Determine whether the proposer may revise and resubmit a returned proposal.
     */
    public function reviseProposal(User $user, Procurement $procurement): bool
    {
        return $procurement->proposal_state !== ProposalState::Disetujui
            && $procurement->created_by === $user->id;
    }

    /**
     * Determine whether the user may register a new procurement.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateProcurement)
            || $user->hasPermission(Permission::ProposeWork);
    }

    /**
     * Determine whether the user may edit the procurement identity data.
     */
    public function update(User $user, Procurement $procurement): bool
    {
        // A proposer corrects their own proposal until it is approved.
        if ($this->reviseProposal($user, $procurement)) {
            return true;
        }

        return $user->hasPermission(Permission::UpdateProcurement) && $this->view($user, $procurement);
    }

    /**
     * Determine whether the user may archive the procurement.
     */
    public function delete(User $user, Procurement $procurement): bool
    {
        return $user->hasPermission(Permission::DeleteProcurement) && $this->view($user, $procurement);
    }

    /**
     * Determine whether the user may appoint at least one of the two PICs.
     */
    public function assignPic(User $user, Procurement $procurement): bool
    {
        return $this->assignPlanner($user, $procurement)
            || $this->assignExecutor($user, $procurement);
    }

    /**
     * Determine whether the user may appoint the planning PIC.
     *
     * TL ICC by default, once the Asman has approved the proposal: nobody is
     * put on a job the Direksi Pekerjaan has not approved.
     */
    public function assignPlanner(User $user, Procurement $procurement): bool
    {
        return $procurement->isProposalApproved()
            && $user->hasPermission(Permission::AssignPlanner)
            && $this->view($user, $procurement);
    }

    /**
     * Determine whether the user may appoint the execution PIC.
     *
     * TL Pengadaan by default, and only after ICC approved the planning:
     * execution is handed over once there is an approved plan to carry out.
     */
    public function assignExecutor(User $user, Procurement $procurement): bool
    {
        return $procurement->isPlanningApproved()
            && $user->hasPermission(Permission::AssignExecutor)
            && $this->view($user, $procurement);
    }

    /**
     * Determine whether the user may change the progress status.
     */
    public function updateStatus(User $user, Procurement $procurement): bool
    {
        // The status follows the work by itself; only cancelling a job (and
        // lifting it) is left to the team leaders, never to a PIC.
        return $user->isSupervisor() && $this->view($user, $procurement);
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
        if (! $procurement->isProposalApproved()
            || $procurement->planning_approval_state === PlanningApprovalState::Disetujui) {
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

        // The TOR and Nota Dinas Usulan are filed on the proposer's tab
        // rather than as checklist steps, but are still required.
        return $procurement->missingPlanningData() === []
            && $procurement->missingProposalData() === []
            && ProposalDocuments::missing($procurement) === []
            && $procurement->isPlanningChecklistComplete();
    }

    /**
     * Determine whether the user may approve or reject the planning stage.
     *
     * Nobody reviews their own submission: once the right can be given to a
     * PIC, the planning PIC of this procurement is explicitly excluded.
     */
    public function reviewPlanning(User $user, Procurement $procurement): bool
    {
        return $user->hasPermission(Permission::ReviewPlanning)
            && $this->view($user, $procurement)
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
        return $user->hasPermission(Permission::ReviewPlanning)
            && $this->view($user, $procurement)
            && $procurement->planning_approval_state === PlanningApprovalState::Ditolak;
    }

    /**
     * Determine whether the user may tick the execution checklist.
     */
    public function updateExecutionChecklist(User $user, Procurement $procurement): bool
    {
        // Held while TL Pengadaan reviews it, final once the job is with
        // Keuangan.
        if (! $procurement->isPlanningApproved()
            || $procurement->isExecutionAwaitingApproval()
            || $procurement->finance_submitted_at !== null) {
            return false;
        }

        return $this->worksOnExecution($user, $procurement);
    }

    /**
     * Whether the user works on the execution of this job: its execution PIC,
     * or TL Pengadaan / the administrator who oversee execution. TL ICC, like
     * a planning PIC, only follows it.
     */
    protected function worksOnExecution(User $user, Procurement $procurement): bool
    {
        return in_array($user->role, [UserRole::Administrator, UserRole::TeamLeaderPengadaan], true)
            || $procurement->executor_id === $user->id;
    }

    /**
     * Whether the user works on the planning of this job: its planning PIC
     * or a team leader.
     */
    protected function worksOnPlanning(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor() || $procurement->planner_id === $user->id;
    }

    /**
     * Determine whether the user may generate, correct or file a document of
     * the given type on this job.
     *
     * A document belongs to the stage whose checklist step asks for it, and
     * only who works on that stage handles it: TL ICC and the planning PIC
     * cannot touch an execution document, for instance. A document no step
     * asks for (the proposal papers, say) follows the procurement-wide right.
     */
    public function manageStageDocument(User $user, Procurement $procurement, DocumentType $type): bool
    {
        $stages = ProcurementChecklist::query()
            ->where('procurement_id', $procurement->id)
            ->whereHas('checklistItem.documentTypes', fn ($query) => $query->whereKey($type->id))
            ->pluck('stage')
            ->map(fn (mixed $stage): string => $stage instanceof ProcurementStage ? $stage->value : (string) $stage)
            ->unique();

        if ($stages->isEmpty()) {
            return true;
        }

        return $stages->contains(fn (string $stage): bool => match ($stage) {
            ProcurementStage::Perencanaan->value => $this->worksOnPlanning($user, $procurement),
            ProcurementStage::Pelaksanaan->value => $this->worksOnExecution($user, $procurement),
            ProcurementStage::Keuangan->value => $this->updateFinanceChecklist($user, $procurement),
            default => false,
        });
    }

    /**
     * Determine whether the user may handle the documents of the execution
     * tab.
     */
    public function manageExecutionDocuments(User $user, Procurement $procurement): bool
    {
        return $this->generateDocument($user, $procurement) && $this->worksOnExecution($user, $procurement);
    }

    /**
     * Determine whether the user may close out the procurement.
     */
    public function complete(User $user, Procurement $procurement): bool
    {
        // A job is closed by Keuangan once paid; this only remains for a paid
        // job that somehow stayed open.
        return $user->hasPermission(Permission::CompleteProcurement)
            && $this->view($user, $procurement)
            && $procurement->isPaid()
            && $procurement->completed_at === null;
    }

    /**
     * Determine whether the user may submit the execution for approval.
     *
     * Like the planning, only the appointed PIC submits, and only once every
     * mandatory execution step is ticked. TL Pengadaan then decides; the job
     * reaches Keuangan only through that approval.
     */
    public function submitExecution(User $user, Procurement $procurement): bool
    {
        if (! $procurement->isPlanningApproved()
            || $procurement->finance_submitted_at !== null
            || $procurement->completed_at !== null
            || in_array($procurement->execution_approval_state, [
                PlanningApprovalState::MenungguPersetujuan,
                PlanningApprovalState::Disetujui,
            ], true)) {
            return false;
        }

        if ($procurement->executor_id !== $user->id) {
            return false;
        }

        return $procurement->pendingRequiredChecklists(ProcurementStage::Pelaksanaan)->isEmpty();
    }

    /**
     * Determine whether the user may approve or return the execution.
     *
     * Approving hands the job to Keuangan for Penyerahan and Pembayaran.
     */
    public function reviewExecution(User $user, Procurement $procurement): bool
    {
        return $user->hasPermission(Permission::ReviewExecution)
            && $this->view($user, $procurement)
            && $procurement->isExecutionAwaitingApproval()
            && $procurement->finance_submitted_at === null;
    }

    /**
     * Whether the user speaks for the division that asked for the work: who
     * proposed it, or a proposer of the same division.
     */
    protected function proposesFor(User $user, Procurement $procurement): bool
    {
        return $user->role === UserRole::Administrator
            || $procurement->created_by === $user->id
            || ($user->hasPermission(Permission::ProposeWork)
                && $user->work_director_id !== null
                && $user->work_director_id === $procurement->work_director_id);
    }

    /**
     * Determine whether the user may start an amendment of the contract.
     *
     * Once there is a contract and until the job is closed or cancelled,
     * one amendment at a time, by the proposing division.
     */
    public function startAmendment(User $user, Procurement $procurement): bool
    {
        $service = app(AmendmentService::class);

        return $this->proposesFor($user, $procurement)
            && $procurement->completed_at === null
            && $procurement->progressStatus->category !== StatusCategory::Batal
            && $service->hasContract($procurement)
            && $service->open($procurement) === null;
    }

    /**
     * Determine whether the user may fill in and send an amendment.
     */
    public function updateAmendment(User $user, Procurement $procurement, ProcurementAmendment $amendment): bool
    {
        return $amendment->state->isEditable()
            && ($user->role === UserRole::Administrator || $amendment->created_by === $user->id);
    }

    /**
     * Determine whether the user may decide on an amendment at its level:
     * the proposer's TL, then the division's Asman, then TL Pengadaan.
     * Nobody decides on their own amendment.
     */
    public function decideAmendment(User $user, Procurement $procurement, ProcurementAmendment $amendment): bool
    {
        if (! $amendment->state->isWaiting()) {
            return false;
        }

        if ($user->role === UserRole::Administrator) {
            return true;
        }

        if ($amendment->created_by === $user->id) {
            return false;
        }

        return match ($amendment->state) {
            AmendmentState::MenungguPersetujuanTl => app(AmendmentService::class)->teamLeaderOf($amendment)?->is($user) === true,
            AmendmentState::MenungguPersetujuanAsman => $this->leadsDivisionOf($user, $procurement),
            default => $user->hasPermission(Permission::ApproveAmendment) && $this->view($user, $procurement),
        };
    }

    /**
     * Determine whether the user may work through the Keuangan steps.
     */
    public function updateFinanceChecklist(User $user, Procurement $procurement): bool
    {
        return $procurement->isInFinance()
            && $user->hasPermission(Permission::ProcessPayment);
    }

    /**
     * Determine whether the user may record the payment and close the job.
     */
    public function completePayment(User $user, Procurement $procurement): bool
    {
        return $this->updateFinanceChecklist($user, $procurement)
            && $procurement->pendingRequiredChecklists(ProcurementStage::Keuangan)->isEmpty();
    }

    /**
     * Determine whether the user may generate documents for the procurement.
     */
    public function generateDocument(User $user, Procurement $procurement): bool
    {
        return $user->isSupervisor()
            || $procurement->isAssignedTo($user)
            // Keuangan files the invoice papers of a job being paid.
            || $this->updateFinanceChecklist($user, $procurement);
    }

    /**
     * Determine whether the user may correct a generated document.
     *
     * A generated document is a draft until someone has checked its wording
     * and the data pulled into it, so whoever may generate it may correct it.
     */
    public function editDocument(User $user, Procurement $procurement): bool
    {
        // The proposer writes the TOR of their proposal until it is approved.
        return $this->generateDocument($user, $procurement)
            || $this->reviseProposal($user, $procurement);
    }
}
