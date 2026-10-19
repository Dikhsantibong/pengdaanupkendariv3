<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\StatusCategory;
use App\Enums\WorkCategory;
use App\Observers\ProcurementDocumentObserver;
use App\Services\ProcurementService;
use App\Services\ProgressStatusResolver;
use App\Support\ProposalDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\ProcurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $number
 * @property int|null $contract_number_format_id
 * @property string $name
 * @property string|null $partner_name
 * @property string|null $partner_vendor_code
 * @property string|null $partner_director_name
 * @property string|null $partner_address
 * @property int|null $partner_candidate_id
 * @property int $work_director_id
 * @property int $target_unit_id
 * @property int|null $procurement_method_id
 * @property int|null $budget_source_id
 * @property int|null $contract_type_id
 * @property WorkCategory|null $work_category
 * @property ProposalState $proposal_state
 * @property CarbonImmutable|null $proposal_submitted_at
 * @property CarbonImmutable|null $proposal_completed_at
 * @property CarbonImmutable|null $proposal_reviewed_at
 * @property int|null $proposal_reviewed_by
 * @property CarbonImmutable|null $proposal_tl_reviewed_at
 * @property int|null $proposal_tl_reviewed_by
 * @property CarbonImmutable|null $finance_submitted_at
 * @property int|null $finance_submitted_by
 * @property CarbonImmutable|null $payment_completed_at
 * @property int|null $payment_completed_by
 * @property string|null $proposal_review_note
 * @property string|null $manager_memo_number
 * @property string|null $pr_po_number
 * @property string|null $prk_number
 * @property string|null $proposal_memo_number
 * @property CarbonImmutable|null $proposal_memo_date
 * @property string|null $icc_memo_number
 * @property CarbonImmutable|null $icc_memo_date
 * @property string|null $coa_number
 * @property string|null $wo_number
 * @property string|null $sr_number
 * @property string|null $quotation_number
 * @property CarbonImmutable|null $quotation_date
 * @property string $hpe_value
 * @property string|null $value_after_negotiation
 * @property int $progress_status_id
 * @property int|null $planner_id
 * @property int|null $executor_id
 * @property PlanningApprovalState $planning_approval_state
 * @property CarbonImmutable|null $planning_submitted_at
 * @property CarbonImmutable|null $planning_reviewed_at
 * @property int|null $planning_reviewed_by
 * @property string|null $planning_review_note
 * @property int $planning_revision
 * @property PlanningApprovalState $execution_approval_state
 * @property CarbonImmutable|null $execution_submitted_at
 * @property CarbonImmutable|null $execution_reviewed_at
 * @property int|null $execution_reviewed_by
 * @property string|null $execution_review_note
 * @property int $execution_revision
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $target_completion_date
 * @property CarbonImmutable|null $execution_start_date
 * @property int|null $execution_duration_days
 * @property int|null $warranty_months
 * @property string|null $bank_account_number
 * @property string|null $bank_name
 * @property string|null $bank_account_holder
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read WorkDirector $workDirector
 * @property-read TargetUnit $targetUnit
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TargetUnit> $targetUnits
 * @property-read ProgressStatus $progressStatus
 */
#[ObservedBy(ProcurementDocumentObserver::class)]
#[Fillable([
    'name',
    'partner_name',
    'partner_vendor_code',
    'partner_director_name',
    'partner_address',
    'work_director_id',
    'target_unit_id',
    'procurement_method_id',
    'budget_source_id',
    'contract_type_id',
    'contract_number_format_id',
    'work_category',
    'manager_memo_number',
    'pr_po_number',
    'prk_number',
    'proposal_memo_number',
    'proposal_memo_date',
    'icc_memo_number',
    'icc_memo_date',
    'coa_number',
    'wo_number',
    'sr_number',
    'quotation_number',
    'quotation_date',
    'hpe_value',
    'value_after_negotiation',
    'progress_status_id',
    'target_completion_date',
    'notes',
])]
class Procurement extends Model
{
    /** @use HasFactory<ProcurementFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Defaults before the row is read back: anything not raised as a
     * proposal is approved from the start.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'proposal_state' => 'disetujui',
        'execution_approval_state' => 'belum_diajukan',
        'execution_revision' => 0,
    ];

    /**
     * The official directing this procurement.
     *
     * @return BelongsTo<WorkDirector, $this>
     */
    public function workDirector(): BelongsTo
    {
        return $this->belongsTo(WorkDirector::class)->withTrashed();
    }

