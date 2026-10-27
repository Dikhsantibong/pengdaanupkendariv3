import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    ChevronRight,
    Clock,
    Hourglass,
    Landmark,
    PackageCheck,
    Search,
    Wallet,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    formatCompactCurrency,
    formatCurrency,
    formatDate,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import finance from '@/routes/finance';
import handover from '@/routes/handover';
import procurements from '@/routes/procurements';
import type { Paginated } from '@/types';

export type BoardSection = 'penyerahan' | 'pembayaran';

export type BoardRow = {
    id: number;
    number: string;
    name: string;
    partner_name: string | null;
    division: string;
    executor: string | null;
    value: number;
    /** When it arrived on this board, and who sent it. */
    submitted_at: string | null;
    submitted_by: string | null;
    /** When it left: paid, or the hand-over approved. */
    paid_at: string | null;
    paid_by: string | null;
    state: string;
    state_label: string;
    steps_done: number;
    steps_total: number;
};

export type BoardProps = {
    section: BoardSection;
    view: 'waiting' | 'done';
    rows: Paginated<BoardRow>;
    counts: { waiting: number; done: number };
    summary: {
        waiting: number;
        awaitingDecision: number;
        waitingValue: number;
        paidThisMonth: number;
    };
    filters: { search: string | null };
};

/** The words of each board. */
const BOARD: Record<
    BoardSection,
    {
        title: string;
        description: string;
        waitingTab: string;
        doneTab: string;
        awaiting: string;
        waitingValue: string;
        doneThisMonth: string;
        arrived: string;
        doneAt: string;
        steps: string;
        empty: Record<'waiting' | 'done', [string, string]>;
    }
> = {
    penyerahan: {
        title: 'Penyerahan',
        description:
            'PIC Pelaksana melengkapi berkas penyerahan lalu mengajukannya ke TL Pengadaan. Yang disetujui diteruskan ke Pembayaran (Keuangan).',
        waitingTab: 'Perlu Diproses',
        doneTab: 'Sudah Disetujui',
        awaiting: 'Menunggu TL Pengadaan',
        waitingValue: 'Nilai Pekerjaan',
        doneThisMonth: 'Disetujui Bulan Ini',
        arrived: 'Pelaksanaan disetujui',
        doneAt: 'Disetujui',
        steps: 'berkas',
        empty: {
            waiting: [
                'Tidak ada penyerahan yang berjalan',
                'Pengadaan yang pelaksanaannya disetujui TL Pengadaan akan muncul di sini.',
            ],
            done: [
                'Belum ada penyerahan yang disetujui',
                'Penyerahan yang disetujui TL Pengadaan akan tercatat di sini.',
            ],
        },
    },
    pembayaran: {
        title: 'Pembayaran',
        description:
            'Keuangan melengkapi berkas pembayaran lalu mengajukannya ke TL Keuangan dan Asman. Persetujuan Asman mencatat lunas dan menutup pengadaan.',
        waitingTab: 'Menunggu Pembayaran',
        doneTab: 'Sudah Dibayar',
        awaiting: 'Menunggu Persetujuan',
        waitingValue: 'Nilai Menunggu Dibayar',
        doneThisMonth: 'Dibayar Bulan Ini',
        arrived: 'Penyerahan disetujui',
        doneAt: 'Lunas',
        steps: 'berkas',
        empty: {
            waiting: [
                'Tidak ada tagihan yang menunggu',
                'Pengadaan yang penyerahannya disetujui TL Pengadaan akan muncul di sini.',
            ],
            done: [
                'Belum ada pembayaran yang lunas',
                'Pembayaran yang disetujui Asman akan tercatat di sini.',
            ],
        },
    },
};

/** Where a job stands, coloured by who has to act. */
function StateBadge({ row }: { row: BoardRow }) {
    const tone = ['ditolak', 'dikembalikan'].includes(row.state)
        ? 'bg-destructive/10 text-destructive'
        : ['menunggu_persetujuan', 'menunggu_tl', 'menunggu_asman'].includes(
                row.state,
            )
          ? 'bg-amber-500/15 text-amber-800 dark:text-amber-300'
          : row.state === 'disetujui'
            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
            : 'bg-muted text-muted-foreground';

    return (
        <span
            className={cn(
                'inline-flex rounded-sm px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                tone,
            )}
        >
            {row.state_label}
        </span>
    );
}

/**
 * The Penyerahan or Pembayaran board: a few figures, then one list at a
 * time (to process / done) as a compact, paged table, so a long board
 * never turns into a long scroll.
 */
