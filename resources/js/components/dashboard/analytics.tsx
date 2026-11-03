import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    BadgeCheck,
    Ban,
    CheckCircle2,
    ChevronRight,
    CircleDot,
    FilePlus2,
    FileUp,
    FolderKanban,
    Hourglass,
    ListChecks,
    Loader2,
    PencilLine,
    RefreshCw,
    RotateCcw,
    Search,
    Send,
    TrendingDown,
    TrendingUp,
    Undo2,
    UserPlus,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ChartEmpty, VIZ } from '@/components/charts/chart-kit';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatCurrency,
    formatDate,
    formatDateTime,
    formatNumber,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';
import procurements from '@/routes/procurements';
import type { StatusCategory } from '@/types';

type Option = { id: number; name: string };
type Choice = { value: string; label: string };

export type Breakdown = { label: string; total: number };

export type DashboardFilterState = {
    period: string;
    year: number | null;
    from: string | null;
    to: string | null;
    work_director_id: number | null;
    target_unit_id: number | null;
    pic_id: number | null;
    procurement_method_id: number | null;
    stage: string | null;
    range_label: string;
};

export type FilterOptions = {
    periods: Choice[];
    years: number[];
    stages: Choice[];
    workDirectors: Option[] | null;
    targetUnits: Option[];
    methods: Option[];
    pics: Option[] | null;
};

export type Kpis = {
    total: number;
    running: number;
    pending: number;
    completed: number;
    cancelled: number;
    awaiting: number;
    awaitingBreakdown: Record<string, number>;
    totalHpe: number;
    runningHpe: number;
    previous: { total: number; totalHpe: number; completed: number } | null;
};

type MonthLabel = { key: string; label: string };

export type Trend = {
    months: MonthLabel[];
    total: number[];
    running: number[];
    completed: number[];
    cancelled: number[];
};

export type Budget = {
    months: MonthLabel[];
    hpe: number[];
    running: number[];
    completed: number[];
    target: number[] | null;
};

export type StageTotal = { key: string; label: string; total: number };

export type Progress = {
    percent: number;
    completion: number;
    active: number;
    completed: number;
    inExecution: number;
    purchaseOrder: number;
    contracted: number;
};

export type Activity = {
    id: number;
    type: string;
    label: string;
    outcome: string;
    description: string;
    user: string | null;
    procurement: { id: number; name: string; number: string } | null;
    at: string | null;
};

export type Upcoming = {
    id: number;
    number: string;
    name: string;
    date: string | null;
};

export type TableRowData = {
    id: number;
    number: string;
    name: string;
    units: string;
    method: string | null;
    planner: string | null;
    executor: string | null;
    hpe_value: number;
    stage: { key: string; label: string };
    status: { name: string; category: StatusCategory };
    created_at: string | null;
};

