import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
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

export default function MonitoringIndex({
    procurements: page,
    filters,
    options,
    statusBoard,
}: {
    procurements: Paginated<ProcurementRow>;
    filters: ProcurementFilters;
    options: FilterOptions;
    statusBoard: StatusBoardRow[];
}) {
    const maxTotal = Math.max(1, ...statusBoard.map((row) => row.total));

    return (
        <>
            <Head title="Monitoring" />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pengawasan"
                    title="Monitoring Progress Pengadaan"
                    description="Distribusi pengadaan pada setiap status progres beserta detail per pengadaan."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {statusBoard.map((row) => (
                        <Link
                            key={row.id}
                            href={monitoring.index({
                                query: { progress_status_id: row.id },
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
                />

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
                    emptyTitle="Tidak ada pengadaan untuk dipantau"
                />
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