export function FinanceBoard({
    section,
    view,
    rows,
    counts,
    summary,
    filters,
}: BoardProps) {
    const words = BOARD[section];
    const boardUrl =
        section === 'penyerahan' ? handover.index().url : finance.index().url;
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                boardUrl,
                { ...(search ? { search } : {}), view },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timeout);
    }, [search, boardUrl, view]);

    const openView = (next: 'waiting' | 'done') =>
        router.get(
            boardUrl,
            { ...(search ? { search } : {}), view: next },
            { preserveScroll: true, replace: true },
        );

    const tabUrl = (id: number) =>
        procurements.show(id, { query: { tab: section } }).url;
    const [emptyTitle, emptyDescription] = words.empty[view];

    return (
        <>
            <Head title={words.title} />

            <div className="flex w-full flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow={`Tahap ${words.title}`}
                    title={words.title}
                    description={words.description}
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label={words.waitingTab}
                        value={summary.waiting}
                        icon={Clock}
                        accent
                    />
                    <StatCard
                        label={words.awaiting}
                        value={summary.awaitingDecision}
                        icon={Hourglass}
                    />
                    <StatCard
                        label={words.waitingValue}
                        value={formatCompactCurrency(summary.waitingValue)}
                        hint={formatCurrency(summary.waitingValue)}
                        icon={Wallet}
                    />
                    <StatCard
                        label={words.doneThisMonth}
                        value={summary.paidThisMonth}
                        icon={BadgeCheck}
                    />
                </div>

                <div className="@container overflow-hidden rounded-md border border-border bg-card">
                    <div className="flex flex-col gap-2 border-b border-border p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div
                            role="tablist"
                            className="inline-flex self-start rounded-md border border-border bg-muted/50 p-0.5"
                        >
                            {(
                                [
                                    [
                                        'waiting',
                                        words.waitingTab,
                                        counts.waiting,
                                    ],
                                    ['done', words.doneTab, counts.done],
                                ] as const
                            ).map(([key, label, count]) => (
                                <button
                                    key={key}
                                    type="button"
                                    role="tab"
                                    aria-selected={key === view}
                                    onClick={() => openView(key)}
                                    className={cn(
                                        'inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                        key === view
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-primary/10 hover:text-primary',
                                    )}
                                >
                                    {label}
                                    <span
                                        className={cn(
                                            'tabular rounded-full px-1.5 text-xs',
                                            key === view
                                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                                : 'bg-primary/10 text-primary',
                                        )}
                                    >
                                        {count}
                                    </span>
                                </button>
                            ))}
                        </div>

                        <div className="relative w-full sm:max-w-xs">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nomor, pekerjaan, atau mitra"
                                className="h-9 pl-9"
                                aria-label="Cari pengadaan"
                            />
                        </div>
                    </div>

                    {rows.data.length === 0 ? (
                        <EmptyState
                            icon={
                                section === 'penyerahan'
                                    ? PackageCheck
                                    : Landmark
                            }
                            title={emptyTitle}
                            description={emptyDescription}
                        />
                    ) : (
                        <ul className="divide-y divide-border">
                            {rows.data.map((row) => {
                                const percentage =
                                    row.steps_total > 0
                                        ? Math.round(
                                              (row.steps_done /
                                                  row.steps_total) *
                                                  100,
                                          )
                                        : 0;

                                return (
                                    <li key={row.id}>
                                        <Link
                                            href={tabUrl(row.id)}
                                            className="group grid gap-x-4 gap-y-1.5 px-4 py-3 transition-colors hover:bg-primary/[0.04] @4xl:grid-cols-[minmax(0,1fr)_9rem_10rem_8.5rem_1rem] @4xl:items-center"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium group-hover:text-primary">
                                                    {row.name}
                                                </p>
                                                <p className="tabular truncate text-xs text-muted-foreground">
                                                    {row.number} ·{' '}
                                                    {row.partner_name ?? '-'} ·{' '}
                                                    {row.division}
                                                </p>
                                            </div>
                                            {/* Beneath the name when narrow; columns of the row when wide. */}
                                            <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 @4xl:contents">
                                                <p className="tabular text-sm font-medium @4xl:text-right">
                                                    {formatCurrency(row.value)}
                                                </p>
                                                <div className="min-w-40 space-y-1">
                                                    {view === 'waiting' ? (
                                                        <>
                                                            <StateBadge
                                                                row={row}
                                                            />
                                                            <div className="flex items-center gap-2">
                                                                <Progress
                                                                    value={
                                                                        percentage
                                                                    }
                                                                    className="h-1.5 flex-1"
                                                                />
                                                                <span className="tabular text-[11px] text-muted-foreground">
                                                                    {
                                                                        row.steps_done
                                                                    }
                                                                    /
                                                                    {
                                                                        row.steps_total
                                                                    }{' '}
                                                                    {
                                                                        words.steps
                                                                    }
                                                                </span>
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            {row.paid_by ?? '-'}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="tabular text-xs text-muted-foreground @4xl:text-right">
                                                    {view === 'waiting'
                                                        ? words.arrived
                                                        : words.doneAt}
                                                    <span className="block text-foreground">
                                                        {formatDate(
                                                            view === 'waiting'
                                                                ? row.submitted_at
                                                                : row.paid_at,
                                                        )}
                                                    </span>
                                                </p>
                                            </div>
                                            <ChevronRight className="hidden size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary @4xl:block" />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    <DataPagination page={rows} />
                </div>
            </div>
        </>
    );
}
