import { Link, router } from '@inertiajs/react';
import { FileSearch, FileText, Search, X } from 'lucide-react';
import { Fragment, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import {
    FolderBadge,
    OpenPartnerBar,
    PartnerFolderGrid,
} from '@/components/partner-folder-grid';
import type {
    OpenPartner,
    PartnerFolder,
} from '@/components/partner-folder-grid';
import { PeriodFilter } from '@/components/period-filter';
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
import { formatDateTime } from '@/lib/format';
import { cardBasis, evenColumns } from '@/lib/layout';
import { cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

const ALL = 'all';

export type MonitoringSummary = { value: string; label: string; total: number };

export type MonitoringFilters = {
    search: string | null;
    state: string | null;
    work_director_id: number | null;
    /** The partner folder open, if any. */
    mitra: string | null;
    year: number | null;
    month: number | null;
};

/** What every amendment or DO row carries. */
export type MonitoringRow = {
    id: number;
    title: string;
    number: string | null;
    state: string;
    state_label: string;
    procurement: {
        id: number;
        number: string;
        name: string;
        partner: string | null;
        division: string;
    };
    requested_by: string | null;
    submitted_at: string | null;
    issued_at: string | null;
    document_url: string | null;
    url: string;
};

/** Issued green, returned red, drafts muted, the rest waiting amber. */
export function stateTone(state: string): string {
    if (state === 'disetujui' || state === 'terbit') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    }

    if (state === 'dikembalikan') {
        return 'bg-destructive/10 text-destructive';
    }

    if (state === 'draft') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-amber-500/10 text-amber-800 dark:text-amber-300';
}

/**
 * A monitoring list of amendments or Delivery Orders across every contract:
 * a card per state (click to filter), a search, division and period filter
 * run on the server, one folder per partner, and in a partner's folder a
 * paginated table grouped per work, linking to each procurement's tab.
 */
export function ContractMonitoring<T extends MonitoringRow>({
    url,
    page,
    folders,
    partner,
    years,
    unitLabel,
    summary,
    filters,
    workDirectors,
    emptyTitle,
    extraHeadings = [],
    extraCells,
}: {
    url: string;
    /** The open partner's rows. */
    page: Paginated<T> | null;
    /** One folder per partner, while none is open. */
    folders: Paginated<PartnerFolder> | null;
    partner: OpenPartner | null;
    years: number[];
    /** What a row is called: amandemen, DO. */
    unitLabel: string;
    summary: MonitoringSummary[];
    filters: MonitoringFilters;
    workDirectors: Option[];
    emptyTitle: string;
    extraHeadings?: Array<{ label: string; className?: string }>;
    extraCells?: (row: T) => ReactNode;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    const visit = (overrides: Record<string, string | number | null>) => {
        const query: Record<string, string> = {};

        Object.entries({ ...filters, ...overrides }).forEach(([key, value]) => {
            if (value !== null && value !== '') {
                query[key] = String(value);
            }
        });

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(
            () => visit({ search: search || null }),
            350,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasFilters =
        Boolean(filters.search) ||
        Boolean(filters.state) ||
        Boolean(filters.work_director_id) ||
        Boolean(filters.year) ||
        Boolean(filters.month);

    // The way back out of a folder keeps every other filter.
    const outside = Object.fromEntries(
        Object.entries({ ...filters, mitra: null }).filter(
            ([, value]) => value !== null && value !== '',
        ),
    ) as Record<string, string>;

    return (
        <div className="flex flex-1 flex-col gap-4">
            <div className="flex flex-wrap gap-3">
                {summary.map((item) => {
                    const active = filters.state === item.value;

                    return (
                        <button
                            key={item.value}
                            type="button"
                            aria-pressed={active}
                            onClick={() =>
                                visit({ state: active ? null : item.value })
                            }
                            // As many per row as divide the states evenly; the
                            // cards grow, so a last row still fills.
                            style={{
                                flexBasis: cardBasis(
                                    evenColumns(summary.length),
                                ),
                            }}
                            className={cn(
                                'min-w-[9rem] flex-1 rounded-md border border-border bg-card p-3 text-left transition-colors hover:border-primary/50',
                                active &&
                                    'border-primary ring-1 ring-primary/30',
                            )}
                        >
                            <span className="block text-xs text-muted-foreground">
                                {item.label}
                            </span>
                            <span className="tabular block text-xl font-semibold">
                                {item.total}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                <div className="w-full space-y-1.5 sm:w-auto sm:min-w-[12rem] sm:flex-[2_3_14rem]">
                    <Label
                        htmlFor="monitoring-search"
                        className="section-label"
                    >
                        Cari
                    </Label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="monitoring-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Nomor, nomor/nama pengadaan, atau nama mitra"
                            className="pl-9"
                        />
                    </div>
                </div>
                <div className="w-full space-y-1.5 sm:w-auto sm:min-w-24 sm:flex-[1_1_auto]">
                    <Label className="section-label">Status</Label>
                    <Select
                        value={filters.state ?? ALL}
                        onValueChange={(value) =>
                            visit({ state: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-0 min-w-full">
                            <SelectValue placeholder="Semua" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua</SelectItem>
                            {summary.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="w-full space-y-1.5 sm:w-auto sm:min-w-24 sm:flex-[1_1_auto]">
                    <Label className="section-label">Direksi Pekerjaan</Label>
                    <Select
                        value={
                            filters.work_director_id === null
                                ? ALL
                                : String(filters.work_director_id)
                        }
                        onValueChange={(value) =>
                            visit({
                                work_director_id:
                                    value === ALL ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger className="w-0 min-w-full">
                            <SelectValue placeholder="Semua" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua</SelectItem>
                            {workDirectors.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={String(option.value)}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <PeriodFilter
                    years={years}
                    year={filters.year}
                    month={filters.month}
                    onChange={(next) => visit(next)}
                />
                {hasFilters && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSearch('');
                            router.get(
                                url,
                                filters.mitra ? { mitra: filters.mitra } : {},
                                { preserveScroll: true, replace: true },
                            );
                        }}
                    >
                        <X className="size-4" />
                        Reset
                    </Button>
                )}
            </div>

            {partner === null && folders !== null && (
                <PartnerFolderGrid
                    page={folders}
                    emptyTitle={emptyTitle}
                    href={(folder) => {
                        const query = new URLSearchParams({
                            ...outside,
                            mitra: folder.key,
                        });

                        return `${url}?${query.toString()}`;
                    }}
                    details={(folder) => (
                        <>
                            <span className="tabular block text-[11px] text-muted-foreground">
                                {folder.total} {unitLabel} · {folder.works}{' '}
                                pekerjaan
                            </span>
                            <FolderBadge
                                done={folder.done}
                                total={folder.total}
                                label="terbit"
                            />
                        </>
                    )}
                />
            )}

            {partner !== null && (
                <OpenPartnerBar
                    partner={partner}
                    backHref={`${url}?${new URLSearchParams(outside).toString()}`}
                >
                    {page?.total ?? page?.data.length ?? 0} {unitLabel}
                </OpenPartnerBar>
            )}

            {page === null ? null : page.data.length === 0 ? (
                <div className="rounded-md border border-border bg-card">
                    <EmptyState icon={FileSearch} title={emptyTitle} />
                </div>
            ) : (
                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <Table>
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Nomor</TableHead>
                                <TableHead>Pengadaan</TableHead>
                                <TableHead>Direksi Pekerjaan</TableHead>
                                {extraHeadings.map((heading) => (
                                    <TableHead
                                        key={heading.label}
                                        className={heading.className}
                                    >
                                        {heading.label}
                                    </TableHead>
                                ))}
                                <TableHead>Status</TableHead>
                                <TableHead>Diajukan / Terbit</TableHead>
                                <TableHead className="text-right">
                                    Dokumen
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {page.data.map((row, index) => (
                                <Fragment key={row.id}>
                                    {/* Each work's own under its heading. */}
                                    {page.data[index - 1]?.procurement.id !==
                                        row.procurement.id && (
                                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                                            <TableCell
                                                colSpan={
                                                    6 + extraHeadings.length
                                                }
                                                className="py-1.5 text-xs font-semibold"
                                            >
                                                {row.procurement.number} ·{' '}
                                                {row.procurement.name}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    <TableRow>
                                        <TableCell>
                                            <Link
                                                href={row.url}
                                                className="block font-medium whitespace-nowrap hover:text-primary hover:underline"
                                            >
                                                {row.title}
                                            </Link>
                                            <span className="tabular text-xs text-muted-foreground">
                                                {row.number ?? 'Belum bernomor'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span className="block max-w-[18rem] truncate">
                                                {row.procurement.name}
                                            </span>
                                            <span className="tabular text-xs text-muted-foreground">
                                                {row.procurement.number}
                                                {row.procurement.partner
                                                    ? ` · ${row.procurement.partner}`
                                                    : ''}
                                            </span>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {row.procurement.division}
                                        </TableCell>
                                        {extraCells?.(row)}
                                        <TableCell>
                                            <span
                                                className={cn(
                                                    'rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                                    stateTone(row.state),
                                                )}
                                            >
                                                {row.state_label}
                                            </span>
                                        </TableCell>
                                        <TableCell className="tabular text-xs whitespace-nowrap text-muted-foreground">
                                            {row.submitted_at
                                                ? formatDateTime(
                                                      row.submitted_at,
                                                  )
                                                : '—'}
                                            {row.issued_at && (
                                                <div className="text-emerald-700 dark:text-emerald-400">
                                                    Terbit{' '}
                                                    {formatDateTime(
                                                        row.issued_at,
                                                    )}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {row.document_url ? (
                                                <a
                                                    href={row.document_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                                >
                                                    <FileText className="size-3.5" />
                                                    Pratinjau
                                                </a>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                </Fragment>
                            ))}
                        </TableBody>
                    </Table>
                    <DataPagination page={page} />
                </div>
            )}
        </div>
    );
}
