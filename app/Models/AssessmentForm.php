<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
 * @property array<string, string>|null $assessor_hidden_until The last BASTP date (Y-m-d) each name stays hidden on, keyed by the name without its phone number.
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
    'assessor_hidden_until',
    'description',
    'sort_order',
    'is_active',
])]
class AssessmentForm extends Model
{
    /** @use HasFactory<AssessmentFormFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The sheet whose names are offered as an assessment's Direksi Pekerjaan.
     */
    public const WORK_DIRECTOR = 'direksi-pekerjaan';

    /**
     * The Direksi Pekerjaan sheet, if there is one.
     */
    public static function workDirectorSheet(): ?self
    {
        return self::query()->where('code', self::WORK_DIRECTOR)->first();
    }

    /**
     * A name as chosen, its phone number left off: "TAUHID ALI (0819…)"
     * becomes "TAUHID ALI".
     */
    public static function nameOf(string $option): string
    {
        return trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $option));
    }

    /**
     * The last BASTP date one name stays hidden on, if it has one.
     */
    public function hiddenUntil(string $option): ?string
    {
        return ($this->assessor_hidden_until ?? [])[mb_strtoupper(self::nameOf($option))] ?? null;
    }

    /**
     * The names that may be chosen on work handed over on the given date: a
     * name is left out up to and including its last hidden date. Without a
     * date every name is offered.
     *
     * @return array<int, string>
     */
    public function optionsFor(?CarbonInterface $bastpDate): array
    {
        return array_values(array_filter(
            $this->assessor_options ?? [],
            function (string $option) use ($bastpDate): bool {
                $until = $this->hiddenUntil($option);

                return $bastpDate === null || $until === null || $bastpDate->toDateString() > $until;
            },
        ));
    }

    /**
     * Every name of the sheet without its phone number, with the last BASTP
     * date it stays hidden on, for a form to filter as the date is typed.
     *
     * @return array<int, array{name: string, hidden_until: string|null}>
     */
    public function namesWithHiding(): array
    {
        return array_map(fn (string $option): array => [
            'name' => self::nameOf($option),
            'hidden_until' => $this->hiddenUntil($option),
        ], $this->assessor_options ?? []);
    }

    /**
     * The option (name with phone) a bare name stands for, if any.
     */
    public function optionNamed(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        foreach ($this->assessor_options ?? [] as $option) {
            if (mb_strtoupper(self::nameOf($option)) === mb_strtoupper(trim($name))) {
                return $option;
            }
        }

        return null;
    }

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
            'assessor_hidden_until' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
