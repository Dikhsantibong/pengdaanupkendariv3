import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    FilePlus2,
    Inbox,
    Paperclip,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import proposals from '@/routes/proposals';
import type { Paginated } from '@/types';

export type ProposalRow = {
    id: number;
    number: string;
    name: string;
    state:
        | 'menunggu_persetujuan_tl'
        | 'menunggu_persetujuan'
        | 'dikembalikan'
        | 'disetujui';
    state_label: string;
    /** Waiting but incomplete: still with the proposer. */
    is_held?: boolean;
    division: string | null;
    proposer: string | null;
    proposer_position: string | null;
    target_units: string;
    hpe_value: number;
    prk_number: string | null;
    attachments_count: number;
    submitted_at: string | null;
    reviewed_at: string | null;
    review_note: string | null;
};

export const STATE_STYLE: Record<ProposalRow['state'], string> = {
    menunggu_persetujuan_tl:
        'bg-sky-500/10 text-sky-700 ring-sky-500/30 dark:text-sky-400',
    menunggu_persetujuan:
        'bg-amber-500/10 text-amber-700 ring-amber-500/30 dark:text-amber-400',
    dikembalikan:
        'bg-red-500/10 text-red-700 ring-red-500/30 dark:text-red-400',
    disetujui:
        'bg-emerald-500/10 text-emerald-700 ring-emerald-500/30 dark:text-emerald-400',
};

export function StateBadge({
    row,
}: {
    row: Pick<ProposalRow, 'state' | 'is_held'>;
}) {
    if (row.is_held) {
        return (
            <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground ring-1 ring-border ring-inset">
                Belum Lengkap
            </span>
        );
    }

    const label = {
        menunggu_persetujuan_tl: 'Menunggu Persetujuan TL',
        menunggu_persetujuan: 'Menunggu Persetujuan Direksi Pekerjaan',
        dikembalikan: 'Dikembalikan',
        disetujui: 'Disetujui',
    }[row.state];

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                STATE_STYLE[row.state],
            )}
        >
            {label}
        </span>
    );
}

function AwaitingCard({ row }: { row: ProposalRow }) {
    return (
        <Link
            href={proposals.show(row.id).url}
            className="group flex flex-col rounded-lg border border-border bg-card p-4 shadow-xs transition hover:border-primary/50 hover:shadow-md"
        >
            <div className="flex items-start justify-between gap-3">
                <p className="line-clamp-2 text-base font-semibold text-foreground group-hover:text-primary">
                    {row.name}
                </p>
                <StateBadge row={row} />
            </div>

            <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">Pengusul</dt>
                    <dd className="font-medium">{row.proposer ?? '-'}</dd>
                    {row.proposer_position && (
                        <dd className="text-xs text-muted-foreground">
                            {row.proposer_position}
                        </dd>
                    )}
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Nomor PRK</dt>
                    <dd className="tabular line-clamp-2 font-medium">
                        {row.prk_number ?? '-'}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Unit Tujuan
                    </dt>
                    <dd className="line-clamp-1">{row.target_units || '-'}</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Diajukan</dt>
                    <dd className="tabular">
                        {formatDateTime(row.submitted_at)}
                    </dd>
                </div>
            </dl>

            <div className="mt-4 flex items-center justify-between border-t border-border pt-3 text-sm">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    <Paperclip className="size-4" />
                    {row.attachments_count} lampiran
                </span>
                <span className="flex items-center gap-1 font-semibold text-primary">
                    Tinjau &amp; Putuskan
                    <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                </span>
            </div>
        </Link>
    );
}

function ListRow({ row }: { row: ProposalRow }) {
    return (
        <li>
            <Link
                href={proposals.show(row.id).url}
                className="group flex flex-col gap-2 px-4 py-3 transition-colors hover:bg-accent/40 sm:flex-row sm:items-center sm:gap-4"
            >
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-foreground group-hover:text-primary">
                        {row.name}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {row.division ?? '-'} ·{' '}
                        {row.state === 'dikembalikan' && row.review_note
                            ? `Catatan: ${row.review_note}`
                            : `Diajukan ${formatDateTime(row.submitted_at)}`}
                    </p>
                </div>
                <span className="tabular hidden max-w-[16rem] truncate text-sm font-medium md:block">
                    {row.prk_number ?? '-'}
                </span>
                <StateBadge row={row} />
                <ChevronRight className="hidden size-4 text-muted-foreground sm:block" />
            </Link>
        </li>
    );
}

function Section({
    title,
    icon: Icon,
    count,
    children,
}: {
    title: string;
    icon: typeof Inbox;
    count?: number;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
                <Icon className="size-5 text-primary" />
                {title}
                {count !== undefined && (
                    <span className="tabular rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                        {count}
                    </span>
                )}
            </h2>
            {children}
        </section>
    );
}

