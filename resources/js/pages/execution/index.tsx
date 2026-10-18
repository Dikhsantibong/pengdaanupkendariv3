import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import { dashboard } from '@/routes';
import execution from '@/routes/execution';
import type {
    FilterOptions,
    Option,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

export default function ExecutionIndex({
    procurements: page,
    filters,
    options,
    contractFormats,
    contractTypes,
}: {
    procurements: Paginated<ProcurementRow>;
    filters: ProcurementFilters;
    options: FilterOptions;
    contractFormats: Option[];
    contractTypes: Option[];
}) {
    return (
        <>
            <Head title="Pelaksanaan" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Tahap 2"
                    title="Pelaksanaan Pengadaan"
                    description="Pengadaan yang dokumen perencanaannya sudah disetujui dan siap dilaksanakan. Tombol ZIP mengunduh seluruh dokumen satu pengadaan sekaligus."
                />

                <ProcurementFilterBar
                    url={execution.index().url}
                    filters={filters}
                    options={options}
                    extraSelects={[
                        {
                            name: 'contract_number_format_id',
                            label: 'Format Kontrak',
                            options: contractFormats,
                            value: filters.contract_number_format_id,
                        },
                        {
                            name: 'contract_type_id',
                            label: 'Jenis Kontrak',
                            options: contractTypes,
                            value: filters.contract_type_id,
                        },
                    ]}
                />

                <ProcurementTable
                    page={page}
                    numbered
                    columns={[
                        'unit',
                        'hpe',
                        'status',
                        'executor',
                        'executionProgress',
                        'target',
                        'download',
                    ]}
                    emptyTitle="Belum ada pengadaan pada tahap pelaksanaan"
                    emptyDescription="Pengadaan akan muncul di sini setelah dokumen perencanaannya disetujui TL ICC."
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
