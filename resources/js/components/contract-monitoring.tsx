import { Link, router } from '@inertiajs/react';
import { FileSearch, FileText, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
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
import { cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

const ALL = 'all';

export type MonitoringSummary = { value: string; label: string; total: number };

export type MonitoringFilters = {
    search: string | null;
    state: string | null;
    work_director_id: number | null;
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
 * a card per state (click to filter), a search and division filter run on
 * the server, and a paginated table linking to each procurement's tab.
 */
export function ContractMonitoring<T extends MonitoringRow>({
    url,
    page,
    summary,
    filters,
    workDirectors,
    emptyTitle,
    extraHeadings = [],
    extraCells,
}: {
    url: string;
    page: Paginated<T>;
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
        Boolean(filters.work_director_id);

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
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
                            className={cn(
                                'rounded-md border border-border bg-card p-3 text-left transition-colors hover:border-primary/50',
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
                <div className="min-w-64 flex-1 space-y-1.5">
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
                <div className="w-full space-y-1.5 sm:w-56">
                    <Label className="section-label">Status</Label>
                    <Select
                        value={filters.state ?? ALL}
                        onValueChange={(value) =>
                            visit({ state: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-full">
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
                <div className="w-full space-y-1.5 sm:w-56">
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
                        <SelectTrigger className="w-full">
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
                {hasFilters && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSearch('');
                            router.get(
                                url,
                                {},
                                { preserveScroll: true, replace: true },
                            );
                        }}
                    >
                        <X className="size-4" />
                        Reset
                    </Button>
                )}
            </div>

            {page.data.length === 0 ? (
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
                            {page.data.map((row) => (
                                <TableRow key={row.id}>
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
                                            ? formatDateTime(row.submitted_at)
                                            : '—'}
                                        {row.issued_at && (
                                            <div className="text-emerald-700 dark:text-emerald-400">
                                                Terbit{' '}
                                                {formatDateTime(row.issued_at)}
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
                            ))}
                        </TableBody>
                    </Table>
                    <DataPagination page={page} />
                </div>
            )}
        </div>
    );
}
