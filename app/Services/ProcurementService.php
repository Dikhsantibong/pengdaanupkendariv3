<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\FinanceSection;
use App\Enums\PaymentApprovalState;
use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\StatusCategory;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementActivity;
use App\Models\ProgressStatus;
use App\Models\User;
use App\Notifications\PlanningReviewed;
use App\Notifications\PlanningSubmitted;
use App\Notifications\ProcurementAssigned;
use App\Notifications\ProcurementMilestone;
use App\Notifications\ProposalReviewed;
use App\Notifications\ProposalSubmitted;
use App\Support\UploadedFileType;
use Database\Seeders\FinanceStageSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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

            // Someone who may only propose raises a proposal for their own
            // division; it waits for that division's Direksi Pekerjaan.
            $isProposal = ! $author->hasPermission(Permission::CreateProcurement)
                && $author->mayProposeWork();

            if ($isProposal) {
                $plannerId = null;

                if ($author->work_director_id !== null) {
                    $attributes['work_director_id'] = $author->work_director_id;
                }
            }

            /** @var array<int, int|string> $unitIds */
            $unitIds = $attributes['target_unit_ids'] ?? [];
            unset($attributes['target_unit_ids']);

            $procurement = new Procurement($attributes);

            if ($unitIds !== []) {
                $procurement->target_unit_id = (int) reset($unitIds);
            }

            // The contract number is given later by the executor (the Kontrak
            // or Lampiran Surat Pesanan step), so a new job is registered under an
            // internal number: USL for a proposal, PGD otherwise.
            $procurement->number = trim((string) ($attributes['number'] ?? '')) !== ''
                ? (string) $attributes['number']
                : $this->nextInternalNumber($isProposal ? 'USL' : 'PGD');

            $procurement->created_by = $author->id;

            if ($isProposal) {
                $procurement->proposal_submitted_at = now();
                $procurement->proposal_state = $this->firstApprovalLevel($procurement, $author);
            }

            $procurement->save();

            $this->syncTargetUnits($procurement, $unitIds);

            $this->syncChecklists($procurement);

            $this->recordActivity(
                $procurement,
                $author,
                ActivityType::Dibuat,
                "Pengadaan {$procurement->number} dibuat.",
            );

            if ($plannerId !== null && $author->hasPermission(Permission::AssignPlanner)) {
                $this->assignPic($procurement, $author, (int) $plannerId, $procurement->executor_id);
            }

            // A proposal reaches its TL / Asman once nothing is missing (see
            // syncProposalReadiness), which may be right away or only after
            // its TOR and Nota Dinas are filed.
            return $procurement;
        });
    }

    /**
     * Where a fresh or resubmitted proposal waits first.
     *
     * Staff with a team leader go to that TL, then to the Asman. A proposer
     * without a TL — or whose TL is also the division's Direksi Pekerjaan,
     * as for K3 KAM and Lingkungan — goes straight to the Asman. The Direksi
     * Pekerjaan proposing for their own division needs no signature at all.
     */
    protected function firstApprovalLevel(Procurement $procurement, User $proposer): ProposalState
    {
        if ($this->leadsDivision($proposer, $procurement)) {
            return ProposalState::Disetujui;
        }

        $leader = $proposer->teamLeader;

        if ($leader === null
            || ! $leader->is_active
            || $leader->trashed()
            || $leader->is($proposer)
            || $this->leadsDivision($leader, $procurement)) {
            return ProposalState::MenungguPersetujuan;
        }

        return ProposalState::MenungguPersetujuanTl;
    }

    /**
     * Whether a team leader's approval of a proposal is the last one: the TL
     * leads the division (K3 KAM, Lingkungan), or the division has no other
     * Direksi Pekerjaan account to pass it to. Either way the proposal goes
     * straight to planning instead of waiting on an Asman who is not there.
     */
    public function teamLeaderApprovalIsFinal(Procurement $procurement, User $teamLeader): bool
    {
        return $this->leadsDivision($teamLeader, $procurement)
            || ! $this->divisionHasApprover($procurement, $teamLeader);
    }

    /**
     * Whether the procurement's division has an active Direksi Pekerjaan
     * account able to decide a request, other than the given user and the
     * requester (the proposer of the work unless another is named, as for an
     * amendment or a DO).
     */
    public function divisionHasApprover(Procurement $procurement, ?User $except = null, ?int $requesterId = null): bool
    {
        return User::query()
            ->active()
            ->where('work_director_id', $procurement->work_director_id)
            ->whereKeyNot(array_filter([$except?->id, $requesterId ?? $procurement->created_by]))
            ->get()
            ->contains(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal));
    }

    /**
     * Proposals of the TL's staff waiting at the Direksi Pekerjaan level in a
     * division that has no Direksi Pekerjaan account: theirs to decide.
     *
     * @return array<int, int>
     */
    public function proposalsLeftToTeamLeader(User $teamLeader): array
    {
        return Procurement::query()
            ->where('proposal_state', ProposalState::MenungguPersetujuan->value)
            ->whereNotNull('proposal_completed_at')
            ->whereIn('created_by', User::query()->select('id')->where('team_leader_id', $teamLeader->id))
            ->get()
            ->reject(fn (Procurement $procurement): bool => $this->divisionHasApprover($procurement, $teamLeader))
            ->modelKeys();
    }

    /**
     * Whether the user is the Direksi Pekerjaan of the procurement's division.
     */
    protected function leadsDivision(User $user, Procurement $procurement): bool
    {
        return $user->work_director_id !== null
            && $user->work_director_id === (int) $procurement->work_director_id
            && $user->hasPermission(Permission::ApproveProposal);
    }

    /**
     * Hand a waiting proposal to its TL / Asman once everything the proposer
     * owes is in, or hold it back with the proposer while anything is missing.
     *
     * Runs whenever the procurement or one of its documents changes, so the
     * proposal moves on at the moment it becomes complete, and is announced
     * (logged and notified) exactly then. The state is read fresh from the
     * database, so a stale model in memory cannot announce it twice.
     */
    public function syncProposalReadiness(Procurement $procurement): void
    {
        if ($procurement->getKey() === null || $procurement->proposal_submitted_at === null) {
            return;
        }

        $fresh = Procurement::withTrashed()->with(['documents.signedUploads', 'creator.teamLeader'])->whereKey($procurement->getKey())->first();

        if ($fresh === null
            || ! in_array($fresh->proposal_state, [ProposalState::MenungguPersetujuanTl, ProposalState::MenungguPersetujuan], true)) {
            return;
        }

        $complete = $fresh->missingProposalRequirements() === [];

        if ($complete === ($fresh->proposal_completed_at !== null)) {
            $procurement->setAttribute('proposal_completed_at', $fresh->proposal_completed_at);
            $procurement->syncOriginalAttribute('proposal_completed_at');

            return;
        }

        $completedAt = $complete ? now() : null;

        // Written straight to the row: saving the model would run this again.
        Procurement::withTrashed()->whereKey($fresh->getKey())->update(['proposal_completed_at' => $completedAt]);
        $procurement->setAttribute('proposal_completed_at', $completedAt);
        $procurement->syncOriginalAttribute('proposal_completed_at');

        if ($complete && $fresh->creator !== null) {
            $fresh->proposal_completed_at = $completedAt;

            $this->announceProposal(
                $fresh,
                $fresh->creator,
                $fresh->activities()->where('type', ActivityType::UsulanDikembalikan->value)->exists(),
            );
        }
    }

    /**
     * Log a proposal sent for approval and tell whoever decides it first.
     */
    protected function announceProposal(Procurement $procurement, User $proposer, bool $resubmitted): void
    {
        $atTeamLeader = $procurement->proposal_state === ProposalState::MenungguPersetujuanTl;
        $approver = $atTeamLeader ? 'TL '.($proposer->teamLeader->name ?? '') : 'Direksi Pekerjaan (Asman)';

        $this->recordActivity(
            $procurement,
            $proposer,
            ActivityType::UsulanDiajukan,
            ($resubmitted ? 'Usulan pekerjaan diperbaiki dan diajukan ulang ke ' : 'Usulan pekerjaan diajukan ke ')
                .trim($approver).' untuk disetujui.',
        );

        if ($atTeamLeader) {
            $proposer->teamLeader?->notify(new ProposalSubmitted($procurement));

            return;
        }

        $this->notifyDivisionApprovers($procurement);
    }

    /**
     * Record the units a procurement serves, in the order they were chosen.
     *
     * The first unit is also kept in `target_unit_id`, so every query and
     * screen that reads a single unit keeps working. An empty list leaves the
     * current units untouched.
     *
     * @param  array<int, int|string>  $unitIds
     */
    public function syncTargetUnits(Procurement $procurement, array $unitIds): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));

        if ($unitIds === []) {
            return;
        }

        $links = [];

        foreach ($unitIds as $index => $unitId) {
            $links[$unitId] = ['sort_order' => $index + 1];
        }

        $procurement->targetUnits()->sync($links);

        if ($procurement->target_unit_id !== $unitIds[0]) {
            $procurement->target_unit_id = $unitIds[0];
            $procurement->save();
        }

        $procurement->unsetRelation('targetUnits');
        $procurement->unsetRelation('targetUnit');
    }

    /**
     * Generate the next free contract number for one format.
     *
     * SPK and PJ each keep their own running count within a year, so the
     * sequence is read from the numbers already issued under that format
     * rather than from a shared counter. Numbers corrected by hand into a
     * different shape are skipped rather than guessed at.
     *
     * The count never drops below the format's starting sequence, because the
     * unit is already partway through the year: SPK resumes at 075 and PJ at
     * 020 rather than beginning again at 001.
     *
     * Falls back to the old internal PGD number when no format is given, which
     * keeps procurements registrable before the formats are seeded.
     */
    public function nextNumber(?ContractNumberFormat $format = null): string
    {
        if ($format === null) {
            return $this->nextInternalNumber();
        }

        $year = (int) now()->format('Y');

        $highest = 0;

        Procurement::withTrashed()
            ->where('contract_number_format_id', $format->getKey())
            ->pluck('number')
            ->each(function (string $number) use ($format, $year, &$highest): void {
                $sequence = $format->sequenceIn($number, $year);

                if ($sequence !== null && $sequence > $highest) {
                    $highest = $sequence;
                }
            });

        return $format->compose(
            max($highest + 1, $format->starting_sequence),
            $year,
        );
    }

    /**
     * The next free number for every format an author may choose from.
     *
     * @return array<int, string>
     */
    public function nextNumbersByFormat(): array
    {
        return ContractNumberFormat::query()
            ->forContracts()
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn (ContractNumberFormat $format): array => [
                $format->getKey() => $this->nextNumber($format),
            ])
            ->all();
    }

    /**
     * Generate the internal procurement number used before contract numbering.
     */
    protected function nextInternalNumber(string $series = 'PGD'): string
    {
        $prefix = $series.'/'.now()->format('Y/m').'/';

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
        $this->readContractRequirements($procurement);

        // The requirements read from this procurement's own contract.
        $own = ChecklistItem::query()
            ->active()
            ->where('procurement_id', $procurement->id)
            ->ordered()
            ->get();

        $readSections = $own->map(fn (ChecklistItem $item): ?string => $item->section?->value)->filter()->unique();

        $applicable = ChecklistItem::query()
            ->shared()
            ->active()
            ->forProcurementMethod($procurement->procurement_method_id)
            ->forContractNumberFormat($procurement->contract_number_format_id)
            ->forWorkCategory($procurement->work_category)
            // The Penyerahan and Pembayaran requirements are those of one
            // contract, so they wait until the contract format is known.
            ->when(
                $procurement->contract_number_format_id === null,
                fn ($query) => $query->whereNull('section'),
            )
            ->ordered()
            ->get()
            // A section read from the contract replaces the template's list.
            ->reject(fn (ChecklistItem $item): bool => $item->section !== null && $readSections->contains($item->section->value))
            ->concat($own);

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

        // Which steps the job goes through decides part of its status.
        app(ProgressStatusResolver::class)->sync($procurement);
    }

    /**
     * Keep the Penyerahan and Pembayaran requirements in step with the
     * procurement's own contract.
     *
     * Each list found in the contract becomes checklist items owned by this
     * procurement. An item whose wording is unchanged is the same item, so
     * its tick survives a re-read; one the contract no longer lists is
     * switched off, its tick kept as history. A list the contract does not
     * have (no contract drawn yet, or worded beyond recognition) falls back
     * to the template's requirements. Once paid, the list is final.
     */
    public function readContractRequirements(Procurement $procurement): void
    {
        if ($procurement->payment_completed_at !== null || ! $procurement->exists) {
            return;
        }

        try {
            $read = app(ContractRequirementReader::class)->read($procurement);
        } catch (Throwable $exception) {
            // A contract that cannot be read must never take the page down:
            // the requirements already read stay, or the template's stand in.
            report($exception);

            return;
        }

        DB::transaction(function () use ($procurement, $read): void {
            // Two people opening the job at once must not both add the same
            // requirements: the second waits for the first, then finds them.
            Procurement::query()->whereKey($procurement->id)->lockForUpdate()->first();

            $this->storeContractRequirements($procurement, $read);
        });
    }

    /**
     * Write the requirements read from a contract as the procurement's own
     * checklist items.
     *
     * @param  array<string, list<array{name: string, is_optional: bool, description: string}>>  $read
     */
    protected function storeContractRequirements(Procurement $procurement, array $read): void
    {
        $existing = ChecklistItem::query()->where('procurement_id', $procurement->id)->get();

        foreach (FinanceSection::cases() as $section) {
            $current = $existing->filter(fn (ChecklistItem $item): bool => $item->section === $section);
            $kept = [];

            foreach ($read[$section->value] ?? [] as $index => $requirement) {
                $item = $current->first(
                    fn (ChecklistItem $candidate): bool => $candidate->name === $requirement['name'] && ! in_array($candidate->id, $kept, true),
                ) ?? new ChecklistItem([
                    'procurement_id' => $procurement->id,
                    'stage' => ProcurementStage::Keuangan,
                    'section' => $section,
                    'name' => $requirement['name'],
                ]);

                $item->fill([
                    'description' => $requirement['description'],
                    'is_optional' => $requirement['is_optional'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);

                if ($item->isDirty()) {
                    $item->save();
                }

                $kept[] = $item->id;
            }

            $current
                ->reject(fn (ChecklistItem $item): bool => in_array($item->id, $kept, true) || ! $item->is_active)
                ->each(fn (ChecklistItem $item) => $item->update(['is_active' => false]));
        }
    }

    /**
     * Appoint the planning and execution PICs of a procurement.
     *
     * Who may change which of the two is decided by the caller (TL ICC the
     * planner, TL Pengadaan the executor); a value left as it was is not
     * touched or logged.
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
                ['pic' => 'perencana'],
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
                ['pic' => 'pelaksana'],
            );

            // Execution cannot start before the planning is approved, so an
            // executor appointed early is told when it is.
            if ($executorId !== null && $procurement->isPlanningApproved()) {
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

        $this->notifyHolders(Permission::ReviewPlanning, new PlanningSubmitted($procurement));

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

        if ($approved) {
            $procurement->executor?->notify(new ProcurementAssigned($procurement, ProcurementStage::Pelaksanaan));

            // TL Pengadaan takes over from here and appoints the executor.
            if ($procurement->executor_id === null) {
                $this->notifyHolders(Permission::AssignExecutor, new ProcurementMilestone(
                    $procurement,
                    'Tunjuk PIC Pelaksana',
                    "Perencanaan {$procurement->number} - {$procurement->name} disetujui ICC. Tunjuk PIC Pelaksana untuk memulai pelaksanaan.",
                ));
            }

            $this->notifyProposers(
                $procurement,
                'Perencanaan disetujui',
                "Perencanaan {$procurement->name} disetujui dan masuk tahap pelaksanaan.",
            );
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
     * Approve a work proposal, or return it to the proposer with a note.
     *
     * The team leader's approval passes the proposal on to the Asman (or
     * straight to planning when that TL is also the division's Direksi
     * Pekerjaan). The Asman's approval hands the job to procurement: it
     * appears for planning and the procurement supervisors are told so they
     * can appoint a planner.
     */
    public function decideProposal(Procurement $procurement, User $reviewer, bool $approved, ?string $note = null): Procurement
    {
        $atTeamLeader = $procurement->proposal_state === ProposalState::MenungguPersetujuanTl;

        if ($atTeamLeader) {
            $procurement->proposal_tl_reviewed_at = now();
            $procurement->proposal_tl_reviewed_by = $reviewer->id;
        }

        if (! $approved) {
            $procurement->proposal_state = ProposalState::Dikembalikan;
            // Handed on again only once it is complete after the revision.
            $procurement->proposal_completed_at = null;
        } elseif ($atTeamLeader && ! $this->teamLeaderApprovalIsFinal($procurement, $reviewer)) {
            $procurement->proposal_state = ProposalState::MenungguPersetujuan;
        } else {
            $procurement->proposal_state = ProposalState::Disetujui;
        }

        // The Asman's decision, or the TL's when it ends the round here.
        if (! $atTeamLeader || $procurement->proposal_state !== ProposalState::MenungguPersetujuan) {
            $procurement->proposal_reviewed_at = now();
            $procurement->proposal_reviewed_by = $reviewer->id;
        }

        $procurement->proposal_review_note = $note;
        $procurement->save();

        $forwarded = $procurement->proposal_state === ProposalState::MenungguPersetujuan;

        $this->recordActivity(
            $procurement,
            $reviewer,
            match (true) {
                ! $approved => ActivityType::UsulanDikembalikan,
                $forwarded => ActivityType::UsulanDisetujuiTl,
                default => ActivityType::UsulanDisetujui,
            },
            match (true) {
                ! $approved => 'Usulan pekerjaan dikembalikan ke pengusul.'.($note !== null ? " Catatan: {$note}" : ''),
                $forwarded => 'Usulan pekerjaan disetujui TL dan diteruskan ke Direksi Pekerjaan (Asman).',
                $atTeamLeader => 'Usulan pekerjaan disetujui TL (Direksi Pekerjaan bidang) dan diteruskan ke perencanaan.',
                default => 'Usulan pekerjaan disetujui Direksi Pekerjaan dan diteruskan ke perencanaan.',
            },
        );

        $notification = new ProposalReviewed($procurement, $approved, $note, $forwarded);
        $procurement->creator?->notify($notification);

        if ($forwarded) {
            $this->notifyDivisionApprovers($procurement);
        } elseif ($approved) {
            // Straight to TL ICC, who appoints the planning PIC.
            $this->notifyHolders(Permission::AssignPlanner, $notification);
        }

        return $procurement;
    }

    /**
     * Send a returned proposal back for approval after the proposer revised it.
     *
     * It starts over from the team leader, who sees what was changed.
     */
    public function resubmitProposal(Procurement $procurement, User $proposer): Procurement
    {
        $procurement->proposal_state = $this->firstApprovalLevel($procurement, $proposer);
        $procurement->proposal_submitted_at = now();
        $procurement->proposal_reviewed_at = null;
        $procurement->proposal_reviewed_by = null;
        $procurement->proposal_tl_reviewed_at = null;
        $procurement->proposal_tl_reviewed_by = null;
        // Announced by syncProposalReadiness when saved, or once the missing
        // items are in.
        $procurement->proposal_completed_at = null;
        $procurement->save();

        return $procurement;
    }

    /**
     * Submit the finished execution to TL Pengadaan, first time or after a
     * revision.
     */
    public function submitExecution(Procurement $procurement, User $actor): Procurement
    {
        $isRevision = $procurement->execution_approval_state === PlanningApprovalState::Ditolak;

        if ($isRevision) {
            $procurement->execution_revision = $procurement->execution_revision + 1;
        }

        $procurement->execution_approval_state = PlanningApprovalState::MenungguPersetujuan;
        $procurement->execution_submitted_at = now();
        // As with the planning, the note of the previous round stays: it is
        // what this round answers.
        $procurement->execution_reviewed_at = null;
        $procurement->execution_reviewed_by = null;
        $procurement->save();

        $this->recordActivity(
            $procurement,
            $actor,
            ActivityType::PelaksanaanDiajukan,
            $isRevision
                ? "Pelaksanaan diajukan ulang ke TL Pengadaan (revisi ke-{$procurement->execution_revision})."
                : 'Pelaksanaan selesai dan diajukan ke TL Pengadaan untuk persetujuan.',
        );

        $this->notifyHolders(Permission::ReviewExecution, new ProcurementMilestone(
            $procurement,
            'Pelaksanaan menunggu persetujuan',
            "Pelaksanaan {$procurement->number} - {$procurement->name} diajukan PIC Pelaksana untuk disetujui.",
        ));

        return $procurement;
    }

    /**
     * Approve the execution, which hands the job to Keuangan, or return it to
     * the execution PIC with a note.
     */
    public function reviewExecution(Procurement $procurement, User $reviewer, bool $approved, ?string $note = null): Procurement
    {
        return DB::transaction(function () use ($procurement, $reviewer, $approved, $note): Procurement {
            $procurement->execution_approval_state = $approved
                ? PlanningApprovalState::Disetujui
                : PlanningApprovalState::Ditolak;
            $procurement->execution_reviewed_at = now();
            $procurement->execution_reviewed_by = $reviewer->id;
            $procurement->execution_review_note = $note;
            $procurement->save();

            $this->recordActivity(
                $procurement,
                $reviewer,
                $approved ? ActivityType::PelaksanaanDisetujui : ActivityType::PelaksanaanDitolak,
                Str::limit($approved
                    ? 'Pelaksanaan disetujui TL Pengadaan dan diteruskan ke Penyerahan.'
                    : 'Pelaksanaan dikembalikan ke PIC Pelaksana untuk diperbaiki.'.($note !== null ? " Catatan: {$note}" : ''), 250),
            );

            if (! $approved) {
                $procurement->executor?->notify(new ProcurementMilestone(
                    $procurement,
                    'Pelaksanaan dikembalikan',
                    "Pelaksanaan {$procurement->name} dikembalikan untuk diperbaiki.".($note !== null ? " Catatan: {$note}" : ''),
                ));

                return $procurement;
            }

            $procurement->executor?->notify(new ProcurementMilestone(
                $procurement,
                'Pelaksanaan disetujui',
                "Pelaksanaan {$procurement->name} disetujui. Lengkapi berkas Penyerahan, lalu ajukan ke TL Pengadaan.",
                'penyerahan',
            ));

            return $this->handToFinance($procurement, $reviewer);
        });
    }

    /**
     * Open Penyerahan and Pembayaran on the approved execution: the
     * execution side files the hand-over papers first.
     */
    protected function handToFinance(Procurement $procurement, User $actor): Procurement
    {
        // Steps added to the master data since the job started are included.
        $this->syncChecklists($procurement);

        $procurement->finance_submitted_at = now();
        $procurement->finance_submitted_by = $actor->id;
        $procurement->save();

        $this->notifyProposers(
            $procurement,
            'Pelaksanaan selesai',
            "Pelaksanaan {$procurement->number} - {$procurement->name} disetujui; berkas penyerahan sedang disiapkan PIC Pelaksana.",
        );

        return $procurement;
    }

    /**
     * The execution PIC submits the hand-over papers to TL Pengadaan.
     */
    public function submitHandover(Procurement $procurement, User $actor): Procurement
    {
        return DB::transaction(function () use ($procurement, $actor): Procurement {
            $resubmitted = $procurement->handover_approval_state === PlanningApprovalState::Ditolak;

            $procurement->handover_approval_state = PlanningApprovalState::MenungguPersetujuan;
            $procurement->handover_submitted_at = now();
            $procurement->handover_submitted_by = $actor->id;
            $procurement->handover_revision = $procurement->handover_revision + ($resubmitted ? 1 : 0);
            $procurement->save();

            $this->recordActivity(
                $procurement,
                $actor,
                ActivityType::PenyerahanDiajukan,
                $resubmitted
                    ? 'Penyerahan diajukan ulang ke TL Pengadaan setelah revisi.'
                    : 'Berkas penyerahan diajukan ke TL Pengadaan.',
            );

            $message = "Penyerahan {$procurement->number} - {$procurement->name} diajukan PIC Pelaksana untuk disetujui.";

            User::query()->active()->get()
                ->filter(fn (User $user): bool => $user->id !== $actor->id && $user->hasPermission(Permission::ReviewHandover))
                ->each(fn (User $user) => $user->notify(new ProcurementMilestone($procurement, 'Penyerahan menunggu persetujuan', $message, 'penyerahan')));

            return $procurement;
        });
    }

    /**
     * TL Pengadaan approves the hand-over, opening the payment to Keuangan,
     * or returns it to the execution PIC with a note.
     */
    public function reviewHandover(Procurement $procurement, User $reviewer, bool $approved, ?string $note = null): Procurement
    {
        return DB::transaction(function () use ($procurement, $reviewer, $approved, $note): Procurement {
            $procurement->handover_approval_state = $approved
                ? PlanningApprovalState::Disetujui
                : PlanningApprovalState::Ditolak;
            $procurement->handover_reviewed_at = now();
            $procurement->handover_reviewed_by = $reviewer->id;
            $procurement->handover_review_note = $note;
            $procurement->save();

            $this->recordActivity(
                $procurement,
                $reviewer,
                $approved ? ActivityType::PenyerahanDisetujui : ActivityType::PenyerahanDikembalikan,
                Str::limit($approved
                    ? 'Penyerahan disetujui TL Pengadaan dan diteruskan ke Pembayaran (Keuangan).'
                    : 'Penyerahan dikembalikan ke PIC Pelaksana untuk diperbaiki.'.($note !== null ? " Catatan: {$note}" : ''), 250),
            );

            if (! $approved) {
                $procurement->executor?->notify(new ProcurementMilestone(
                    $procurement,
                    'Penyerahan dikembalikan',
                    "Penyerahan {$procurement->name} dikembalikan untuk diperbaiki.".($note !== null ? " Catatan: {$note}" : ''),
                    'penyerahan',
                ));

                return $procurement;
            }

            $procurement->executor?->notify(new ProcurementMilestone(
                $procurement,
                'Penyerahan disetujui',
                "Penyerahan {$procurement->name} disetujui dan diteruskan ke Keuangan.",
                'penyerahan',
            ));

            // The papers of the payment may have been added since.
            $this->syncChecklists($procurement);

            $message = "Pengadaan {$procurement->number} - {$procurement->name} siap dibayar: lengkapi berkas pembayaran lalu ajukan.";

            User::query()->active()->get()
                ->filter(fn (User $user): bool => $user->hasPermission(Permission::ProcessPayment))
                ->each(fn (User $user) => $user->notify(new ProcurementMilestone($procurement, 'Tagihan menunggu pembayaran', $message, 'pembayaran')));

            $this->notifyProposers($procurement, 'Diserahkan ke Keuangan', "Pengadaan {$procurement->number} - {$procurement->name} diserahkan ke Keuangan untuk dibayar.");

            return $procurement;
        });
    }

    /**
     * A Keuangan staff member submits the payment: first to their TL, then
     * to the Asman of that TL's division. Without a TL, or with a TL who is
     * that Asman, it goes straight to the Asman.
     *
     * @throws ValidationException when nobody can decide it
     */
    public function submitPayment(Procurement $procurement, User $actor): Procurement
    {
        $teamLeader = $actor->teamLeader()->first();
        $teamLeader = $teamLeader !== null && $teamLeader->is_active && $teamLeader->id !== $actor->id ? $teamLeader : null;
        $division = $teamLeader->work_director_id ?? $actor->work_director_id;

        if ($division === null) {
            throw ValidationException::withMessages([
                'payment' => 'Akun Anda dan TL atasan belum terhubung ke bidang (Direksi Pekerjaan), sehingga Asman yang menyetujui pembayaran tidak diketahui. Hubungi Administrator.',
            ]);
        }

        // A TL who is the division's Asman decides once, as the Asman.
        if ($teamLeader !== null && $teamLeader->work_director_id === $division && $teamLeader->hasPermission(Permission::ApproveProposal)) {
            $teamLeader = null;
        }

        return DB::transaction(function () use ($procurement, $actor, $teamLeader, $division): Procurement {
            $resubmitted = $procurement->payment_approval_state === PaymentApprovalState::Dikembalikan;

            $procurement->payment_approval_state = $teamLeader !== null
                ? PaymentApprovalState::MenungguTl
                : PaymentApprovalState::MenungguAsman;
            $procurement->payment_submitted_at = now();
            $procurement->payment_submitted_by = $actor->id;
            $procurement->payment_team_leader_id = $teamLeader?->id;
            $procurement->payment_work_director_id = $division;
            $procurement->payment_tl_reviewed_at = null;
            $procurement->payment_tl_reviewed_by = null;
            $procurement->payment_revision = $procurement->payment_revision + ($resubmitted ? 1 : 0);
            $procurement->save();

            $this->recordActivity(
                $procurement,
                $actor,
                ActivityType::PembayaranDiajukan,
                ($resubmitted ? 'Pembayaran diajukan ulang setelah revisi' : 'Berkas pembayaran diajukan')
                    .($teamLeader !== null ? " ke TL Keuangan ({$teamLeader->name})." : ' ke Asman.'),
            );

            $this->notifyPaymentDeciders($procurement);

            return $procurement;
        });
    }

    /**
     * The TL, then the Asman, approve the payment or return it to Keuangan
     * with a note. The Asman's approval records the payment and closes the
     * job.
     */
    public function decidePayment(Procurement $procurement, User $reviewer, bool $approved, ?string $note = null): Procurement
    {
        return DB::transaction(function () use ($procurement, $reviewer, $approved, $note): Procurement {
            if (! $approved) {
                $procurement->payment_approval_state = PaymentApprovalState::Dikembalikan;
                $procurement->payment_reviewed_at = now();
                $procurement->payment_reviewed_by = $reviewer->id;
                $procurement->payment_review_note = $note;
                $procurement->save();

                $this->recordActivity(
                    $procurement,
                    $reviewer,
                    ActivityType::PembayaranDikembalikan,
                    Str::limit('Pembayaran dikembalikan ke Keuangan untuk diperbaiki.'.($note !== null ? " Catatan: {$note}" : ''), 250),
                );

                $procurement->paymentSubmitter?->notify(new ProcurementMilestone(
                    $procurement,
                    'Pembayaran dikembalikan',
                    "Pembayaran {$procurement->name} dikembalikan untuk diperbaiki.".($note !== null ? " Catatan: {$note}" : ''),
                    'pembayaran',
                ));

                return $procurement;
            }

            if ($procurement->payment_approval_state === PaymentApprovalState::MenungguTl) {
                $procurement->payment_approval_state = PaymentApprovalState::MenungguAsman;
                $procurement->payment_tl_reviewed_at = now();
                $procurement->payment_tl_reviewed_by = $reviewer->id;
                $procurement->payment_review_note = $note;
                $procurement->save();

                $this->recordActivity(
                    $procurement,
                    $reviewer,
                    ActivityType::PembayaranDisetujuiTl,
                    Str::limit('Pembayaran disetujui TL Keuangan dan diteruskan ke Asman.'.($note !== null ? " Catatan: {$note}" : ''), 250),
                );

                $this->notifyPaymentDeciders($procurement);

                return $procurement;
            }

            $procurement->payment_approval_state = PaymentApprovalState::Disetujui;
            $procurement->payment_reviewed_at = now();
            $procurement->payment_reviewed_by = $reviewer->id;
            $procurement->payment_review_note = $note;
            $procurement->save();

            $procurement->paymentSubmitter?->notify(new ProcurementMilestone(
                $procurement,
                'Pembayaran disetujui',
                "Pembayaran {$procurement->name} disetujui Asman; pengadaan ditutup.",
                'pembayaran',
            ));

            return $this->completePayment($procurement, $reviewer);
        });
    }

    /**
     * Tell whoever decides the payment next that it waits on them: the TL,
     * or the Asman of the division.
     */
    protected function notifyPaymentDeciders(Procurement $procurement): void
    {
        $message = "Pembayaran {$procurement->number} - {$procurement->name} menunggu persetujuan Anda.";

        $deciders = $procurement->payment_approval_state === PaymentApprovalState::MenungguTl
            ? collect([$procurement->paymentTeamLeader()->first()])
            : User::query()->active()
                ->where('work_director_id', $procurement->payment_work_director_id)
                ->get()
                ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal));

        $deciders
            ->filter(fn (?User $user): bool => $user !== null && $user->id !== $procurement->payment_submitted_by)
            ->each(fn (User $user) => $user->notify(new ProcurementMilestone($procurement, 'Pembayaran menunggu persetujuan', $message, 'pembayaran')));
    }

    /**
     * Record the payment and close the procurement.
     *
     * The job moves to the closing progress status (category Selesai), so
     * every count of finished work agrees with it.
     */
    public function completePayment(Procurement $procurement, User $actor): Procurement
    {
        $procurement->payment_completed_at = now();
        $procurement->payment_completed_by = $actor->id;
        $procurement->completed_at = now();

        // "Selesai" when it exists, otherwise the last finished status.
        $closing = ProgressStatus::query()
            ->active()
            ->where('category', StatusCategory::Selesai->value)
            ->orderByRaw('case when name = ? then 0 else 1 end', [FinanceStageSeeder::CLOSED_STATUS])
            ->orderByDesc('sort_order')
            ->first();

        if ($closing !== null) {
            $procurement->progress_status_id = $closing->id;
        }

        $procurement->save();

        $this->recordActivity(
            $procurement,
            $actor,
            ActivityType::PembayaranSelesai,
            'Pembayaran ke mitra selesai, pengadaan ditutup.',
        );

        $message = "Pembayaran {$procurement->name} selesai dan pengadaan ditutup.";

        $this->notifyProposers($procurement, 'Pengadaan selesai', $message);
        $procurement->executor?->notify(new ProcurementMilestone($procurement, 'Pengadaan selesai', $message));
        $this->notifySupervisors(new ProcurementMilestone($procurement, 'Pengadaan selesai', $message));

        return $procurement;
    }

    /**
     * Keep the division that proposed the work informed of its progress.
     */
    protected function notifyProposers(Procurement $procurement, string $title, string $message): void
    {
        if ($procurement->proposal_submitted_at === null) {
            return;
        }

        $notification = new ProcurementMilestone($procurement, $title, $message);

        $recipients = collect([$procurement->creator, $procurement->creator?->teamLeader])
            ->merge(
                User::query()->active()
                    ->where('work_director_id', $procurement->work_director_id)
                    ->get()
                    ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal)),
            )
            ->filter()
            ->unique('id');

        $recipients->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * Keep the supporting files sent with a proposal.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function attachFiles(Procurement $procurement, array $files, User $uploader): int
    {
        foreach ($files as $file) {
            $procurement->attachments()->create([
                // A random name with the extension of the content.
                'path' => $file->store('proposal-attachments/'.$procurement->id, 'local'),
                'file_name' => $file->getClientOriginalName(),
                'mime' => UploadedFileType::mime($file),
                'size' => $file->getSize(),
                'uploaded_by' => $uploader->id,
            ]);
        }

        return count($files);
    }

    /**
     * Tell the Direksi Pekerjaan of the proposal's division that it waits on them.
     */
    protected function notifyDivisionApprovers(Procurement $procurement): void
    {
        User::query()
            ->active()
            ->where('work_director_id', $procurement->work_director_id)
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal))
            ->each(fn (User $user) => $user->notify(new ProposalSubmitted($procurement)));
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
     * Send a notification to every active account holding a right; the
     * administrator holds every right, so it is always told.
     */
    protected function notifyHolders(Permission $permission, mixed $notification): void
    {
        User::query()
            ->active()
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission($permission))
            ->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * Send a notification to every supervisor of the procurement process.
     */
    protected function notifySupervisors(mixed $notification): void
    {
        User::query()
            ->active()
            ->withRole([UserRole::TeamLeaderPengadaan, UserRole::TeamLeaderIcc, UserRole::Administrator])
            ->get()
            ->each(fn (User $user) => $user->notify($notification));
    }
}
