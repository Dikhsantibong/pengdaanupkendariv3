<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use App\Enums\TeamLeaderField;
use App\Enums\UnitManagerLevel;
use Database\Factories\UnitManagerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $position
 * @property UnitManagerLevel $level
 * @property TeamLeaderField|null $field
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'position', 'level', 'field', 'description', 'sort_order', 'is_active'])]
class UnitManager extends Model
{
    /** @use HasFactory<UnitManagerFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The units this official holds: several for a Manager UL or a TL, one for
     * a TL OPHAR, none for the Manager.
     *
     * @return BelongsToMany<TargetUnit, $this>
     */
    public function targetUnits(): BelongsToMany
    {
        return $this->belongsToMany(TargetUnit::class)
            ->withTrashed()
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * The jabatan written for a rank, e.g. "TL PEMELIHARAAN" or
     * "TL OPHAR PLTD LANGARA".
     */
    public static function positionFor(UnitManagerLevel $level, ?TeamLeaderField $field = null, ?string $unit = null): string
    {
        return match ($level) {
            UnitManagerLevel::Tl => trim('TL '.$field?->label()),
            UnitManagerLevel::TlOphar => trim('TL OPHAR '.mb_strtoupper((string) $unit)),
            default => $level->label(),
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => UnitManagerLevel::class,
            'field' => TeamLeaderField::class,
            'is_active' => 'boolean',
        ];
    }
}
