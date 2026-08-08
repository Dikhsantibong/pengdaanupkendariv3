<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One assessor's sheet of the vendor performance form.
 *
 * Each sheet carries its own subset of aspects and is signed by its own
 * assessor, which is why the master recap can average an aspect across
 * everyone who scored it.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $assessor_title
 * @property string|null $assessor_name
 * @property array<int, string>|null $assessor_options
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read int|null $scores_count
 */
#[Fillable([
    'code',
    'name',
    'assessor_title',
    'assessor_name',
    'assessor_options',
    'description',
    'sort_order',
    'is_active',
])]
class AssessmentForm extends Model
{
    /** @use HasFactory<AssessmentFormFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The aspects this sheet scores, in the order they are printed.
     *
     * @return BelongsToMany<AssessmentAspect, $this>
     */
    public function aspects(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentAspect::class, 'assessment_form_aspect')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Every score recorded on this sheet.
     *
     * @return HasMany<VendorAssessmentScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(VendorAssessmentScore::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessor_options' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
