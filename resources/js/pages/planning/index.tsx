import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import { dashboard } from '@/routes';
import planning from '@/routes/planning';
import type {
    FilterOptions,
    Option,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

export default function PlanningIndex({
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
            <Head title="Perencanaan" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Tahap 1"
                    title="Perencanaan Pengadaan"
                    description="Pengadaan yang dokumen perencanaannya sedang disusun, diajukan, atau perlu diperbaiki."
                />

                <ProcurementFilterBar
                    url={planning.index().url}
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
                    columns={[
                        'unit',
                        'hpe',
                        'status',
                        'approval',
                        'planner',
                        'planningProgress',
                    ]}
                    emptyTitle="Tidak ada pengadaan pada tahap perencanaan"
                    emptyDescription="Seluruh pengadaan yang terlihat sudah melewati tahap perencanaan."
                />
            </div>
        </>
    );
}

PlanningIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Perencanaan', href: planning.index() },
    ],
};
