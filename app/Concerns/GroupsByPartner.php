<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Boards that open on one folder per partner (spelling and spacing aside),
 * the partner busiest most recently first; a folder lists the work done with
 * that partner. Shared by the monitoring boards and Daftar Pengadaan.
 */
trait GroupsByPartner
{
    /**
     * The folder of the work no partner is named on yet.
     */
    public const NO_PARTNER = 'tanpa-mitra';

    /**
     * The partner folder asked for, if one is open.
     */
    protected function partner(Request $request): ?string
    {
        return $request->string('mitra')->trim()->value() ?: null;
    }

    /**
     * Keep the procurements of one partner's folder.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  A query on procurements.
     * @return Builder<TModel>
     */
    protected function ofPartner(Builder $query, string $partner): Builder
    {
        if ($partner === self::NO_PARTNER) {
            return $query->where(fn (Builder $inner) => $inner
                ->whereNull('procurements.partner_name')
                ->orWhereRaw("TRIM(procurements.partner_name) = ''"));
        }

        return $query->whereRaw('LOWER(TRIM(procurements.partner_name)) = ?', [mb_strtolower($partner)]);
    }

    /**
     * The open folder as the page names it: the partner, or the folder of
     * work without one.
     *
     * @return array{key: string, name: string}
     */
    protected function partnerPayload(string $partner): array
    {
        return [
            'key' => $partner,
            'name' => $partner === self::NO_PARTNER ? 'Belum Ada Mitra' : $partner,
        ];
    }

    /**
     * One folder per partner, spelling and spacing aside, the partner busiest
     * most recently first and the work without a partner last: how many rows
     * it holds, over how many works, and how many are done.
     *
     * @param  Builder<covariant Model>  $rows  Selecting partner_name, procurement_id, updated_at and done.
     * @return array<string, mixed> The page of folders as the board pages it: data, links and meta.
     */
    protected function folders(Builder $rows): array
    {
        $key = "LOWER(TRIM(COALESCE(partner_name, '')))";

        return DB::query()
            ->fromSub($rows, 'rows')
            ->selectRaw("{$key} as partner_key")
            ->selectRaw('MIN(TRIM(partner_name)) as partner_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT procurement_id) as works')
            ->selectRaw('SUM(done) as done')
            ->selectRaw('MAX(updated_at) as latest_at')
            // Aggregated, so MySQL's ONLY_FULL_GROUP_BY can order by it.
            ->selectRaw("MAX(CASE WHEN {$key} = '' THEN 1 ELSE 0 END) as unnamed")
            ->groupByRaw($key)
            ->orderBy('unnamed')
            ->orderByDesc('latest_at')
            ->orderBy('partner_name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (stdClass $row): array => [
                'key' => $row->partner_key === '' ? self::NO_PARTNER : (string) $row->partner_name,
                'name' => $row->partner_key === '' ? 'Belum Ada Mitra' : (string) $row->partner_name,
                'total' => (int) $row->total,
                'works' => (int) $row->works,
                'done' => (int) $row->done,
                'latest_at' => $row->latest_at === null ? null : (string) $row->latest_at,
            ])
            ->toArray();
    }
}
