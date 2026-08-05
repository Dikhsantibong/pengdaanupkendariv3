<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use Database\Factories\ProcurementMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $procurements_count
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'is_active'])]
class ProcurementMethod extends Model
{
    /** @use HasFactory<ProcurementMethodFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The procurements carried out with this method.
     *
     * @return HasMany<Procurement, $this>
     */
    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    /**
     * The checklist steps a procurement using this method skips.
     *
     * @return BelongsToMany<ChecklistItem, $this>
     */
    public function excludedChecklistItems(): BelongsToMany
    {
        return $this->belongsToMany(
            ChecklistItem::class,
            'checklist_item_method_exclusions',
        )->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
