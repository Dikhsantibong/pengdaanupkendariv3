import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import {
    FolderBadge,
    OpenPartnerBar,
    PartnerFolderGrid,
} from '@/components/partner-folder-grid';
import type {
    OpenPartner,
    PartnerFolder,
} from '@/components/partner-folder-grid';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';
import type {
    FilterOptions,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
    StatusCategory,
} from '@/types';

type StatusBoardRow = {
    id: number;
    name: string;
    category: StatusCategory;
    total: number;
};

const categoryBar: Record<StatusCategory, string> = {
    pending: 'bg-status-pending',
    batal: 'bg-status-batal',
    berjalan: 'bg-status-berjalan',
    selesai: 'bg-status-selesai',
};

/** The filters as a query, the empty ones left out. */
function queryOf(values: Record<string, string | number | null | undefined>) {
    return Object.fromEntries(
        Object.entries(values).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        ),
    ) as Record<string, string | number>;
}

export default function MonitoringIndex({
    folders,
    procurements: page,
    partner,
    filters,
    options,
    statusBoard,
}: {
    /** One folder per partner, while none is open. */
    folders: Paginated<PartnerFolder> | null;
    /** The open partner's work. */
    procurements: Paginated<ProcurementRow> | null;
    partner: OpenPartner | null;
    filters: ProcurementFilters;
    options: FilterOptions;
    statusBoard: StatusBoardRow[];
}) {
    const maxTotal = Math.max(1, ...statusBoard.map((row) => row.total));
    const { search, ...narrowing } = filters;
    const persist: Record<string, string> = partner
        ? { mitra: partner.key }
        : {};

    return (
        <>
            <Head title="Monitoring" />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Monitoring"
                    title="Monitoring Progress Pengadaan"
                    description="Pengadaan dikelompokkan dalam folder per mitra. Klik folder untuk melihat pekerjaan yang pernah dikerjakan mitra itu beserta progresnya."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {statusBoard.map((row) => (
                        <Link
                            key={row.id}
                            href={monitoring.index({
                                query: queryOf({
                                    ...persist,
                                    progress_status_id: row.id,
                                }),
                            })}
                            className={cn(
                                'space-y-2 rounded-md border border-border bg-card p-4 transition-colors hover:bg-accent/40',
                                filters.progress_status_id === row.id &&
                                    'border-primary ring-1 ring-primary/30',
                            )}
                        >
                            <p className="section-label truncate">{row.name}</p>
                            <p className="tabular text-2xl leading-tight font-semibold">
                                {row.total}
                            </p>
                            <div className="h-1 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className={cn(
                                        'h-full',
                                        categoryBar[row.category],
                                    )}
                                    style={{
                                        width: `${(row.total / maxTotal) * 100}%`,
                                    }}
                                />
                            </div>
                        </Link>
                    ))}
                </div>

                <ProcurementFilterBar
                    url={monitoring.index().url}
                    filters={filters}
                    options={options}
                    persist={persist}
                />

                {partner !== null && page !== null && (
                    <>
                        <OpenPartnerBar
                            partner={partner}
                            backHref={
                                monitoring.index({
                                    query: queryOf({ search, ...narrowing }),
                                }).url
                            }
                        >
                            {page.meta?.total ?? page.total ?? page.data.length}{' '}
                            pekerjaan
                        </OpenPartnerBar>

                        <ProcurementTable
                            page={page}
                            columns={[
                                'unit',
                                'status',
                                'approval',
                                'planningProgress',
                                'executionProgress',
                                'target',
                            ]}
                            emptyTitle="Tidak ada pekerjaan mitra ini yang cocok"
                        />
                    </>
                )}

                {partner === null && folders !== null && (
                    <PartnerFolderGrid
                        page={folders}
                        emptyTitle="Tidak ada pengadaan untuk dipantau"
                        href={(folder) =>
                            monitoring.index({
                                query: queryOf({
                                    ...filters,
                                    mitra: folder.key,
                                }),
                            }).url
                        }
                        details={(folder) => (
                            <>
                                <span className="tabular block text-[11px] text-muted-foreground">
                                    {folder.total} pekerjaan
                                </span>
                                <FolderBadge
                                    done={folder.done}
                                    total={folder.total}
                                    label="selesai"
                                />
                            </>
                        )}
                    />
                )}
            </div>
        </>
    );
}

MonitoringIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring', href: monitoring.index() },
    ],
};