export type TablePage = {
    data: TableRowData[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type TableQuery = { search: string; sort: string; direction: string };

/**
 * Every section may be missing (the role does not show it) or null (it
 * could not be worked out).
 */
export type Analytics = {
    sections: string[];
    kpis?: Kpis | null;
    trend?: Trend | null;
    stages?: StageTotal[] | null;
    budget?: Budget | null;
    methods?: Breakdown[] | null;
    progress?: Progress | null;
    directorates?: Breakdown[] | null;
    units?: Breakdown[] | null;
    budgetSources?: Breakdown[] | null;
    pics?: { label: string; planner: number; executor: number }[] | null;
    statuses?: Breakdown[] | null;
    upcoming?: Upcoming[] | null;
    activities?: Activity[] | null;
};

/** The colour of each stage, shared by the pipeline and the donut. */
export const STAGE_COLORS: Record<string, string> = {
    usulan: VIZ.grey,
    perencanaan: VIZ.sky,
    pelaksanaan: VIZ.blue,
    keuangan: VIZ.navy,
    selesai: VIZ.green,
    batal: VIZ.red,
};

/** The props the filters and the table reload. */
const ANALYTICS_PROPS = ['analytics', 'table', 'filters', 'tableQuery'];

/**
 * The query string of the dashboard, without empty or default values.
 */
export function queryOf(
    filters: DashboardFilterState,
    table: TableQuery,
    overrides: Record<string, string | number | null> = {},
): Record<string, string | number> {
    const all: Record<string, string | number | null> = {
        period: filters.period === 'all' ? null : filters.period,
        year: filters.period === 'year' ? filters.year : null,
        from: filters.period === 'custom' ? filters.from : null,
        to: filters.period === 'custom' ? filters.to : null,
        work_director_id: filters.work_director_id,
        target_unit_id: filters.target_unit_id,
        pic_id: filters.pic_id,
        procurement_method_id: filters.procurement_method_id,
        stage: filters.stage,
        search: table.search === '' ? null : table.search,
        sort: table.sort === 'created_at' ? null : table.sort,
        direction: table.direction === 'desc' ? null : table.direction,
        ...overrides,
    };

    return Object.fromEntries(
        Object.entries(all).filter(
            (entry): entry is [string, string | number] =>
                entry[1] !== null && entry[1] !== '',
        ),
    );
}

/**
 * Reload the analytics for new filters, keeping the page where it is.
 */
export function visitDashboard(
    query: Record<string, string | number>,
    setLoading: (loading: boolean) => void,
    only: string[] = ANALYTICS_PROPS,
) {
    router.get(dashboard().url, query, {
        only,
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => setLoading(true),
        onFinish: () => setLoading(false),
    });
}

/**
 * A titled card holding one chart or list.
 */
export function Panel({
    title,
    description,
    action,
    children,
    className,
    bodyClassName,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
    /** Columns taken in a dashboard grid; read by the grid, not here. */
    span?: 1 | 2 | 3;
}) {
    return (
        <section
            className={cn(
                'flex min-w-0 flex-col rounded-md border border-border bg-card',
                className,
            )}
        >
            <header className="flex items-start justify-between gap-3 border-b border-border px-4 py-3">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-foreground">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </header>
            <div className={cn('flex-1 p-4', bodyClassName)}>{children}</div>
        </section>
    );
}

/**
 * What a section shows when its figures could not be worked out.
 */
export function SectionError() {
    return (
        <ChartEmpty
            title="Data tidak dapat dimuat"
            description="Terjadi kendala saat menghitung data ini. Muat ulang halaman; bila berlanjut, hubungi administrator."
        />
    );
}

/**
 * The filters every figure and chart below follows.
 */
export function DashboardFilterBar({
    filters,
    options,
    tableQuery,
    loading,
    setLoading,
}: {
    filters: DashboardFilterState;
    options: FilterOptions;
    tableQuery: TableQuery;
    loading: boolean;
    setLoading: (loading: boolean) => void;
}) {
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const apply = (overrides: Record<string, string | number | null>) =>
        visitDashboard(
            queryOf(filters, tableQuery, { ...overrides, table_page: null }),
            setLoading,
        );

    const isFiltered =
        filters.period !== 'all' ||
        filters.work_director_id !== null ||
        filters.target_unit_id !== null ||
        filters.pic_id !== null ||
        filters.procurement_method_id !== null ||
        filters.stage !== null;

    const idSelect = (
        label: string,
        name: keyof DashboardFilterState,
        choices: Option[] | null,
    ) =>
        choices === null ? null : (
            <FilterField label={label}>
                <Select
                    value={filters[name] ? String(filters[name]) : 'all'}
                    onValueChange={(next) =>
                        apply({ [name]: next === 'all' ? null : Number(next) })
                    }
                >
                    <SelectTrigger className="w-0 min-w-full">
                        <SelectValue placeholder="Semua" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua</SelectItem>
                        {choices.map((choice) => (
                            <SelectItem
                                key={choice.id}
                                value={String(choice.id)}
                            >
                                {choice.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>
        );

    return (
        <section
            aria-label="Filter dashboard"
            className="rounded-md border border-border bg-card px-4 py-3"
        >
            <div className="flex flex-wrap items-end gap-3">
                <FilterField label="Periode" wide>
                    <Select
                        value={filters.period}
                        onValueChange={(next) => {
                            if (next === 'custom') {
                                // A range waits for both dates.
                                const today = new Date()
                                    .toISOString()
                                    .slice(0, 10);
                                const start = `${today.slice(0, 4)}-01-01`;
                                setFrom(start);
                                setTo(today);
                                apply({
                                    period: 'custom',
                                    from: start,
                                    to: today,
                                });

                                return;
                            }

                            apply({
                                period: next === 'all' ? null : next,
                                year:
                                    next === 'year'
                                        ? (filters.year ??
                                          options.years[0] ??
                                          null)
                                        : null,
                            });
                        }}
                    >
                        <SelectTrigger className="w-0 min-w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {options.periods.map((period) => (
                                <SelectItem
                                    key={period.value}
                                    value={period.value}
                                >
                                    {period.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterField>

                {filters.period === 'year' && (
                    <FilterField label="Tahun">
                        <Select
                            value={String(filters.year ?? options.years[0])}
                            onValueChange={(next) =>
                                apply({ period: 'year', year: Number(next) })
                            }
                        >
                            <SelectTrigger className="w-0 min-w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.years.map((year) => (
                                    <SelectItem key={year} value={String(year)}>
                                        {year}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>
                )}

                {filters.period === 'custom' && (
                    <form
                        className="flex flex-wrap items-end gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply({ period: 'custom', from, to });
                        }}
                    >
                        <FilterField label="Dari">
                            <Input
                                type="date"
                                value={from}
                                max={to || undefined}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                                required
                            />
                        </FilterField>
                        <FilterField label="Sampai">
                            <Input
                                type="date"
                                value={to}
                                min={from || undefined}
                                onChange={(event) => setTo(event.target.value)}
                                required
                            />
                        </FilterField>
                        <Button type="submit" variant="outline">
                            Terapkan
                        </Button>
                    </form>
                )}

                {idSelect(
                    'Direksi Pekerjaan',
                    'work_director_id',
                    options.workDirectors,
                )}
                {idSelect('Unit Tujuan', 'target_unit_id', options.targetUnits)}
                {idSelect('PIC', 'pic_id', options.pics)}
                {idSelect(
                    'Metode Pengadaan',
                    'procurement_method_id',
                    options.methods,
                )}

                <FilterField label="Status Pengadaan">
                    <Select
                        value={filters.stage ?? 'all'}
                        onValueChange={(next) =>
                            apply({ stage: next === 'all' ? null : next })
                        }
                    >
                        <SelectTrigger className="w-0 min-w-full">
                            <SelectValue placeholder="Semua" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua</SelectItem>
                            {options.stages.map((stage) => (
                                <SelectItem
                                    key={stage.value}
                                    value={stage.value}
                                >
                                    {stage.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterField>

                <div className="flex items-center gap-3 pb-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={!isFiltered || loading}
                        onClick={() =>
                            visitDashboard(
                                queryOf(
                                    {
                                        ...filters,
                                        period: 'all',
                                        work_director_id: null,
                                        target_unit_id: null,
                                        pic_id: null,
                                        procurement_method_id: null,
                                        stage: null,
                                    },
                                    tableQuery,
                                ),
                                setLoading,
                            )
                        }
                    >
                        <RotateCcw className="size-3.5" />
                        Reset Filter
                    </Button>
                </div>
            </div>

            <p
                className="mt-2.5 flex items-center gap-1.5 text-xs text-muted-foreground"
                aria-live="polite"
            >
                {loading ? (
                    <>
                        <Loader2 className="size-3.5 animate-spin" />
                        Memperbarui data…
                    </>
                ) : (
                    <>Periode data: {filters.range_label}</>
                )}
            </p>
        </section>
    );
}

function FilterField({
    label,
    wide = false,
    children,
}: {
    label: string;
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'w-full space-y-1.5 sm:w-auto sm:flex-[1_1_auto]',
                wide ? 'sm:min-w-44' : 'sm:min-w-36',
            )}
        >
            <Label className="section-label">{label}</Label>
            {children}
        </div>
    );
}

/**
 * The change against the period before, when there is one to compare with.
 */
function Change({
    current,
    previous,
}: {
    current: number;
    previous: number | undefined;
}) {
    if (previous === undefined) {
        return null;
    }

    if (previous === 0) {
        return current === 0 ? (
            <span className="text-xs text-muted-foreground">
                Sama dengan periode sebelumnya
            </span>
        ) : (
            <span className="text-xs text-muted-foreground">
                Periode sebelumnya: 0
            </span>
        );
    }

    const change = ((current - previous) / previous) * 100;
    const Icon = change >= 0 ? TrendingUp : TrendingDown;

    return (
        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <Icon className="size-3.5" />
            <span className="tabular font-medium text-foreground">
                {change > 0 ? '+' : ''}
                {change.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%
            </span>
            vs periode sebelumnya
        </span>
    );
}

const AWAITING_LABELS: Record<string, string> = {
    usulan: 'Usulan',
    perencanaan: 'Perencanaan',
    pelaksanaan: 'Pelaksanaan',
    penyerahan: 'Penyerahan',
    pembayaran: 'Pembayaran',
};

/**
 * The six headline figures of the filtered jobs.
 */
export function KpiGrid({
    kpis,
    canOpenApprovals,
}: {
    kpis: Kpis;
    canOpenApprovals: boolean;
}) {
    const share = (value: number) =>
        kpis.total > 0
            ? `${((value / kpis.total) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}% dari total`
            : 'Belum ada pengadaan';

    const awaitingParts = Object.entries(kpis.awaitingBreakdown)
        .filter(([, total]) => total > 0)
        .map(([key, total]) => `${AWAITING_LABELS[key] ?? key} ${total}`);

    const cards: {
        key: string;
        label: string;
        value: string;
        icon: LucideIcon;
        color: string;
        hint: ReactNode;
        change?: ReactNode;
        href?: string;
        /** A long figure (Rupiah) that scales with the card's width. */
        long?: boolean;
    }[] = [
        {
            key: 'total',
            label: 'Total Pengadaan',
            value: formatNumber(kpis.total),
            icon: FolderKanban,
            color: VIZ.blue,
            hint: `${formatNumber(kpis.pending)} masih tahap usulan / pending`,
            change: (
                <Change current={kpis.total} previous={kpis.previous?.total} />
            ),
        },
        {
            key: 'running',
            label: 'Pengadaan Berjalan',
            value: formatNumber(kpis.running),
            icon: CircleDot,
            color: VIZ.yellow,
            hint: `Nilai HPE belum selesai ${formatCurrency(kpis.runningHpe)}`,
        },
        {
            key: 'completed',
            label: 'Pengadaan Selesai',
            value: formatNumber(kpis.completed),
            icon: BadgeCheck,
            color: VIZ.green,
            hint: share(kpis.completed),
            change: (
                <Change
                    current={kpis.completed}
                    previous={kpis.previous?.completed}
                />
            ),
        },
        {
            key: 'awaiting',
            label: 'Menunggu Approval',
            value: formatNumber(kpis.awaiting),
            icon: Hourglass,
            color: VIZ.navy,
            hint:
                awaitingParts.length > 0
                    ? awaitingParts.join(' · ')
                    : 'Tidak ada antrean persetujuan',
            href:
                canOpenApprovals && kpis.awaiting > 0
                    ? approvals.index().url
                    : undefined,
        },
        {
            key: 'hpe',
            long: true,
            label: 'Total Nilai HPE',
            value: formatCurrency(kpis.totalHpe),
            icon: Wallet,
            color: VIZ.blue,
            hint: 'Harga Perkiraan Engineer, bukan realisasi bayar',
            change: (
                <Change
                    current={kpis.totalHpe}
                    previous={kpis.previous?.totalHpe}
                />
            ),
        },
        {
            key: 'cancelled',
            label: 'Pengadaan Dibatalkan',
            value: formatNumber(kpis.cancelled),
            icon: Ban,
            color: VIZ.red,
            hint: share(kpis.cancelled),
        },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
            {cards.map((card) => {
                const body = (
                    <>
                        <div className="flex items-start justify-between gap-3">
                            <p className="section-label">{card.label}</p>
                            <span
                                className="flex size-8 shrink-0 items-center justify-center rounded-md"
                                style={{
                                    color: card.color,
                                    backgroundColor: `color-mix(in srgb, ${card.color} 12%, transparent)`,
                                }}
                            >
                                <card.icon className="size-4" strokeWidth={2} />
                            </span>
                        </div>
                        <p
                            className={cn(
                                'tabular mt-1 leading-tight font-semibold tracking-tight [overflow-wrap:anywhere] text-foreground',
                                card.long
                                    ? 'text-[clamp(1rem,7.5cqi,1.625rem)]'
                                    : 'text-[1.625rem]',
                            )}
                        >
                            {card.value}
                        </p>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                            {card.hint}
                        </p>
                        {card.change && (
                            <div className="mt-1">{card.change}</div>
                        )}
                    </>
                );
                const className =
                    '@container relative flex min-w-0 flex-col overflow-hidden rounded-md border border-border bg-card p-4 pt-[1.1rem]';
                const stripe = (
                    <span
                        className="absolute inset-x-0 top-0 h-0.5"
                        style={{ backgroundColor: card.color }}
                        aria-hidden
                    />
                );

                return card.href ? (
                    <Link
                        key={card.key}
                        href={card.href}
                        className={cn(
                            className,
                            'transition-colors hover:border-primary/50',
                        )}
                    >
                        {stripe}
                        {body}
                    </Link>
                ) : (
                    <section key={card.key} className={className}>
                        {stripe}
                        {body}
                    </section>
                );
            })}
        </div>
    );
}

/**
 * The jobs at each step of the journey, left to right; picking a step
 * filters the dashboard to it.
 */
export function StagePipeline({
    stages,
    activeStage,
    onSelect,
}: {
    stages: StageTotal[];
    activeStage: string | null;
    onSelect: (stage: string | null) => void;
}) {
    const total = stages.reduce((sum, stage) => sum + stage.total, 0);
    const flow = stages.filter((stage) => stage.key !== 'batal');
    const cancelled = stages.find((stage) => stage.key === 'batal');
    const percent = (value: number) =>
        total > 0
            ? `${((value / total) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`
            : '0%';

    return (
        <Panel
            title="Alur Tahapan Pengadaan"
            description="Posisi setiap pengadaan pada alurnya. Klik tahap untuk menyaring dashboard."
            action={
                activeStage !== null ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onSelect(null)}
                    >
                        Tampilkan semua
                    </Button>
                ) : undefined
            }
        >
            <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                {flow.map((stage, index) => {
                    const isActive = activeStage === stage.key;

                    return (
                        <li key={stage.key} className="relative min-w-0">
                            <button
                                type="button"
                                onClick={() =>
                                    onSelect(isActive ? null : stage.key)
                                }
                                aria-pressed={isActive}
                                className={cn(
                                    'flex h-full w-full flex-col gap-2 rounded-md border bg-card p-3 text-left transition-colors hover:bg-muted/60',
                                    isActive
                                        ? 'border-primary ring-1 ring-primary'
                                        : 'border-border',
                                    activeStage !== null &&
                                        !isActive &&
                                        'opacity-60',
                                )}
                            >
                                <span className="flex items-center justify-between gap-2">
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="tabular flex size-5 shrink-0 items-center justify-center rounded-full border border-border text-[0.625rem] font-semibold text-muted-foreground">
                                            {index + 1}
                                        </span>
                                        <span className="truncate text-xs font-semibold text-foreground">
                                            {stage.label}
                                        </span>
                                    </span>
                                </span>
                                <span className="flex items-baseline justify-between gap-2">
                                    <span className="tabular text-2xl leading-none font-semibold text-foreground">
                                        {formatNumber(stage.total)}
                                    </span>
                                    <span className="tabular text-xs text-muted-foreground">
                                        {percent(stage.total)}
                                    </span>
                                </span>
                                <span className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <span
                                        className="block h-full rounded-full"
                                        style={{
                                            width:
                                                total > 0
                                                    ? `${(stage.total / total) * 100}%`
                                                    : 0,
                                            backgroundColor:
                                                STAGE_COLORS[stage.key],
                                        }}
                                    />
                                </span>
                            </button>
                            {index < flow.length - 1 && (
                                <ChevronRight
                                    className="absolute top-1/2 -right-2 z-10 hidden size-4 -translate-y-1/2 rounded-full bg-background text-muted-foreground lg:block"
                                    aria-hidden
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
            {cancelled !== undefined && (
                <button
                    type="button"
                    onClick={() =>
                        onSelect(activeStage === 'batal' ? null : 'batal')
                    }
                    className={cn(
                        'mt-3 inline-flex items-center gap-2 rounded-sm text-xs text-muted-foreground hover:text-foreground',
                        activeStage === 'batal' &&
                            'font-semibold text-foreground',
                    )}
                >
                    <span
                        className="size-2 rounded-full"
                        style={{ backgroundColor: STAGE_COLORS.batal }}
                        aria-hidden
                    />
                    Dibatalkan (di luar alur): {formatNumber(cancelled.total)}{' '}
                    pengadaan · {percent(cancelled.total)}
                </button>
            )}
        </Panel>
    );
}

const SORTABLE_COLUMNS: Record<string, string> = {
    name: 'Nama Pengadaan',
    number: 'No. Pengadaan',
    hpe_value: 'Nilai HPE',
    created_at: 'Tanggal',
};

/**
 * The filtered jobs, newest first: searchable, sortable and paged without
 * reloading the charts.
 */
export function RecentProcurementTable({
    page,
    filters,
    options,
    tableQuery,
    setLoading,
}: {
    page: TablePage;
    filters: DashboardFilterState;
    options: FilterOptions;
    tableQuery: TableQuery;
    setLoading: (loading: boolean) => void;
}) {
    const [search, setSearch] = useState(tableQuery.search);
    const [busy, setBusy] = useState(false);
    const firstRender = useRef(true);

    const reloadTable = (overrides: Record<string, string | number | null>) =>
        visitDashboard(queryOf(filters, tableQuery, overrides), setBusy, [
            'table',
            'tableQuery',
        ]);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(
            () => reloadTable({ search: search.trim(), table_page: null }),
            350,
        );

        return () => clearTimeout(timer);
        // Only a new search term reloads; the rest is read at that moment.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const sortBy = (column: string) =>
        reloadTable({
            sort: column,
            direction:
                tableQuery.sort === column && tableQuery.direction === 'desc'
                    ? 'asc'
                    : 'desc',
            table_page: null,
        });

    const header = (column: string, className?: string) => {
        const isSorted = tableQuery.sort === column;
        const Icon = !isSorted
            ? ArrowUpDown
            : tableQuery.direction === 'asc'
              ? ArrowUp
              : ArrowDown;

        return (
            <TableHead
                className={className}
                aria-sort={
                    isSorted
                        ? tableQuery.direction === 'asc'
                            ? 'ascending'
                            : 'descending'
                        : undefined
                }
            >
                <button
                    type="button"
                    onClick={() => sortBy(column)}
                    className={cn(
                        'inline-flex items-center gap-1 hover:text-foreground',
                        isSorted && 'text-foreground',
                    )}
                >
                    {SORTABLE_COLUMNS[column]}
                    <Icon className="size-3" />
                </button>
            </TableHead>
        );
    };

    const offset = (page.from ?? 1) - 1;

    return (
        <Panel
            title="Pengadaan Terbaru"
            description={`${formatNumber(page.total)} pengadaan sesuai filter · ${filters.range_label}`}
            bodyClassName="p-0"
            action={
                <Link
                    href={procurements.index()}
                    className="shrink-0 text-xs font-medium text-primary hover:underline"
                >
                    Lihat semua
                </Link>
            }
        >
            <div className="flex flex-col gap-2 border-b border-border px-4 py-3 sm:flex-row sm:items-center">
                <div className="relative sm:max-w-xs sm:flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama atau nomor pengadaan"
                        aria-label="Cari pengadaan"
                        className="pl-8"
                    />
                </div>
                <Select
                    value={filters.stage ?? 'all'}
                    onValueChange={(next) =>
                        visitDashboard(
                            queryOf(filters, tableQuery, {
                                stage: next === 'all' ? null : next,
                                table_page: null,
                            }),
                            setLoading,
                        )
                    }
                >
                    <SelectTrigger
                        className="sm:w-52"
                        aria-label="Filter status pengadaan"
                    >
                        <SelectValue placeholder="Semua status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua status</SelectItem>
                        {options.stages.map((stage) => (
                            <SelectItem key={stage.value} value={stage.value}>
                                {stage.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {busy && (
                    <Loader2 className="size-4 animate-spin text-muted-foreground" />
                )}
            </div>

            {page.data.length === 0 ? (
                <div className="p-4">
                    <ChartEmpty
                        title="Tidak ada pengadaan"
                        description={
                            tableQuery.search !== ''
                                ? `Tidak ada pengadaan yang cocok dengan "${tableQuery.search}".`
                                : undefined
                        }
                    />
                </div>
            ) : (
                <div className={cn('transition-opacity', busy && 'opacity-60')}>
                    <Table>
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="w-10 text-right">
                                    No
                                </TableHead>
                                {header('name', 'min-w-56')}
                                {header('number')}
                                <TableHead>Unit Tujuan</TableHead>
                                <TableHead>Metode</TableHead>
                                <TableHead>PIC</TableHead>
                                {header('hpe_value', 'text-right')}
                                <TableHead>Status</TableHead>
                                {header('created_at')}
                                <TableHead className="text-right">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {page.data.map((row, index) => (
                                <TableRow key={row.id}>
                                    <TableCell className="tabular text-right text-muted-foreground">
                                        {offset + index + 1}
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            href={procurements.show(row.id)}
                                            className="line-clamp-2 font-medium text-foreground hover:text-primary hover:underline"
                                        >
                                            {row.name}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">
                                            {row.stage.label}
                                        </span>
                                    </TableCell>
                                    <TableCell className="tabular text-xs whitespace-nowrap">
                                        {row.number}
                                    </TableCell>
                                    <TableCell
                                        className="max-w-44 truncate text-xs"
                                        title={row.units}
                                    >
                                        {row.units || '—'}
                                    </TableCell>
                                    <TableCell className="text-xs whitespace-nowrap">
                                        {row.method ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-xs">
                                        <span className="block whitespace-nowrap">
                                            <span className="text-muted-foreground">
                                                Ren:{' '}
                                            </span>
                                            {row.planner ?? '—'}
                                        </span>
                                        <span className="block whitespace-nowrap">
                                            <span className="text-muted-foreground">
                                                Pel:{' '}
                                            </span>
                                            {row.executor ?? '—'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="tabular text-right font-medium whitespace-nowrap">
                                        {formatCurrency(row.hpe_value)}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={row.status.name}
                                            category={row.status.category}
                                        />
                                    </TableCell>
                                    <TableCell className="tabular text-xs whitespace-nowrap">
                                        {formatDate(row.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={procurements.show(row.id)}
                                            >
                                                Detail
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <TablePager
                page={page}
                onVisit={(url) =>
                    router.get(
                        url,
                        {},
                        {
                            only: ['table', 'tableQuery'],
                            preserveState: true,
                            preserveScroll: true,
                            replace: true,
                            onStart: () => setBusy(true),
                            onFinish: () => setBusy(false),
                        },
                    )
                }
            />
        </Panel>
    );
}

function TablePager({
    page,
    onVisit,
}: {
    page: TablePage;
    onVisit: (url: string) => void;
}) {
    const summary = (
        <span className="tabular text-xs text-muted-foreground">
            Menampilkan {page.from ?? 0}–{page.to ?? 0} dari{' '}
            {formatNumber(page.total)} data
        </span>
    );

    if (page.last_page <= 1) {
        return (
            <div className="border-t border-border px-4 py-2.5">{summary}</div>
        );
    }

    return (
        <nav
            aria-label="Halaman tabel pengadaan"
            className="flex flex-col gap-2 border-t border-border px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between"
        >
            {summary}
            <ul className="flex flex-wrap items-center gap-1">
                {page.links.map((link, index) => {
                    const label = link.label
                        .replace('&laquo; Previous', '‹')
                        .replace('Next &raquo;', '›')
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›');

                    return (
                        <li key={`${link.label}-${index}`}>
                            <button
                                type="button"
                                disabled={link.url === null || link.active}
                                onClick={() => link.url && onVisit(link.url)}
                                aria-current={link.active ? 'page' : undefined}
                                className={cn(
                                    'tabular min-w-8 rounded-sm border px-2 py-1 text-xs transition-colors',
                                    link.active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-card hover:bg-muted disabled:opacity-40',
                                )}
                            >
                                {label}
                            </button>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

const ACTIVITY_ICONS: Record<string, { icon: LucideIcon; color: string }> = {
    disetujui: { icon: CheckCircle2, color: VIZ.green },
    dikembalikan: { icon: Undo2, color: VIZ.red },
    ditunjuk: { icon: UserPlus, color: VIZ.blue },
    dibuat: { icon: FilePlus2, color: VIZ.blue },
    selesai: { icon: BadgeCheck, color: VIZ.green },
    diajukan: { icon: Send, color: VIZ.navy },
};

/** Routine edits get their own icon rather than "submitted". */
function activityIcon(activity: Activity): { icon: LucideIcon; color: string } {
    if (activity.type.startsWith('dokumen_')) {
        return { icon: FileUp, color: VIZ.sky };
    }

    if (activity.type === 'status_diubah') {
        return { icon: RefreshCw, color: VIZ.grey };
    }

    if (activity.type === 'diperbarui') {
        return { icon: PencilLine, color: VIZ.grey };
    }

    if (activity.type === 'checklist_diperbarui') {
        return { icon: ListChecks, color: VIZ.grey };
    }

    return ACTIVITY_ICONS[activity.outcome] ?? ACTIVITY_ICONS.diajukan;
}

/**
 * The latest things that happened to the filtered jobs.
 */
export function ActivityTimeline({ activities }: { activities: Activity[] }) {
    if (activities.length === 0) {
        return (
            <ChartEmpty
                title="Belum ada aktivitas"
                description="Aktivitas pengadaan yang sesuai filter akan tampil di sini."
            />
        );
    }

    return (
        <ol className="space-y-0">
            {activities.map((activity, index) => {
                const { icon: Icon, color } = activityIcon(activity);

                return (
                    <li key={activity.id} className="flex gap-3">
                        <div className="flex flex-col items-center">
                            <span
                                className="flex size-7 shrink-0 items-center justify-center rounded-full border border-border bg-card"
                                style={{ color }}
                            >
                                <Icon className="size-3.5" />
                            </span>
                            {index < activities.length - 1 && (
                                <span className="w-px flex-1 bg-border" />
                            )}
                        </div>
                        <div className="min-w-0 flex-1 pb-4">
                            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                <p className="text-sm font-semibold text-foreground">
                                    {activity.label}
                                </p>
                                <time
                                    dateTime={activity.at ?? undefined}
                                    className="tabular text-xs text-muted-foreground"
                                >
                                    {formatDateTime(activity.at)}
                                </time>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {activity.user ?? 'Sistem'}
                            </p>
                            <p className="mt-0.5 line-clamp-2 text-xs text-foreground/80">
                                {activity.description}
                            </p>
                            {activity.procurement && (
                                <Link
                                    href={procurements.show(
                                        activity.procurement.id,
                                    )}
                                    className="mt-0.5 block truncate text-xs font-medium text-primary hover:underline"
                                >
                                    {activity.procurement.number} ·{' '}
                                    {activity.procurement.name}
                                </Link>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * The running jobs closest to their target date.
 */
export function UpcomingList({ rows }: { rows: Upcoming[] }) {
    if (rows.length === 0) {
        return (
            <ChartEmpty
                title="Belum ada target penyelesaian"
                description="Isi target penyelesaian pada data pengadaan agar tampil di sini."
            />
        );
    }

    return (
        <ul className="-my-2 divide-y divide-border">
            {rows.map((row) => (
                <li
                    key={row.id}
                    className="flex items-center justify-between gap-3 py-2.5"
                >
                    <div className="min-w-0">
                        <Link
                            href={procurements.show(row.id)}
                            className="block truncate text-sm font-medium hover:text-primary hover:underline"
                        >
                            {row.name}
                        </Link>
                        <span className="tabular text-xs text-muted-foreground">
                            {row.number}
                        </span>
                    </div>
                    <span className="tabular shrink-0 text-xs font-medium text-foreground">
                        {formatDate(row.date)}
                    </span>
                </li>
            ))}
        </ul>
    );
}
