<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use App\Enums\StatusCategory;
use Database\Factories\ProgressStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property StatusCategory $category
 * @property int $sort_order
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $procurements_count
 */
#[Fillable(['name', 'slug', 'category', 'sort_order', 'is_default', 'is_active'])]
class ProgressStatus extends Model
{
    /** @use HasFactory<ProgressStatusFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The procurements currently sitting on this status.
     *
     * @return HasMany<Procurement, $this>
     */
    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    /**
     * Get the status applied to freshly created procurements.
     */
    public static function defaultStatus(): ?self
    {
        return self::query()->where('is_default', true)->first()
            ?? self::query()->ordered()->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => StatusCategory::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
