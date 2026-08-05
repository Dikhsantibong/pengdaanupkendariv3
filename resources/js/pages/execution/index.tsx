import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import { dashboard } from '@/routes';
import execution from '@/routes/execution';
import type {
    FilterOptions,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

export default function ExecutionIndex({
    procurements: page,
    filters,
    options,
}: {
    procurements: Paginated<ProcurementRow>;
    filters: ProcurementFilters;
    options: FilterOptions;
}) {
    return (
        <>
            <Head title="Pelaksanaan" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Tahap 2"
                    title="Pelaksanaan Pengadaan"
                    description="Pengadaan yang dokumen perencanaannya sudah disetujui dan siap dilaksanakan."
                />

                <ProcurementFilterBar
                    url={execution.index().url}
                    filters={filters}
                    options={options}
                />

                <ProcurementTable
                    page={page}
                    columns={[
                        'unit',
                        'hpe',
                        'status',
                        'executor',
                        'executionProgress',
                        'target',
                    ]}
                    emptyTitle="Belum ada pengadaan pada tahap pelaksanaan"
                    emptyDescription="Pengadaan akan muncul di sini setelah dokumen perencanaannya disetujui TL Perencanaan."
                />
            </div>
        </>
    );
}

ExecutionIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pelaksanaan', href: execution.index() },
    ],
};
