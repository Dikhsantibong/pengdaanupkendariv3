import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format';
import { dashboard } from '@/routes';
import reports from '@/routes/reports';
import type {
    FilterOptions,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

export default function ReportIndex({
    procurements: page,
    filters,
    options,
    totals,
}: {
    procurements: Paginated<ProcurementRow>;
    filters: ProcurementFilters;
    options: FilterOptions;
    totals: { count: number; hpe: number };
}) {
    const exportQuery = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== null),
    ) as Record<string, string | number>;

    return (
        <>
            <Head title="Laporan" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pelaporan"
                    title="Laporan Pengadaan"
                    description="Rekapitulasi pengadaan sesuai filter yang diterapkan, siap diekspor ke format CSV."
                    actions={
                        <Button asChild variant="outline">
                            <a
                                href={
                                    reports.export({ query: exportQuery }).url
                                }
                            >
                                <Download className="size-4" />
                                Ekspor CSV
                            </a>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        label="Jumlah Pengadaan"
                        value={totals.count}
                        accent
                    />
                    <StatCard
                        label="Total Nilai HPE"
                        value={formatCurrency(totals.hpe)}
                    />
                </div>

                <ProcurementFilterBar
                    url={reports.index().url}
                    filters={filters}
                    options={options}
                />

                <ProcurementTable
                    page={page}
                    columns={[
                        'unit',
                        'director',
                        'method',
                        'budgetSource',
                        'hpe',
                        'status',
                        'approval',
                        'planner',
                    ]}
                    emptyTitle="Tidak ada data untuk dilaporkan"
                />
            </div>
        </>
    );
}

ReportIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan', href: reports.index() },
    ],
};
