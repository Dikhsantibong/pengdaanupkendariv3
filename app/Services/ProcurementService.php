<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementActivity;
use App\Models\ProgressStatus;
use App\Models\User;
use App\Notifications\PlanningReviewed;
use App\Notifications\PlanningSubmitted;
use App\Notifications\ProcurementAssigned;
use Illuminate\Support\Facades\DB;

class ProcurementService
{
    /**
     * Register a new procurement together with its checklist rows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $author): Procurement
    {
        return DB::transaction(function () use ($attributes, $author): Procurement {
            // The planning PIC may be appointed on the create form, but the
            // appointment itself goes through assignPic() so the notification
            // and the history entry are identical either way.
            $plannerId = $attributes['planner_id'] ?? null;
            unset($attributes['planner_id']);

            $procurement = new Procurement($attributes);
            $procurement->number = $this->nextNumber();
            $procurement->created_by = $author->id;
            $procurement->save();

            $this->syncChecklists($procurement);

            $this->recordActivity(
                $procurement,
                $author,
                ActivityType::Dibuat,
                "Pengadaan {$procurement->number} dibuat.",
            );

            if ($plannerId !== null) {
                $this->assignPic($procurement, $author, (int) $plannerId, $procurement->executor_id);
            }

            return $procurement;
        });
    }

    /**
     * Generate the next internal procurement number.
     */
    public function nextNumber(): string
    {
        $prefix = 'PGD/'.now()->format('Y/m').'/';

        $latest = Procurement::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $latest === null ? 1 : ((int) substr((string) $latest, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Make sure the procurement has a checklist row for every applicable item.
     *
     * Applicable means active and not excluded from the procurement's method,
     * so switching method adds the steps the new method needs and drops the
     * ones it does not. Rows that are already completed are always kept: they
     * are history, not configuration.
     */
    public function syncChecklists(Procurement $procurement): void
    {
        $applicable = ChecklistItem::query()
            ->active()
            ->forProcurementMethod($procurement->procurement_method_id)
            ->ordered()
            ->get();

        $existing = $procurement->checklists()->pluck('checklist_item_id')->all();

        foreach ($applicable->whereNotIn('id', $existing) as $item) {
            $procurement->checklists()->create([
                'checklist_item_id' => $item->id,
                'stage' => $item->stage,
                'is_completed' => false,
            ]);
        }

        $procurement->checklists()
            ->where('is_completed', false)
            ->whereNotIn('checklist_item_id', $applicable->pluck('id'))
            ->delete();

        $procurement->unsetRelation('checklists');
    }

    /**
     * Appoint the planning and execution PICs of a procurement.
     */
    public function assignPic(Procurement $procurement, User $actor, ?int $plannerId, ?int $executorId): Procurement
    {
        $previousPlannerId = $procurement->planner_id;
        $previousExecutorId = $procurement->executor_id;

        $procurement->planner_id = $plannerId;
        $procurement->executor_id = $executorId;
        $procurement->save();

        if ($plannerId !== $previousPlannerId) {
            $procurement->refresh()->loadMissing('planner');

            $this->recordActivity(
                $procurement,
                $actor,
                ActivityType::PicDitunjuk,
                'PIC Perencana diubah menjadi '.($plannerId === null ? 'kosong' : $procurement->planner->name).'.',
            );

            if ($plannerId !== null) {
                $procurement->planner->notify(new ProcurementAssigned($procurement, ProcurementStage::Perencanaan));
            }
        }

        if ($executorId !== $previousExecutorId) {
            $procurement->refresh()->loadMissing('executor');

            $this->recordActivity(
                $procurement,
                $actor,
                ActivityType::PicDitunjuk,
                'PIC Pelaksana diubah menjadi '.($executorId === null ? 'kosong' : $procurement->executor->name).'.',
            );

            if ($executorId !== null) {
                $procurement->executor->notify(new ProcurementAssigned($procurement, ProcurementStage::Pelaksanaan));
            }
        }

        return $procurement;
    }

    /**
     * Move the procurement onto a different progress status.
     */
    public function changeStatus(Procurement $procurement, User $actor, ProgressStatus $status, ?string $note = null): Procurement
    {
        $previous = $procurement->progressStatus;

        $procurement->progress_status_id = $status->id;
        $procurement->save();

        $this->recordActivity(
            $procurement,
            $actor,
            ActivityType::StatusDiubah,
            "Status diubah dari {$previous->name} menjadi {$status->name}.",
            ['from' => $previous->name, 'to' => $status->name, 'note' => $note],
        );

        return $procurement;
    }

    /**
     * Submit the planning stage for approval, first time or after a revision.
     */
    public function submitPlanning(Procurement $procurement, User $actor): Procurement
    {
        $isRevision = $procurement->planning_approval_state === PlanningApprovalState::Ditolak;

        if ($isRevision) {
            $procurement->planning_revision = $procurement->planning_revision + 1;
        }

        $procurement->planning_approval_state = PlanningApprovalState::MenungguPersetujuan;
        $procurement->planning_submitted_at = now();
        // The reviewer of the previous round is cleared, but their note is not:
        // it is the instruction being answered, so both the PIC and whoever
        // reviews this round need to keep seeing it.
        $procurement->planning_reviewed_at = null;
        $procurement->planning_reviewed_by = null;
        $procurement->save();

        $this->recordActivity(
            $procurement,
            $actor,
            ActivityType::PerencanaanDiajukan,
            $isRevision
                ? "Dokumen perencanaan diajukan ulang (revisi ke-{$procurement->planning_revision})."
                : 'Dokumen perencanaan diajukan untuk persetujuan.',
        );

        $this->notifySupervisors(new PlanningSubmitted($procurement));

        return $procurement;
    }

    /**
     * Approve or reject the planning stage of a procurement.
     */
    public function reviewPlanning(Procurement $procurement, User $reviewer, bool $approved, ?string $note = null): Procurement
    {
        $procurement->planning_approval_state = $approved
            ? PlanningApprovalState::Disetujui
            : PlanningApprovalState::Ditolak;
        $procurement->planning_reviewed_at = now();
        $procurement->planning_reviewed_by = $reviewer->id;
        $procurement->planning_review_note = $note;
        $procurement->save();

        $this->recordActivity(
            $procurement,
            $reviewer,
            $approved ? ActivityType::PerencanaanDisetujui : ActivityType::PerencanaanDitolak,
            $approved
                ? 'Dokumen perencanaan disetujui.'
                : 'Dokumen perencanaan dikembalikan untuk revisi.'.($note !== null ? " Catatan: {$note}" : ''),
        );

        if ($procurement->planner_id !== null) {
            $procurement->planner->notify(new PlanningReviewed($procurement, $approved, $note));
        }

        return $procurement;
    }

    /**
     * Withdraw a rejection and put the submission back in the approval queue.
     *
     * Nothing is submitted here: the PIC's original submission is simply put
     * back up for decision, which is what is needed when a rejection was
     * issued in error or when the PIC cannot act on it.
     */
    public function revertPlanningRejection(Procurement $procurement, User $actor, ?string $reason = null): Procurement
    {
        $procurement->planning_approval_state = PlanningApprovalState::MenungguPersetujuan;
        $procurement->planning_reviewed_at = null;
        $procurement->planning_reviewed_by = null;
        $procurement->planning_review_note = null;
        $procurement->save();

        $this->recordActivity(
            $procurement,
            $actor,
            ActivityType::PerencanaanDiajukan,
            'Penolakan dibatalkan, perencanaan kembali menunggu persetujuan.'
                .($reason !== null ? " Alasan: {$reason}" : ''),
        );

        if ($procurement->planner_id !== null) {
            $procurement->planner->notify(new PlanningSubmitted($procurement));
        }

        return $procurement;
    }

    /**
     * Close out a procurement once the execution stage is finished.
     */
    public function complete(Procurement $procurement, User $actor): Procurement
    {
        $procurement->completed_at = now();
        $procurement->save();

        $this->recordActivity(
            $procurement,
            $actor,
            ActivityType::PengadaanSelesai,
            'Pengadaan dinyatakan selesai.',
        );

        return $procurement;
    }

    /**
     * Append an entry to the procurement audit trail.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function recordActivity(
        Procurement $procurement,
        ?User $actor,
        ActivityType $type,
        string $description,
        ?array $meta = null,
    ): ProcurementActivity {
        return $procurement->activities()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    /**
     * Send a notification to every supervisor of the procurement process.
     */
    protected function notifySupervisors(mixed $notification): void
    {
        User::query()
            ->active()
            ->withRole([UserRole::TeamLeader, UserRole::Administrator])
            ->get()
            ->each(fn (User $user) => $user->notify($notification));
    }
}