/** A row of the overview: the decisions taken at both levels. */
type OverviewRow = ProposalRow & {
    tl_reviewer: string | null;
    tl_reviewed_at: string | null;
    reviewer: string | null;
};

type OverviewTab = 'waiting' | 'history' | 'decisions';

export type ProposalOverview = {
    tab: OverviewTab;
    search: string | null;
    counts: Record<OverviewTab, number>;
    rows: Paginated<OverviewRow>;
};

const OVERVIEW_TABS: {
    key: OverviewTab;
    label: string;
    icon: typeof Inbox;
    empty: string;
}[] = [
    {
        key: 'waiting',
        label: 'Menunggu Persetujuan',
        icon: Clock,
        empty: 'Tidak ada usulan yang menunggu persetujuan TL atau Direksi Pekerjaan.',
    },
    {
        key: 'history',
        label: 'Riwayat Usulan',
        icon: Inbox,
        empty: 'Belum ada usulan pekerjaan yang diajukan.',
    },
    {
        key: 'decisions',
        label: 'Riwayat Keputusan',
        icon: RotateCcw,
        empty: 'Belum ada usulan yang diputuskan.',
    },
];

/** Who decided, and when, at one level. */
function Decision({
    level,
    name,
    at,
}: {
    level: string;
    name: string | null;
    at: string | null;
}) {
    if (!name) {
        return null;
    }

    return (
        <div className="whitespace-nowrap">
            <span className="text-xs text-muted-foreground">{level}: </span>
            <span className="font-medium">{name}</span>
            {at && (
                <span className="tabular block text-xs text-muted-foreground">
                    {formatDateTime(at)}
                </span>
            )}
        </div>
    );
}

/**
 * Every proposer's proposals for whoever oversees them all: those waiting,
 * every proposal raised, and every decision taken.
 */
function ProposalOverviewView({ overview }: { overview: ProposalOverview }) {
    const { tab, counts, rows } = overview;
    const [search, setSearch] = useState(overview.search ?? '');
    const isFirstRender = useRef(true);
    const indexUrl = proposals.index().url;

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                indexUrl,
                { ...(search ? { search } : {}), tab },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timeout);
    }, [search, indexUrl, tab]);

    const openTab = (next: OverviewTab) =>
        router.get(
            indexUrl,
            { ...(search ? { search } : {}), tab: next },
            { preserveScroll: true, replace: true },
        );

    const current =
        OVERVIEW_TABS.find((item) => item.key === tab) ?? OVERVIEW_TABS[0];
    const showsDecisions = tab === 'decisions';

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div
                    role="tablist"
                    className="inline-flex flex-wrap self-start rounded-md border border-border bg-muted/50 p-0.5"
                >
                    {OVERVIEW_TABS.map(({ key, label, icon: Icon }) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={key === tab}
                            onClick={() => openTab(key)}
                            className={cn(
                                'inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                key === tab
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-primary/10 hover:text-primary',
                            )}
                        >
                            <Icon className="size-4" />
                            {label}
                            <span
                                className={cn(
                                    'tabular rounded-full px-1.5 text-xs',
                                    key === tab
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-primary/10 text-primary',
                                )}
                            >
                                {counts[key]}
                            </span>
                        </button>
                    ))}
                </div>

                <div className="relative w-full sm:max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari pekerjaan, PRK, atau pengusul"
                        className="pl-8"
                        aria-label="Cari usulan"
                    />
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-border bg-card">
                {rows.data.length === 0 ? (
                    <div className="flex items-center justify-center gap-3 px-5 py-10 text-sm text-muted-foreground">
                        <CheckCircle2 className="size-5 text-emerald-600" />
                        {search
                            ? 'Tidak ada usulan yang cocok.'
                            : current.empty}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pekerjaan</TableHead>
                                    <TableHead>Pengusul</TableHead>
                                    <TableHead>Diajukan</TableHead>
                                    <TableHead>Status</TableHead>
                                    {showsDecisions && (
                                        <TableHead>Keputusan</TableHead>
                                    )}
                                    <TableHead className="w-8" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.data.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                proposals.show(row.id).url,
                                            )
                                        }
                                    >
                                        <TableCell className="max-w-[28rem]">
                                            <Link
                                                href={
                                                    proposals.show(row.id).url
                                                }
                                                className="line-clamp-2 font-medium text-foreground hover:text-primary"
                                                onClick={(event) =>
                                                    event.stopPropagation()
                                                }
                                            >
                                                {row.name}
                                            </Link>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {row.division ?? '-'}
                                                {row.prk_number
                                                    ? ` · PRK ${row.prk_number}`
                                                    : ''}
                                            </span>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            <span className="font-medium">
                                                {row.proposer ?? '-'}
                                            </span>
                                            {row.proposer_position && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.proposer_position}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="tabular whitespace-nowrap">
                                            {formatDateTime(row.submitted_at)}
                                        </TableCell>
                                        <TableCell>
                                            <StateBadge row={row} />
                                            {row.state === 'dikembalikan' &&
                                                row.review_note && (
                                                    <span className="mt-1 line-clamp-2 block max-w-[16rem] text-xs text-muted-foreground">
                                                        Catatan:{' '}
                                                        {row.review_note}
                                                    </span>
                                                )}
                                        </TableCell>
                                        {showsDecisions && (
                                            <TableCell className="space-y-1">
                                                <Decision
                                                    level="TL"
                                                    name={row.tl_reviewer}
                                                    at={row.tl_reviewed_at}
                                                />
                                                <Decision
                                                    level="Direksi Pekerjaan"
                                                    name={row.reviewer}
                                                    at={row.reviewed_at}
                                                />
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <ChevronRight className="size-4 text-muted-foreground" />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <DataPagination page={rows} />
            </div>
        </div>
    );
}

