<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VendorAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A performance assessment of one vendor on one procurement.
 *
 * @property int $id
 * @property int|null $procurement_id
 * @property string $project
 * @property string|null $po_number
 * @property CarbonImmutable|null $po_date
 * @property string $vendor_name
 * @property string $form_number
 * @property string $revision_number
 * @property CarbonImmutable|null $form_date
 * @property string $place
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'procurement_id',
    'project',
    'po_number',
    'po_date',
    'vendor_name',
    'form_number',
    'revision_number',
    'form_date',
    'place',
    'notes',
])]
class VendorAssessment extends Model
{
    /** @use HasFactory<VendorAssessmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The procurement being assessed, when it came from one.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * The user who opened this assessment.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The signing links issued for this assessment.
     *
     * @return HasMany<VendorAssessmentInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(VendorAssessmentInvitation::class);
    }

    /**
     * The link issued for one sheet, if there is one.
     */
    public function invitationForForm(int $formId): ?VendorAssessmentInvitation
    {
        return $this->invitations->firstWhere('assessment_form_id', $formId);
    }

    /**
     * Every score on every sheet of this assessment.
     *
     * @return HasMany<VendorAssessmentScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(VendorAssessmentScore::class);
    }

    /**
     * The average level of one aspect across every assessor who scored it.
     *
     * Unscored rows are left out rather than counted as zero, so a sheet that
     * has not been filled in yet cannot drag the recap down.
     */
    public function averageFor(int $aspectId): ?float
    {
        $levels = $this->scores
            ->where('assessment_aspect_id', $aspectId)
            ->whereNotNull('level')
            ->pluck('level');

        return $levels->isEmpty() ? null : round($levels->avg(), 2);
    }

    /**
     * The overall average across every aspect that has been scored.
     */
    public function overallAverage(): ?float
    {
        $levels = $this->scores->whereNotNull('level')->pluck('level');

        return $levels->isEmpty() ? null : round($levels->avg(), 2);
    }

    /**
     * The scores belonging to one assessor sheet.
     *
     * @return Collection<int, VendorAssessmentScore>
     */
    public function scoresForForm(int $formId): Collection
    {
        return $this->scores->where('assessment_form_id', $formId)->values();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'form_date' => 'date',
        ];
    }
}