    /**
     * Keep the pivot in step with the single unit column.
     *
     * Every procurement serves at least the unit in `target_unit_id`. Writing it
     * into the pivot as soon as the row exists means a procurement made by a
     * factory, a seeder or the service is always found by unit, before the
     * service replaces the list with the full selection.
     */
    protected static function booted(): void
    {
        // The method is not asked on the form: it follows the contract format
        // (set on the Format No Kontrak master data), on every path that
        // chooses or changes the format. A format without a method leaves the
        // procurement's own method as it was.
        static::saving(function (Procurement $procurement): void {
            if ($procurement->contract_number_format_id === null
                || ! ($procurement->isDirty('contract_number_format_id') || $procurement->procurement_method_id === null)) {
                return;
            }

            $methodId = ContractNumberFormat::withTrashed()
                ->whereKey($procurement->contract_number_format_id)
                ->value('procurement_method_id');

            if ($methodId !== null) {
                $procurement->procurement_method_id = (int) $methodId;
            }
        });

        // The progress status is set by the system (ProgressStatusResolver);
        // a new job starts on the default status until it is worked out.
        static::creating(function (Procurement $procurement): void {
            // Not set yet on a job created without one (the column is filled
            // here before the row exists).
            $default = ProgressStatus::defaultStatus();

            if ($procurement->getAttribute('progress_status_id') === null && $default !== null) {
                $procurement->progress_status_id = $default->id;
            }
        });

        static::saved(function (Procurement $procurement): void {
            // A status given on creation or set by hand enters the history;
            // the automatic one is recorded where it is set.
            if ($procurement->wasRecentlyCreated || $procurement->wasChanged('progress_status_id')) {
                ProcurementStatusHistory::record($procurement->id, (int) $procurement->progress_status_id);
            }

            // Whether a waiting proposal may reach its TL / Asman yet, then
            // the status that follows from it.
            app(ProcurementService::class)->syncProposalReadiness($procurement);
            app(ProgressStatusResolver::class)->sync($procurement);
        });

        static::created(function (Procurement $procurement): void {
            $procurement->targetUnits()->syncWithoutDetaching([
                $procurement->target_unit_id => ['sort_order' => 1],
            ]);
        });
    }

