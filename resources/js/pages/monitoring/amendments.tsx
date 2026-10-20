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
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';
import type { FilterOptions, Paginated } from '@/types';

type AmendmentRow = MonitoringRow & {
    memo_number: string | null;
};

export default function AmendmentMonitoring({
    amendments,
    folders,
    partner,
    summary,
    filters,
    options,
}: {
    amendments: Paginated<AmendmentRow> | null;
    folders: Paginated<PartnerFolder> | null;
    partner: OpenPartner | null;
    summary: MonitoringSummary[];
    filters: MonitoringFilters;
    options: FilterOptions;
}) {
    return (
        <>
            <Head title="Monitoring Amandemen" />

            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Monitoring"
                    title="Monitoring Amandemen"
                    description="Seluruh amandemen kontrak: dari permohonan pengusul, persetujuan TL dan Asman, hingga diterbitkan Laksdan."
                />

                <ContractMonitoring<AmendmentRow>
                    url={monitoring.amendments().url}
                    page={amendments}
                    folders={folders}
                    partner={partner}
                    years={options.years ?? []}
                    unitLabel="amandemen"
                    summary={summary}
                    filters={filters}
                    workDirectors={options.workDirectors}
                    emptyTitle="Belum ada amandemen"
                    extraHeadings={[{ label: 'Nota Dinas Usulan' }]}
                    extraCells={(row) => (
                        <TableCell className="tabular text-xs">
                            {row.memo_number ?? '—'}
                        </TableCell>
                    )}
                />
            </div>
        </>
    );
}

AmendmentMonitoring.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring Amandemen', href: monitoring.amendments() },
    ],
};
