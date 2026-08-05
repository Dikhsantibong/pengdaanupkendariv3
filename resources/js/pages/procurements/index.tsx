import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import type {
    Auth,
    FilterOptions,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

export default function ProcurementIndex({
    procurements: page,
    filters,
    options,
}: {
    procurements: Paginated<ProcurementRow>;
    filters: ProcurementFilters;
    options: FilterOptions;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Daftar Pengadaan" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pengadaan"
                    title="Daftar Pengadaan"
                    description={
                        auth.permissions.viewAllProcurements
                            ? 'Seluruh pengadaan barang dan jasa yang terdaftar di UP Kendari.'
                            : 'Pengadaan yang ditugaskan kepada Anda.'
                    }
                    actions={
                        auth.permissions.createProcurement && (
                            <Button asChild>
                                <Link href={procurements.create()}>
                                    <Plus className="size-4" />
                                    Buat Perencanaan Pengadaan
                                </Link>
                            </Button>
                        )
                    }
                />

                <ProcurementFilterBar
                    url={procurements.index().url}
                    filters={filters}
                    options={options}
                />

                <ProcurementTable
                    page={page}
                    columns={[
                        'unit',
                        'method',
                        'budgetSource',
                        'hpe',
                        'status',
                        'approval',
                        'planner',
                        'executor',
                    ]}
                    emptyDescription="Tidak ada pengadaan yang cocok dengan filter saat ini."
                />
            </div>
        </>
    );
}

ProcurementIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Daftar Pengadaan', href: procurements.index() },
    ],
};