export default function ProposalIndex({
    canApprove,
    canPropose,
    division,
    awaiting,
    decided,
    mine,
    overview,
}: {
    canApprove: boolean;
    canPropose: boolean;
    division: string | null;
    awaiting: ProposalRow[];
    decided: ProposalRow[];
    mine: ProposalRow[];
    /** Present only for whoever oversees every proposal. */
    overview?: ProposalOverview;
}) {
    return (
        <>
            <Head title="Usulan Pekerjaan" />

            <div className="flex w-full flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={
                        division ? `Bidang ${division}` : 'Usulan Pekerjaan'
                    }
                    title="Usulan Pekerjaan"
                    description={
                        overview
                            ? 'Pantau usulan pekerjaan dari seluruh pengusul: yang menunggu persetujuan, riwayat usulan, dan riwayat keputusan TL serta Direksi Pekerjaan.'
                            : canApprove
                              ? 'Tinjau usulan pekerjaan yang menunggu keputusan Anda. Usulan staf disetujui TL lebih dulu, lalu Asman bidang, kemudian masuk perencanaan untuk penunjukan PIC.'
                              : 'Pantau usulan pekerjaan Anda. Usulan disetujui TL Anda, lalu Asman bidang, kemudian masuk perencanaan untuk penunjukan PIC Perencana.'
                    }
                    actions={
                        canPropose ? (
                            <Button asChild size="lg">
                                <Link href={procurements.create().url}>
                                    <FilePlus2 className="size-4" />
                                    Usulkan Pekerjaan
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                {overview && <ProposalOverviewView overview={overview} />}

                {!overview && canApprove && (
                    <Section
                        title="Menunggu Persetujuan Anda"
                        icon={Clock}
                        count={awaiting.length}
                    >
                        {awaiting.length === 0 ? (
                            <div className="flex items-center gap-3 rounded-lg border border-dashed border-border bg-card px-5 py-8 text-sm text-muted-foreground">
                                <CheckCircle2 className="size-6 text-emerald-600" />
                                Tidak ada usulan yang menunggu. Usulan baru dari
                                bidang Anda akan muncul di sini.
                            </div>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                                {awaiting.map((row) => (
                                    <AwaitingCard key={row.id} row={row} />
                                ))}
                            </div>
                        )}
                    </Section>
                )}

                {!overview && (canPropose || mine.length > 0) && (
                    <Section
                        title="Usulan Saya"
                        icon={Inbox}
                        count={mine.length}
                    >
                        {mine.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-border bg-card px-5 py-8 text-center text-sm text-muted-foreground">
                                Anda belum mengusulkan pekerjaan. Klik{' '}
                                <b>Usulkan Pekerjaan</b> untuk memulai.
                            </div>
                        ) : (
                            <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                                {mine.map((row) => (
                                    <ListRow key={row.id} row={row} />
                                ))}
                            </ul>
                        )}
                    </Section>
                )}

                {!overview && canApprove && decided.length > 0 && (
                    <Section title="Riwayat Keputusan Anda" icon={RotateCcw}>
                        <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                            {decided.map((row) => (
                                <ListRow key={row.id} row={row} />
                            ))}
                        </ul>
                    </Section>
                )}
            </div>
        </>
    );
}

ProposalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Usulan Pekerjaan', href: proposals.index() },
    ],
};
