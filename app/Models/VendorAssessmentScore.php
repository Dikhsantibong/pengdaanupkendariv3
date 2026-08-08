<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One assessor's level for one aspect on one assessment.
 *
 * @property int $id
 * @property int $vendor_assessment_id
 * @property int $assessment_form_id
 * @property int $assessment_aspect_id
 * @property int|null $level
 * @property string|null $note
 * @property int|null $scored_by
 * @property CarbonImmutable|null $scored_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'assessment_form_id',
    'assessment_aspect_id',
    'level',
    'note',
    'scored_by',
    'scored_at',
])]
class VendorAssessmentScore extends Model
{
    /**
     * The assessment this score belongs to.
     *
     * @return BelongsTo<VendorAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(VendorAssessment::class, 'vendor_assessment_id');
    }

    /**
     * The assessor sheet this score was given on.
     *
     * @return BelongsTo<AssessmentForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id')->withTrashed();
    }

    /**
     * The aspect being scored.
     *
     * @return BelongsTo<AssessmentAspect, $this>
     */
    public function aspect(): BelongsTo
    {
        return $this->belongsTo(AssessmentAspect::class, 'assessment_aspect_id')->withTrashed();
    }

    /**
     * The user who entered this score.
     *
     * @return BelongsTo<User, $this>
     */
    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scored_at' => 'datetime',
        ];
    }
}
