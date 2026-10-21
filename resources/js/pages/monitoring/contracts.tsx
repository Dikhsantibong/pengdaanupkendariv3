import { Head, Link, router } from '@inertiajs/react';
import { FileSearch, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import {
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
import { formatCurrency, formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';
import type { FilterOptions, Option, Paginated } from '@/types';

const ALL = 'all';

type ContractRow = {
    id: number;
    number: string;
    name: string;
    format: string | null;
    division: string;
    value: number | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    url: string;
};

type ContractFilters = {
    search: string | null;
    contract_number_format_id: number | null;
    work_director_id: number | null;
    mitra: string | null;
    year: number | null;
    month: number | null;
};

/** Whole days from today to a date: negative once it has passed. */
function daysLeft(date: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round(
        (new Date(`${date}T00:00:00`).getTime() - today.getTime()) / 86400000,
    );
}

/** The filters as a query, the empty ones left out. */
function queryOf(values: Record<string, string | number | null>) {
    return Object.fromEntries(
        Object.entries(values)
            .filter(([, value]) => value !== null && value !== '')
            .map(([key, value]) => [key, String(value)]),
    );
}

/**
 * The partners under contract now, a folder each; a folder lists the
 * contracts running with that partner, with how long each has left.
 */
export default function ContractMonitoring({
    folders,
    contracts,
    partner,
    filters,
    options,
}: {
    folders: Paginated<PartnerFolder> | null;
    contracts: Paginated<ContractRow> | null;
    partner: OpenPartner | null;
    filters: ContractFilters;
    options: FilterOptions & { formats: Option[] };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);
    const url = monitoring.contracts().url;

    const visit = (overrides: Record<string, string | number | null>) =>
        router.get(url, queryOf({ ...filters, ...overrides }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

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
        Boolean(filters.contract_number_format_id) ||
        Boolean(filters.work_director_id) ||
        Boolean(filters.year) ||
        Boolean(filters.month);
    const outside = queryOf({ ...filters, mitra: null });

    return (
        <>
            <Head title="Monitoring Kontrak" />

            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Monitoring"
                    title="Monitoring Kontrak"
                    description="Mitra yang sedang terkontrak: sudah bernomor kontrak dan pekerjaannya belum selesai atau batal. Klik folder untuk melihat kontrak yang sedang berjalan dengan mitra itu."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <div className="w-full space-y-1.5 sm:w-auto sm:min-w-[12rem] sm:flex-[2_3_14rem]">
                        <Label
                            htmlFor="contract-search"
                            className="section-label"
                        >
                            Cari
                        </Label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="contract-search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Nomor kontrak, nama pekerjaan, atau mitra"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <OptionFilter
                        label="Jenis Kontrak"
                        value={filters.contract_number_format_id}
                        options={options.formats}
                        onChange={(value) =>
                            visit({ contract_number_format_id: value })
                        }
                    />
                    <OptionFilter
                        label="Direksi Pekerjaan"
                        value={filters.work_director_id}
                        options={options.workDirectors}
                        onChange={(value) => visit({ work_director_id: value })}
                    />
                    <PeriodFilter
                        years={options.years ?? []}
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
                                    filters.mitra
                                        ? { mitra: filters.mitra }
                                        : {},
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
                        emptyTitle="Belum ada mitra yang sedang terkontrak"
                        href={(folder) =>
                            monitoring.contracts({
                                query: { ...outside, mitra: folder.key },
                            }).url
                        }
                        details={(folder) => (
                            <span className="tabular block text-[11px] text-muted-foreground">
                                {folder.total} kontrak berjalan
                            </span>
                        )}
                    />
                )}

                {partner !== null && contracts !== null && (
                    <>
                        <OpenPartnerBar
                            partner={partner}
                            backHref={
                                monitoring.contracts({ query: outside }).url
                            }
                        >
                            {contracts.total ?? contracts.data.length} kontrak
                            berjalan
                        </OpenPartnerBar>

                        {contracts.data.length === 0 ? (
                            <div className="rounded-md border border-border bg-card">
                                <EmptyState
                                    icon={FileSearch}
                                    title="Tidak ada kontrak berjalan yang cocok"
                                />
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-md border border-border bg-card">
                                <Table>
                                    <TableHeader className="bg-muted/60">
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead>No Kontrak</TableHead>
                                            <TableHead>Pekerjaan</TableHead>
                                            <TableHead>Jenis</TableHead>
                                            <TableHead className="text-right">
                                                Nilai
                                            </TableHead>
                                            <TableHead>
                                                Masa Pelaksanaan
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {contracts.data.map((row) => (
                                            <ContractTableRow
                                                key={row.id}
                                                row={row}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                                <DataPagination page={contracts} />
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function ContractTableRow({ row }: { row: ContractRow }) {
    const left = row.end_date === null ? null : daysLeft(row.end_date);

    return (
        <TableRow>
            <TableCell className="tabular font-medium whitespace-nowrap">
                <Link
                    href={row.url}
                    className="hover:text-primary hover:underline"
                >
                    {row.number}
                </Link>
            </TableCell>
            <TableCell>
                <span className="block max-w-[22rem] truncate">{row.name}</span>
                <span className="text-xs text-muted-foreground">
                    {row.division}
                </span>
            </TableCell>
            <TableCell className="text-xs">{row.format ?? '—'}</TableCell>
            <TableCell className="tabular text-right whitespace-nowrap">
                {row.value === null ? '—' : formatCurrency(row.value)}
            </TableCell>
            <TableCell className="tabular text-xs whitespace-nowrap">
                {row.start_date && row.end_date ? (
                    <>
                        {formatDate(row.start_date)} –{' '}
                        {formatDate(row.end_date)}
                        <div
                            className={
                                left !== null && left < 0
                                    ? 'text-destructive'
                                    : 'text-muted-foreground'
                            }
                        >
                            {left === null
                                ? ''
                                : left < 0
                                  ? `Lewat ${Math.abs(left)} hari`
                                  : `Sisa ${left} hari`}
                        </div>
                    </>
                ) : (
                    <span className="text-muted-foreground">Belum diisi</span>
                )}
            </TableCell>
            <TableCell className="text-xs">{row.status}</TableCell>
        </TableRow>
    );
}

function OptionFilter({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: number | null;
    options: Option[];
    onChange: (value: number | null) => void;
}) {
    return (
        <div className="w-full space-y-1.5 sm:w-auto sm:min-w-28 sm:flex-[1_1_auto]">
            <Label className="section-label">{label}</Label>
            <Select
                value={value === null ? ALL : String(value)}
                onValueChange={(next) =>
                    onChange(next === ALL ? null : Number(next))
                }
            >
                <SelectTrigger className="w-0 min-w-full">
                    <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Semua</SelectItem>
                    {options.map((option) => (
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
    );
}

ContractMonitoring.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring Kontrak', href: monitoring.contracts() },
    ],
};
