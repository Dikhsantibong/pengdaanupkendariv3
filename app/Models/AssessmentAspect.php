<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentAspectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One numbered aspect on the vendor performance form.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $preamble
 * @property array<int, string> $indicators
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read int|null $scores_count
 */
#[Fillable(['code', 'name', 'preamble', 'indicators', 'sort_order', 'is_active'])]
class AssessmentAspect extends Model
{
    /** @use HasFactory<AssessmentAspectFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The assessor forms that score this aspect.
     *
     * @return BelongsToMany<AssessmentForm, $this>
     */
    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentForm::class, 'assessment_form_aspect')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Every score recorded against this aspect.
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
            'indicators' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
