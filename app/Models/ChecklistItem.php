<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use App\Enums\ProcurementStage;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ProcurementStage $stage
 * @property string $name
 * @property string|null $description
 * @property bool $is_optional
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $procurement_checklists_count
 */
#[Fillable(['stage', 'name', 'description', 'is_optional', 'sort_order', 'is_active'])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The per-procurement checklist rows derived from this item.
     *
     * @return HasMany<ProcurementChecklist, $this>
     */
    public function procurementChecklists(): HasMany
    {
        return $this->hasMany(ProcurementChecklist::class);
    }

    /**
     * Limit the query to a single procurement stage.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForStage(Builder $query, ProcurementStage $stage): Builder
    {
        return $query->where('stage', $stage->value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ProcurementStage::class,
            'is_optional' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