    /**
     * The first unit this procurement serves.
     *
     * Kept alongside the full list so existing single-unit queries still work.
     *
     * @return BelongsTo<TargetUnit, $this>
     */
    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(TargetUnit::class)->withTrashed();
    }

    /**
     * Every unit this procurement serves, in the order they were chosen.
     *
     * @return BelongsToMany<TargetUnit, $this>
     */
    public function targetUnits(): BelongsToMany
    {
        return $this->belongsToMany(TargetUnit::class, 'procurement_target_unit')
            ->withTrashed()
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * The units served, falling back to the single unit for a procurement
     * whose list has not been written yet.
     *
     * @return Collection<int, TargetUnit>
     */
    public function servedUnits(): Collection
    {
        $units = $this->targetUnits->toBase();

        if ($units->isEmpty()) {
            return collect([$this->targetUnit]);
        }

        return $units->values();
    }

    /**
     * The names of the units served, joined for display.
     */
    public function targetUnitNames(): string
    {
        return $this->servedUnits()->pluck('name')->implode(', ');
    }

    /**
     * The method this procurement is carried out with.
     *
     * @return BelongsTo<ProcurementMethod, $this>
     */
    public function procurementMethod(): BelongsTo
    {
        return $this->belongsTo(ProcurementMethod::class)->withTrashed();
    }

    /**
     * The budget this procurement is funded from.
     *
     * @return BelongsTo<BudgetSource, $this>
     */
    public function budgetSource(): BelongsTo
    {
        return $this->belongsTo(BudgetSource::class)->withTrashed();
    }

    /**
     * The format the contract number of this procurement was drawn from.
     *
     * @return BelongsTo<ContractNumberFormat, $this>
     */
    public function contractNumberFormat(): BelongsTo
    {
        return $this->belongsTo(ContractNumberFormat::class)->withTrashed();
    }

    /**
     * The kind of contract this procurement will be awarded under.
     *
     * @return BelongsTo<ContractType, $this>
     */
    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class)->withTrashed();
    }

    /**
     * The current progress status.
     *
     * @return BelongsTo<ProgressStatus, $this>
     */
    public function progressStatus(): BelongsTo
    {
        return $this->belongsTo(ProgressStatus::class)->withTrashed();
    }

    /**
     * The PIC responsible for the planning stage.
     *
     * @return BelongsTo<User, $this>
     */
    public function planner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planner_id');
    }

    /**
     * The PIC responsible for the execution stage.
     *
     * @return BelongsTo<User, $this>
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    /**
     * The team leader who reviewed the planning documents.
     *
     * @return BelongsTo<User, $this>
     */
    public function planningReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planning_reviewed_by');
    }

    /**
     * The team leader who reviewed the execution.
     *
     * @return BelongsTo<User, $this>
     */
    public function executionReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execution_reviewed_by')->withTrashed();
    }

    /**
     * The team leader who decided on the proposal first.
     *
     * @return BelongsTo<User, $this>
     */
    public function proposalTeamLeaderReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposal_tl_reviewed_by')->withTrashed();
    }

    /**
     * Who handed the job to Keuangan.
     *
     * @return BelongsTo<User, $this>
     */
    public function financeSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_submitted_by')->withTrashed();
    }

    /**
     * Who recorded the payment and closed the job.
     *
     * @return BelongsTo<User, $this>
     */
    public function paymentCompleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_completed_by')->withTrashed();
    }

    /**
     * Whether the job has been handed to Keuangan and is still being paid.
     */
    public function isInFinance(): bool
    {
        return $this->finance_submitted_at !== null && $this->payment_completed_at === null;
    }

    /**
     * Whether Keuangan has paid the partner.
     */
    public function isPaid(): bool
    {
        return $this->payment_completed_at !== null;
    }

    /**
     * Where the job is in its journey, as a key and a label.
     *
     * @return array{key: string, label: string}
     */
    public function currentStage(): array
    {
        [$key, $label] = match (true) {
            $this->completed_at !== null => ['selesai', 'Selesai'],
            $this->finance_submitted_at !== null => ['keuangan', 'Keuangan'],
            $this->isPlanningApproved() => ['pelaksanaan', 'Pelaksanaan'],
            $this->isProposalApproved() => ['perencanaan', 'Perencanaan'],
            default => ['usulan', 'Usulan'],
        };

        return ['key' => $key, 'label' => $label];
    }

    /**
     * The identity data a work proposal must carry before its TL / Asman
     * sees it, and before planning can be submitted.
     *
     * The SR and WO numbers are optional. The Direksi Pekerjaan and the unit
     * are not listed: the database never stores a job without them.
     *
     * @return array<int, string>
     */
    public function missingProposalData(): array
    {
        $filled = fn (mixed $value): bool => $value !== null && trim((string) $value) !== '';

        return array_keys(array_filter([
            'Nama Pengadaan' => ! $filled($this->name),
            'Sumber Anggaran' => $this->budget_source_id === null,
            'Nomor PRK' => ! $filled($this->prk_number),
            'Nomor PR' => ! $filled($this->pr_po_number),
            'Nomor COA' => ! $filled($this->coa_number),
            'Nomor Nota Dinas Usulan' => ! $filled($this->proposal_memo_number),
            'Tanggal Nota Dinas Usulan' => $this->proposal_memo_date === null,
        ]));
    }

    /**
     * Everything the proposer still owes: the identity data, then the signed
     * TOR (generated from its template) and Nota Dinas Usulan.
     *
     * @return array<int, string>
     */
    public function missingProposalRequirements(): array
    {
        return [
            ...$this->missingProposalData(),
            ...array_map(fn (string $document): string => "Unggahan {$document}", ProposalDocuments::missing($this)),
        ];
    }

    /**
     * Whether a proposal waiting for approval is still held with its proposer
     * because something is missing.
     */
    public function isProposalHeld(): bool
    {
        return in_array($this->proposal_state, [ProposalState::MenungguPersetujuanTl, ProposalState::MenungguPersetujuan], true)
            && $this->proposal_completed_at === null;
    }

    /**
     * The procurement data still missing before planning documents are drawn.
     *
     * A job that came in as a work proposal has no contract format or
     * category yet; its documents would otherwise pick the wrong template,
     * so generating and submitting wait until procurement has filled them.
     * The method follows the format, so it is not asked separately. A
     * procurement registered directly already had them asked on its form.
     *
     * @return array<int, string>
     */
    public function missingPlanningData(): array
    {
        if ($this->proposal_submitted_at === null) {
            return [];
        }

        $missing = [];

        if ($this->contract_number_format_id === null) {
            $missing[] = 'Jenis No Kontrak';
        } elseif ($this->contractNumberFormat?->uses_work_category === true && $this->contract_type_id === null) {
            $missing[] = 'Kategori Kontrak';
        }

        return $missing;
    }

    /**
     * The mandatory steps of a stage that are still outstanding.
     *
     * @return Collection<int, ProcurementChecklist>
     */
    public function pendingRequiredChecklists(ProcurementStage $stage): Collection
    {
        // A Penyerahan / Pembayaran requirement ticked without its upload
        // still counts as pending.
        return $this->checklists
            ->where('stage', $stage)
            ->filter(fn (ProcurementChecklist $checklist): bool => ! $checklist->checklistItem->is_optional)
            ->filter(fn (ProcurementChecklist $checklist): bool => ! $checklist->is_completed
                || ($checklist->needsOwnUpload() && ! $checklist->files()->exists()))
            ->sortBy(fn (ProcurementChecklist $checklist): int => $checklist->checklistItem->sort_order)
            ->values();
    }

    /**
     * The Direksi Pekerjaan account that decided on the proposal.
     *
     * @return BelongsTo<User, $this>
     */
    public function proposalReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposal_reviewed_by');
    }

    /**
     * The supporting files sent with the work proposal.
     *
     * @return HasMany<ProcurementAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProcurementAttachment::class)->oldest('id');
    }

    /**
     * Whether the Direksi Pekerjaan has approved the proposal, so that the
     * procurement may be planned.
     */
    public function isProposalApproved(): bool
    {
        return $this->proposal_state === ProposalState::Disetujui;
    }

    /**
     * Limit the query to procurements past the proposal step.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeProposalApproved(Builder $query): Builder
    {
        return $query->where('proposal_state', ProposalState::Disetujui->value);
    }

    /**
     * The user who registered this procurement.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Every checklist row belonging to this procurement.
     *
     * @return HasMany<ProcurementChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(ProcurementChecklist::class);
    }

    /**
     * Every document generated for this procurement.
     *
     * @return HasMany<ProcurementDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementDocument::class);
    }

    /**
     * The partner candidates (calon mitra) listed in planning.
     *
     * @return HasMany<ProcurementPartnerCandidate, $this>
     */
    public function partnerCandidates(): HasMany
    {
        return $this->hasMany(ProcurementPartnerCandidate::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Whether the partner is appointed directly (SPPL, let by Surat
     * Pesanan): one partner, no competing candidates.
     */
    public function isDirectAppointment(): bool
    {
        $this->loadMissing(['contractNumberFormat', 'procurementMethod']);

        return $this->contractNumberFormat?->code === 'SPPL'
            || $this->procurementMethod?->code === 'surat-pesanan';
    }

    /**
     * The candidate picked as the executor in the "Pelaksana" step.
     *
     * @return BelongsTo<ProcurementPartnerCandidate, $this>
     */
    public function partnerCandidate(): BelongsTo
    {
        return $this->belongsTo(ProcurementPartnerCandidate::class, 'partner_candidate_id');
    }

    /**
     * The amendments of this procurement's contract, oldest first.
     *
     * @return HasMany<ProcurementAmendment, $this>
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(ProcurementAmendment::class)->orderBy('sequence');
    }

    /**
     * The Delivery Orders called off under the contract (KHS), oldest first.
     *
     * @return HasMany<ProcurementDeliveryOrder, $this>
     */
    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(ProcurementDeliveryOrder::class)->orderBy('sequence');
    }

    /**
     * The price list of a KHS contract, from which the Delivery Orders draw
     * their lines.
     *
     * @return HasMany<ProcurementContractItem, $this>
     */
    public function contractItems(): HasMany
    {
        return $this->hasMany(ProcurementContractItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Whether the contract is called off by Delivery Orders: an SPK or PJ
     * whose jenis kontrak is KHS.
     */
    public function usesDeliveryOrders(): bool
    {
        return in_array($this->contractNumberFormat?->code, ['SPK', 'PJ'], true)
            && $this->contractType?->code === 'khs';
    }

    /**
     * Every progress status the procurement entered, in order.
     *
     * @return HasMany<ProcurementStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProcurementStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * The audit trail of this procurement.
     *
     * @return HasMany<ProcurementActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class);
    }

    /**
     * Limit the query to procurements still being worked on: neither
     * finished nor cancelled.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereNull('completed_at')
            ->whereHas('progressStatus', fn (Builder $status) => $status->whereNotIn('category', [
                StatusCategory::Selesai->value,
                StatusCategory::Batal->value,
            ]));
    }

    /**
     * Limit the query to the procurements a given user is allowed to see.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission(Permission::ViewAllProcurements)) {
            return $query;
        }

        // Whoever registered a procurement keeps sight of it, so a PIC given
        // the right to create one can follow it up. A Direksi Pekerjaan sees
        // every procurement of the division they lead.
        $leadsDivision = $user->work_director_id !== null
            && $user->hasPermission(Permission::ApproveProposal);
        $paysPartners = $user->hasPermission(Permission::ProcessPayment);

        return $query->where(function (Builder $builder) use ($user, $leadsDivision, $paysPartners): void {
            $builder->where('planner_id', $user->id)
                ->orWhere('executor_id', $user->id)
                ->orWhere('created_by', $user->id)
                // A team leader follows the work their staff proposed.
                ->orWhereIn($builder->qualifyColumn('created_by'), User::query()
                    ->select('id')
                    ->where('team_leader_id', $user->id));

            if ($leadsDivision) {
                // Qualified: report queries join tables that share the column.
                $builder->orWhere($builder->qualifyColumn('work_director_id'), $user->work_director_id);
            }

            // Keuangan sees every job handed to it.
            if ($paysPartners) {
                $builder->orWhereNotNull($builder->qualifyColumn('finance_submitted_at'));
            }
        });
    }

    /**
     * Determine whether the given user is an assigned PIC of this procurement.
     */
    public function isAssignedTo(User $user): bool
    {
        return $this->planner_id === $user->id || $this->executor_id === $user->id;
    }

    /**
     * Determine whether the planning documents have been approved.
     */
    public function isPlanningApproved(): bool
    {
        return $this->planning_approval_state === PlanningApprovalState::Disetujui;
    }

    /**
     * The signed document filed for a document type on this procurement.
     */
    public function signedDocumentFor(int $documentTypeId): ?ProcurementDocument
    {
        return $this->documents
            ->where('document_type_id', $documentTypeId)
            ->first(fn (ProcurementDocument $document): bool => $document->isSigned());
    }

    /**
     * The latest document generated for a document type on this procurement.
     */
    public function documentFor(int $documentTypeId): ?ProcurementDocument
    {
        return $this->documents
            ->where('document_type_id', $documentTypeId)
            ->sortByDesc('generated_at')
            ->first();
    }

    /**
     * Determine whether the execution PIC's submission waits for a decision.
     */
    public function isExecutionAwaitingApproval(): bool
    {
        return $this->execution_approval_state === PlanningApprovalState::MenungguPersetujuan;
    }

    /**
     * Determine whether the planning stage was sent back for revision.
     */
    public function needsPlanningRevision(): bool
    {
        return $this->planning_approval_state === PlanningApprovalState::Ditolak;
    }

    /**
     * The mandatory planning steps that are still outstanding.
     *
     * Steps flagged optional in the master data are excluded: they are there
     * for the procurements that happen to need them, so they must never hold
     * up a submission.
     *
     * @return Collection<int, ProcurementChecklist>
     */
    public function pendingRequiredPlanningChecklists(): Collection
    {
        // A step ticked before its input was asked for (or whose data was
        // cleared since) still counts as pending until that data is in.
        return $this->checklists
            ->where('stage', ProcurementStage::Perencanaan)
            ->filter(fn (ProcurementChecklist $checklist): bool => ! $checklist->checklistItem->is_optional)
            ->filter(fn (ProcurementChecklist $checklist): bool => ! $checklist->is_completed
                || ($checklist->checklistItem->input_kind?->isFilledOn($this) === false))
            ->sortBy(fn (ProcurementChecklist $checklist): int => $checklist->checklistItem->sort_order)
            ->values();
    }

    /**
     * Determine whether every mandatory planning step has been ticked.
     */
    public function isPlanningChecklistComplete(): bool
    {
        return $this->pendingRequiredPlanningChecklists()->isEmpty();
    }

    /**
     * Determine whether the procurement has been finished.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null
            || $this->progressStatus->category === StatusCategory::Selesai;
    }

    /**
     * Count the completed and total checklist rows for a stage.
     *
     * @return array{completed: int, total: int, percentage: int}
     */
    public function checklistProgress(ProcurementStage $stage): array
    {
        $rows = $this->checklists->where('stage', $stage);
        $total = $rows->count();
        $completed = $rows->where('is_completed', true)->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? (int) round($completed / $total * 100) : 0,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hpe_value' => 'decimal:2',
            'value_after_negotiation' => 'decimal:2',
            'planning_approval_state' => PlanningApprovalState::class,
            'planning_submitted_at' => 'datetime',
            'planning_reviewed_at' => 'datetime',
            'execution_approval_state' => PlanningApprovalState::class,
            'execution_submitted_at' => 'datetime',
            'execution_reviewed_at' => 'datetime',
            'execution_revision' => 'integer',
            'completed_at' => 'datetime',
            'finance_submitted_at' => 'datetime',
            'payment_completed_at' => 'datetime',
            'proposal_tl_reviewed_at' => 'datetime',
            'target_completion_date' => 'date',
            'proposal_memo_date' => 'date',
            'icc_memo_date' => 'date',
            'quotation_date' => 'date',
            'execution_start_date' => 'date',
            'execution_duration_days' => 'integer',
            'warranty_months' => 'integer',
            'work_category' => WorkCategory::class,
            'proposal_state' => ProposalState::class,
            'proposal_submitted_at' => 'datetime',
            'proposal_completed_at' => 'datetime',
            'proposal_reviewed_at' => 'datetime',
        ];
    }

    /**
     * The last day of the execution period.
     *
     * The start date counts as day one, so 30 days from 1 October ends on
     * 30 October — the way "30 hari kalender" is read on a contract.
     */
    public function executionEndDate(): ?CarbonImmutable
    {
        if ($this->execution_start_date === null || $this->execution_duration_days === null) {
            return null;
        }

        return $this->execution_start_date->addDays($this->execution_duration_days - 1);
    }
}
