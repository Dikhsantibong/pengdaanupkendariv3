<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The year and month filter every list of data offers: the records of a
 * year, or of one month of it, by the date each list goes by.
 */
class FilterPeriod
{
    /**
     * Keep the records of the year and month asked for.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, Request $request, string $column = 'created_at'): Builder
    {
        ['year' => $year, 'month' => $month] = self::current($request);
        $column = $query->qualifyColumn($column);

        return $query
            ->when($year !== null, fn (Builder $inner) => $inner->whereYear($column, $year))
            ->when($month !== null, fn (Builder $inner) => $inner->whereMonth($column, $month));
    }

    /**
     * The year and month asked for, when they are valid.
     *
     * @return array{year: int|null, month: int|null}
     */
    public static function current(Request $request): array
    {
        $year = $request->integer('year');
        $month = $request->integer('month');

        return [
            'year' => $year >= 2000 && $year <= 2100 ? $year : null,
            'month' => $month >= 1 && $month <= 12 ? $month : null,
        ];
    }

    /**
     * The years a list can be filtered by: from the earliest record of the
     * table to this year, newest first.
     *
     * @return list<int>
     */
    public static function years(string $table, string $column = 'created_at'): array
    {
        $earliest = DB::table($table)->min($column);
        $current = (int) now()->format('Y');
        $first = $earliest === null ? $current : min($current, (int) substr((string) $earliest, 0, 4));

        return range($current, $first);
    }
}
