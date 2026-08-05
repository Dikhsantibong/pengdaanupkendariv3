<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared query helpers for the modular master data tables.
 */
trait MasterDataScopes
{
    /**
     * Limit the query to master data rows that are still selectable.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Order master data rows by their configured display order.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
