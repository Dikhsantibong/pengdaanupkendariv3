<?php

namespace App\Support;

use App\Models\Procurement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The filters of the dashboard analytics: a reporting period, by the date a
 * job was registered, and the master data a job can be narrowed by.
 *
 * Every figure and chart of the dashboard reads the same filtered set of
 * jobs, so they always agree with each other.
 */
final class DashboardFilters
{
    /**
     * The reporting periods on offer.
     *
     * @var array<string, string>
     */
    public const PERIODS = [
        'all' => 'Semua Periode',
        '6m' => '6 Bulan Terakhir',
        '12m' => '1 Tahun Terakhir',
        'year' => 'Per Tahun',
        'custom' => 'Rentang Tanggal',
    ];

    /**
     * The longest stretch the monthly charts draw.
     */
    private const MAX_MONTHS = 36;

    public function __construct(
        public readonly string $period = 'all',
        public readonly ?int $year = null,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
        public readonly ?int $workDirectorId = null,
        public readonly ?int $targetUnitId = null,
        public readonly ?int $picId = null,
        public readonly ?int $methodId = null,
        public readonly ?string $stage = null,
    ) {}

    /**
     * Read the filters from the query string, dropping anything invalid.
     */
    public static function fromRequest(Request $request): self
    {
        $period = (string) $request->query('period', 'all');
        $period = array_key_exists($period, self::PERIODS) ? $period : 'all';

        $year = $request->integer('year');
        $year = $year >= 2000 && $year <= 2100 ? $year : (int) now()->format('Y');

        $from = self::date($request->query('from'));
        $to = self::date($request->query('to'));

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($period === 'custom' && ($from === null || $to === null)) {
            $period = 'all';
        }

        $stage = (string) $request->query('stage', '');

        return new self(
            period: $period,
            year: $period === 'year' ? $year : null,
            from: $period === 'custom' ? $from : null,
            to: $period === 'custom' ? $to : null,
            workDirectorId: self::id($request->query('work_director_id')),
            targetUnitId: self::id($request->query('target_unit_id')),
            picId: self::id($request->query('pic_id')),
            methodId: self::id($request->query('procurement_method_id')),
            stage: array_key_exists($stage, DashboardStage::LABELS) ? $stage : null,
        );
    }

    /**
     * The first and last moment of the period, or null for every date.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function range(): ?array
    {
        $now = CarbonImmutable::now();

        return match ($this->period) {
            '6m' => [$now->startOfMonth()->subMonths(5), $now->endOfDay()],
            '12m' => [$now->startOfMonth()->subMonths(11), $now->endOfDay()],
            'year' => [
                CarbonImmutable::create((int) $this->year)->startOfYear(),
                CarbonImmutable::create((int) $this->year)->endOfYear(),
            ],
            'custom' => [$this->from?->startOfDay() ?? $now, $this->to?->endOfDay() ?? $now],
            default => null,
        };
    }

    /**
     * The period of the same length just before this one, to compare with.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function previousRange(): ?array
    {
        $range = $this->range();

        if ($range === null) {
            return null;
        }

        [$start, $end] = $range;

        if ($this->period === 'year') {
            return [$start->subYear(), $end->subYear()];
        }

        if ($this->period === 'custom') {
            $days = (int) $start->diffInDays($end) + 1;

            return [$start->subDays($days), $start->subSecond()];
        }

        $months = $this->period === '6m' ? 6 : 12;

        return [$start->subMonths($months), $start->subSecond()];
    }

    /**
     * The first day of each month the monthly charts draw, oldest first.
     *
     * Every date shows the last twelve months.
     *
     * @return list<CarbonImmutable>
     */
    public function months(): array
    {
        $range = $this->range();
        $now = CarbonImmutable::now();

        [$start, $end] = $range ?? [$now->startOfMonth()->subMonths(11), $now];

        // A period reaching into the future stops at this month.
        $last = ($end->greaterThan($now) && $start->lessThanOrEqualTo($now) ? $now : $end)->startOfMonth();
        $month = $start->startOfMonth();
        $months = [];

        while ($month->lessThanOrEqualTo($last)) {
            $months[] = $month;
            $month = $month->addMonth();
        }

        return array_slice($months, -self::MAX_MONTHS);
    }

    /**
     * Narrow the jobs to these filters.
     *
     * @param  Builder<Procurement>  $query
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null|false  $range  false to use the period's own range
     * @return Builder<Procurement>
     */
    public function apply(Builder $query, array|false|null $range = false): Builder
    {
        $range = $range === false ? $this->range() : $range;

        return $query
            ->when($range !== null, fn (Builder $inner) => $inner->whereBetween($inner->qualifyColumn('created_at'), $range))
            ->when($this->workDirectorId !== null, fn (Builder $inner) => $inner->where($inner->qualifyColumn('work_director_id'), $this->workDirectorId))
            ->when($this->targetUnitId !== null, fn (Builder $inner) => $inner->whereHas(
                'targetUnits',
                fn (Builder $units) => $units->whereKey($this->targetUnitId),
            ))
            ->when($this->picId !== null, fn (Builder $inner) => $inner->where(fn (Builder $pic) => $pic
                ->where($pic->qualifyColumn('planner_id'), $this->picId)
                ->orWhere($pic->qualifyColumn('executor_id'), $this->picId)))
            ->when($this->methodId !== null, fn (Builder $inner) => $inner->where($inner->qualifyColumn('procurement_method_id'), $this->methodId))
            ->when($this->stage !== null, fn (Builder $inner) => DashboardStage::where($inner, (string) $this->stage));
    }

    /**
     * Whether any filter narrows the jobs.
     */
    public function isFiltered(): bool
    {
        return $this->period !== 'all'
            || $this->workDirectorId !== null
            || $this->targetUnitId !== null
            || $this->picId !== null
            || $this->methodId !== null
            || $this->stage !== null;
    }

    /**
     * The filters as the page shows them.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        $range = $this->range();

        return [
            'period' => $this->period,
            'year' => $this->year,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'work_director_id' => $this->workDirectorId,
            'target_unit_id' => $this->targetUnitId,
            'pic_id' => $this->picId,
            'procurement_method_id' => $this->methodId,
            'stage' => $this->stage,
            'range_label' => $range === null
                ? 'Semua periode'
                : $range[0]->translatedFormat('j M Y').' – '.$range[1]->translatedFormat('j M Y'),
        ];
    }

    /**
     * A date from the query string, when it is one.
     */
    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof CarbonImmutable ? $date->startOfDay() : null;
    }

    /**
     * A record id from the query string, when it is one.
     */
    private static function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
