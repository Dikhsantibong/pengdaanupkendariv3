import { Head } from '@inertiajs/react';
import { ContractMonitoring } from '@/components/contract-monitoring';
import type {
    MonitoringFilters,
    MonitoringRow,
    MonitoringSummary,
} from '@/components/contract-monitoring';
import { PageHeader } from '@/components/page-header';
import type {
    OpenPartner,
    PartnerFolder,
} from '@/components/partner-folder-grid';
import { TableCell } from '@/components/ui/table';
import { formatCurrency, formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';
import type { FilterOptions, Paginated } from '@/types';

type DeliveryOrderRow = MonitoringRow & {
    order_date: string | null;
    items_count: number;
    total: number;
};

export default function DeliveryOrderMonitoring({
    orders,
    folders,
    partner,
    summary,
    filters,
    options,
}: {
    orders: Paginated<DeliveryOrderRow> | null;
    folders: Paginated<PartnerFolder> | null;
    partner: OpenPartner | null;
    summary: MonitoringSummary[];
    filters: MonitoringFilters;
    options: FilterOptions;
}) {
    return (
        <>
            <Head title="Monitoring Delivery Order" />

            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Monitoring"
                    title="Monitoring Delivery Order"
                    description="Seluruh Delivery Order kontrak KHS: dari permohonan pengusul, persetujuan TL dan Asman, Nota Dinas DO Rendan, hingga diterbitkan Laksdan."
                />

                <ContractMonitoring<DeliveryOrderRow>
                    url={monitoring.deliveryOrders().url}
                    page={orders}
                    folders={folders}
                    partner={partner}
                    years={options.years ?? []}
                    unitLabel="DO"
                    summary={summary}
                    filters={filters}
                    workDirectors={options.workDirectors}
                    emptyTitle="Belum ada Delivery Order"
                    extraHeadings={[
                        { label: 'Tanggal DO' },
                        { label: 'Item', className: 'text-right' },
                        { label: 'Nilai DO', className: 'text-right' },
                    ]}
                    extraCells={(row) => (
                        <>
                            <TableCell className="tabular text-xs whitespace-nowrap">
                                {row.order_date
                                    ? formatDate(row.order_date)
                                    : '—'}
                            </TableCell>
                            <TableCell className="tabular text-right">
                                {row.items_count}
                            </TableCell>
                            <TableCell className="tabular text-right font-medium whitespace-nowrap">
                                {formatCurrency(row.total)}
                            </TableCell>
                        </>
                    )}
                />
            </div>
        </>
    );
}

DeliveryOrderMonitoring.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Monitoring Delivery Order',
            href: monitoring.deliveryOrders(),
        },
    ],
};
