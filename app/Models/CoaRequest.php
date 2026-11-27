<?php

namespace App\Models;

use App\Enums\CoaRequestStatus;
use App\Enums\CoaSegment;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Support\CoaCatalog;
use App\Support\CoaUsage;
use Carbon\CarbonImmutable;
use Database\Factories\CoaRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A COA activation request initiated by Staf / TL Pengusul and reviewed,
 * editable, and activated by Keuangan. Activated COA codes sync directly into
 * the work proposal form.
 *
 * @property int $id
 * @property int $requested_by
 * @property int|null $work_director_id
 * @property string $segment_1
 * @property string $segment_2
 * @property string $segment_3
 * @property string $segment_4
 * @property string $segment_5
 * @property string $segment_6
 * @property string $segment_7
 * @property string $expense_element
 * @property string $coa_code
 * @property string|null $description
 * @property string|null $notes
 * @property CoaRequestStatus $status
 * @property CarbonImmutable|null $requested_at
 * @property int|null $activated_by
 * @property CarbonImmutable|null $activated_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $requester
 * @property-read WorkDirector|null $workDirector
 * @property-read User|null $activator
 * @property-read User|null $updater
 */
#[Fillable([
    'requested_by',
    'work_director_id',
    'segment_1',
    'segment_2',
    'segment_3',
    'segment_4',
    'segment_5',
    'segment_6',
    'segment_7',
    'expense_element',
    'coa_code',
    'description',
    'notes',
    'status',
    'requested_at',
    'activated_by',
    'activated_at',
    'updated_by',
])]
class CoaRequest extends Model
{
    /** @use HasFactory<CoaRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CoaRequestStatus::class,
            'requested_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }

    /**
     * The user who requested this COA.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The division / Direksi Pekerjaan associated with the requester.
     *
     * @return BelongsTo<WorkDirector, $this>
     */
    public function workDirector(): BelongsTo
    {
        return $this->belongsTo(WorkDirector::class);
    }

    /**
     * The Keuangan / Administrator user who activated the COA.
     *
     * @return BelongsTo<User, $this>
     */
    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * The user who last updated this COA request.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Limit the query to activated COA codes.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CoaRequestStatus::Active->value);
    }

    /**
     * Limit the query to COA requests visible to the given user.
     *
     * - Administrator sees every record.
     * - Keuangan (coa.manage) sees all submitted and active requests across
     *   every division, plus their own drafts if any.
     * - Staf / TL Pengusul see their own requests, their division's requests,
     *   and all activated COAs so they can reuse existing active codes.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Administrator) {
            return $query;
        }

        if ($user->hasPermission(Permission::ManageCoaRequests)) {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder->whereIn('status', [
                    CoaRequestStatus::Pending->value,
                    CoaRequestStatus::Active->value,
                ])->orWhere('requested_by', $user->id);
            });
        }

        $divisionId = $user->divisionId();

        return $query->where(function (Builder $builder) use ($user, $divisionId): void {
            $builder->where('requested_by', $user->id)
                ->orWhere('status', CoaRequestStatus::Active->value);

            if ($divisionId !== null) {
                $builder->orWhere('work_director_id', $divisionId);
            }
        });
    }

    /**
     * Build the active COA list synced into the procurement / proposal form.
     *
     * @return array<int, array{id: int, value: string, label: string, description: string|null, summary: string, workDirectorId: int|null, usageCount: int}>
     */
    public static function activeOptions(): array
    {
        $labels = CoaCatalog::labelMap();
        $counts = CoaUsage::counts();

        return self::query()
            ->active()
            ->latest('activated_at')
            ->latest('id')
            ->get()
            ->unique('coa_code')
            ->values()
            ->map(function (self $coa) use ($labels, $counts): array {
                $summaryParts = array_filter([
                    $labels[CoaSegment::Segment3->value][$coa->segment_3] ?? null,
                    $labels[CoaSegment::Segment4->value][$coa->segment_4] ?? null,
                    $labels[CoaSegment::Segment6->value][$coa->segment_6] ?? null,
                    $labels[CoaSegment::Segment7->value][$coa->segment_7] ?? null,
                ]);

                return [
                    'id' => $coa->id,
                    'value' => $coa->coa_code,
                    'label' => $coa->coa_code,
                    'description' => $coa->description,
                    'summary' => implode(' · ', $summaryParts),
                    'workDirectorId' => $coa->work_director_id,
                    // How many usulan / pengadaan already use this code.
                    'usageCount' => $counts[mb_strtoupper($coa->coa_code)] ?? 0,
                ];
            })
            ->all();
    }
}
