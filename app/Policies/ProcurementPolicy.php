<?php

namespace App\Policies;

use App\Enums\AmendmentState;
use App\Enums\DeliveryOrderState;
use App\Enums\FinanceSection;
use App\Enums\PaymentApprovalState;
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
use App\Models\ProcurementDeliveryOrder;
use App\Models\User;
use App\Services\AmendmentService;
use App\Services\DeliveryOrderService;
use App\Services\ProcurementService;
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
            || ($procurement->isHandoverApproved() && $user->hasPermission(Permission::ProcessPayment))
            || $this->decidesPaymentOf($user, $procurement, any: true);
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

        if ($level === ProposalState::MenungguPersetujuanTl) {
            return $this->leadsProposerOf($user, $procurement);
        }

        // A division with no Direksi Pekerjaan account of its own: its TL
        // decides, so a proposal never waits on an Asman who is not there.
        return $this->leadsDivisionOf($user, $procurement)
            || ($this->leadsProposerOf($user, $procurement)
                && ! app(ProcurementService::class)->divisionHasApprover($procurement, $user));
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
     * Determine whether the user may give the contract number, which sits in
     * the identity of the procurement: the execution PIC, TL Pengadaan or the
     * administrator, once there is an approved plan and until the job is with
     * Keuangan.
     */
    public function updateContractNumber(User $user, Procurement $procurement): bool
    {
        return $procurement->isPlanningApproved()
            && $procurement->finance_submitted_at === null
            && $this->worksOnExecution($user, $procurement);
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
     * Determine whether the user may decide on an amendment request at its
     * level: the proposer's TL, then the division's Asman. Nobody decides on
     * their own request.
     */
    public function decideAmendment(User $user, Procurement $procurement, ProcurementAmendment $amendment): bool
    {
        if (! $amendment->state->awaitsApproval()) {
            return false;
        }

        if ($user->role === UserRole::Administrator) {
            return true;
        }

        if ($amendment->created_by === $user->id) {
            return false;
        }

        $service = app(AmendmentService::class);

        return $amendment->state === AmendmentState::MenungguPersetujuanTl
            ? $service->teamLeaderOf($amendment)?->is($user) === true
            // The Asman, or the requester's TL when the division has none.
            : $service->asmanLevelDeciders($amendment)->contains(fn (User $decider): bool => $decider->is($user));
    }

    /**
     * Determine whether the user may number, date, fill in the changed
     * articles of and issue an agreed amendment: the execution side
     * (Laksdan). It does not pass through the planning side.
     */
    public function issueAmendment(User $user, Procurement $procurement, ProcurementAmendment $amendment): bool
    {
        return $amendment->state === AmendmentState::MenungguTlPengadaan && $this->worksOnExecution($user, $procurement);
    }

    /**
     * Determine whether the user may request a Delivery Order: the
     * proposing division, under a KHS contract that exists, until the job is
     * closed or cancelled. Several DOs may run side by side.
     */
    public function startDeliveryOrder(User $user, Procurement $procurement): bool
    {
        return $this->proposesFor($user, $procurement)
            && $procurement->completed_at === null
            && $procurement->progressStatus->category !== StatusCategory::Batal
            && app(DeliveryOrderService::class)->available($procurement);
    }

    /**
     * Determine whether the user may fill in and send a DO request.
     */
    public function updateDeliveryOrderRequest(User $user, Procurement $procurement, ProcurementDeliveryOrder $order): bool
    {
        return $order->state->isEditable()
            && ($user->role === UserRole::Administrator || $order->created_by === $user->id);
    }

    /**
     * Determine whether the user may decide on a DO request at its level:
     * the proposer's TL, then the division's Asman. Nobody decides on their
     * own request.
     */
    public function decideDeliveryOrder(User $user, Procurement $procurement, ProcurementDeliveryOrder $order): bool
    {
        if (! $order->state->awaitsApproval()) {
            return false;
        }

        if ($user->role === UserRole::Administrator) {
            return true;
        }

        if ($order->created_by === $user->id) {
            return false;
        }

        $service = app(DeliveryOrderService::class);

        return $order->state === DeliveryOrderState::MenungguPersetujuanTl
            ? $service->teamLeaderOf($order)?->is($user) === true
            // The Asman, or the requester's TL when the division has none.
            : $service->asmanLevelDeciders($order)->contains(fn (User $decider): bool => $decider->is($user));
    }

    /**
     * Determine whether the user may write the Nota Dinas DO and hand the DO
     * to Laksdan: the planning side (Rendan).
     */
    public function forwardDeliveryOrder(User $user, Procurement $procurement, ProcurementDeliveryOrder $order): bool
    {
        return $order->state === DeliveryOrderState::DiRendan && $this->worksOnPlanning($user, $procurement);
    }

    /**
     * Determine whether the user may date, number and issue the DO: the
     * execution side (Laksdan).
     */
    public function issueDeliveryOrder(User $user, Procurement $procurement, ProcurementDeliveryOrder $order): bool
    {
        return $order->state === DeliveryOrderState::DiLaksdan && $this->worksOnExecution($user, $procurement);
    }

    /**
     * Determine whether the user may keep the Rincian Item: the planning
     * side while the job is planned, the execution side once planning is
     * approved, until it goes to Keuangan or is cancelled.
     */
    public function manageItems(User $user, Procurement $procurement): bool
    {
        if ($procurement->finance_submitted_at !== null
            || $procurement->progressStatus->category === StatusCategory::Batal) {
            return false;
        }

        return $procurement->isPlanningApproved()
            ? $this->worksOnExecution($user, $procurement)
            : $this->worksOnPlanning($user, $procurement);
    }

    /**
     * Determine whether the user may keep the KHS contract's price list,
     * which every DO draws its lines from: the execution side.
     */
    public function manageContractItems(User $user, Procurement $procurement): bool
    {
        return $procurement->usesDeliveryOrders() && $this->worksOnExecution($user, $procurement);
    }

    /**
     * Determine whether the user may work through either Keuangan tab.
     */
    public function updateFinanceChecklist(User $user, Procurement $procurement): bool
    {
        return $this->updateHandoverChecklist($user, $procurement)
            || $this->updatePaymentChecklist($user, $procurement);
    }

    /**
     * Determine whether the user may work on one Keuangan step: a Penyerahan
     * step is the execution side's, a Pembayaran step Keuangan's.
     */
    public function updateFinanceStep(User $user, Procurement $procurement, ProcurementChecklist $checklist): bool
    {
        return $checklist->financeSection() === FinanceSection::Penyerahan
            ? $this->updateHandoverChecklist($user, $procurement)
            : $this->updatePaymentChecklist($user, $procurement);
    }

    /**
     * Determine whether the user may file the hand-over papers: who works on
     * the execution, once it is approved, until the hand-over is submitted
     * (or while it is back for revision).
     */
    public function updateHandoverChecklist(User $user, Procurement $procurement): bool
    {
        return $procurement->isInFinance()
            && in_array($procurement->handover_approval_state, [PlanningApprovalState::BelumDiajukan, PlanningApprovalState::Ditolak], true)
            && $this->worksOnExecution($user, $procurement);
    }

    /**
     * Determine whether the user may submit the hand-over to TL Pengadaan:
     * the execution PIC, like the execution itself, once every mandatory
     * Penyerahan step is done.
     */
    public function submitHandover(User $user, Procurement $procurement): bool
    {
        return $this->updateHandoverChecklist($user, $procurement)
            && $procurement->executor_id === $user->id
            && $procurement->pendingFinanceChecklists(FinanceSection::Penyerahan)->isEmpty();
    }

    /**
     * Determine whether the user may approve or return the hand-over.
     * Approving opens the payment to Keuangan.
     */
    public function reviewHandover(User $user, Procurement $procurement): bool
    {
        return $user->hasPermission(Permission::ReviewHandover)
            && $this->view($user, $procurement)
            && $procurement->isInFinance()
            && $procurement->isHandoverAwaitingApproval();
    }

    /**
     * Determine whether the user may file the payment papers: Keuangan, once
     * TL Pengadaan approved the hand-over, until the payment is submitted
     * (or while it is back for revision).
     */
    public function updatePaymentChecklist(User $user, Procurement $procurement): bool
    {
        return $procurement->isInPayment()
            && $procurement->payment_approval_state->isOpen()
            && $user->hasPermission(Permission::ProcessPayment);
    }

    /**
     * Determine whether the user may submit the payment to their TL, once
     * every mandatory Pembayaran step is done.
     */
    public function submitPayment(User $user, Procurement $procurement): bool
    {
        return $this->updatePaymentChecklist($user, $procurement)
            && $procurement->pendingFinanceChecklists(FinanceSection::Pembayaran)->isEmpty();
    }

    /**
     * Determine whether the user may approve or return the payment at the
     * step it waits on: the submitter's TL, then the Asman of the division.
     * Nobody decides a payment they submitted; the administrator may stand
     * in for either.
     */
    public function decidePayment(User $user, Procurement $procurement): bool
    {
        return $procurement->isInPayment()
            && $procurement->payment_approval_state->isAwaiting()
            && $procurement->payment_submitted_by !== $user->id
            && $this->decidesPaymentOf($user, $procurement);
    }

    /**
     * Whether the user decides the payment at the step it waits on, or, with
     * $any, at either step of a payment submitted at all.
     */
    protected function decidesPaymentOf(User $user, Procurement $procurement, bool $any = false): bool
    {
        $state = $procurement->payment_approval_state;

        if ($state === PaymentApprovalState::BelumDiajukan) {
            return false;
        }

        $asTeamLeader = $procurement->payment_team_leader_id !== null
            && $procurement->payment_team_leader_id === $user->id;
        $asDirector = $procurement->payment_work_director_id !== null
            && $user->work_director_id === $procurement->payment_work_director_id
            && $user->hasPermission(Permission::ApproveProposal);

        if ($any) {
            return $asTeamLeader || $asDirector;
        }

        return $user->isAdministrator()
            || ($state === PaymentApprovalState::MenungguTl && $asTeamLeader)
            || ($state === PaymentApprovalState::MenungguAsman && $asDirector);
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
