<?php

namespace App\Models;

use App\Enums\CoaSegment;
use Database\Factories\CoaSegmentOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One master data option for a COA segment (Segment 1-7 or Expense Element).
 *
 * @property int $id
 * @property CoaSegment $segment
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'segment',
    'code',
    'name',
    'description',
    'sort_order',
    'is_active',
])]
class CoaSegmentOption extends Model
{
    /** @use HasFactory<CoaSegmentOptionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment' => CoaSegment::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Limit the query to active options.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Limit the query to a single COA segment.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSegment(Builder $query, CoaSegment $segment): Builder
    {
        return $query->where('segment', $segment->value);
    }

    /**
     * Order options by display order then code.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }
}
