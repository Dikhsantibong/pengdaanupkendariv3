<?php

namespace App\Models;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use Carbon\CarbonImmutable;
use Database\Factories\ProcurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $number
 * @property string $name
 * @property int $work_director_id
 * @property int $target_unit_id
 * @property int|null $procurement_method_id
 * @property int|null $budget_source_id
 * @property int|null $contract_type_id
 * @property string|null $manager_memo_number
 * @property int|null $pr_ro_number_id
 * @property string|null $prk_number
 * @property string $hpe_value
 * @property int $progress_status_id
 * @property int|null $planner_id
 * @property int|null $executor_id
 * @property PlanningApprovalState $planning_approval_state
 * @property CarbonImmutable|null $planning_submitted_at
 * @property CarbonImmutable|null $planning_reviewed_at
 * @property int|null $planning_reviewed_by
 * @property string|null $planning_review_note
 * @property int $planning_revision
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $target_completion_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read WorkDirector $workDirector
 * @property-read TargetUnit $targetUnit
 * @property-read ProgressStatus $progressStatus
 */
#[Fillable([
    'name',
    'work_director_id',
    'target_unit_id',
    'procurement_method_id',
    'budget_source_id',
    'contract_type_id',
    'manager_memo_number',
    'pr_ro_number_id',
    'prk_number',
    'hpe_value',
    'progress_status_id',
    'target_completion_date',
    'notes',
])]
class Procurement extends Model
{
    /** @use HasFactory<ProcurementFactory> */
    use HasFactory, SoftDeletes;

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
     * The unit this procurement is destined for.
     *
     * @return BelongsTo<TargetUnit, $this>
     */
    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(TargetUnit::class)->withTrashed();
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
     * The kind of contract this procurement will be awarded under.
     *
     * @return BelongsTo<ContractType, $this>
     */
    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class)->withTrashed();
    }

    /**
     * The PR/RO number attached to this procurement.
     *
     * @return BelongsTo<PrRoNumber, $this>
     */
    public function prRoNumber(): BelongsTo
    {
        return $this->belongsTo(PrRoNumber::class)->withTrashed();
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
     * The audit trail of this procurement.
     *
     * @return HasMany<ProcurementActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class);
    }

    /**
     * Limit the query to the procurements a given user is allowed to see.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role->isSupervisor()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('planner_id', $user->id)->orWhere('executor_id', $user->id);
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
        return $this->checklists
            ->where('stage', ProcurementStage::Perencanaan)
            ->where('is_completed', false)
            ->filter(fn (ProcurementChecklist $checklist): bool => ! $checklist->checklistItem->is_optional)
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
            'planning_approval_state' => PlanningApprovalState::class,
            'planning_submitted_at' => 'datetime',
            'planning_reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'target_completion_date' => 'date',
        ];
    }
}
