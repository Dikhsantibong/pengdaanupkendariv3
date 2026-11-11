import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { OpenPartnerBar } from '@/components/partner-folder-grid';
import type { OpenPartner } from '@/components/partner-folder-grid';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementListTable } from '@/components/procurement-list-table';
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

/** The filters as a query, the empty ones left out. */
function queryOf(values: Record<string, string | number | null | undefined>) {
    return Object.fromEntries(
        Object.entries(values).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        ),
    ) as Record<string, string | number>;
}

export default function ProcurementIndex({
    partner,
    procurements: page,
    filters,
    options,
}: {
    /** A partner opened from a link elsewhere (?mitra=). */
    partner: OpenPartner | null;
    /** Every procurement matching the filters, newest first. */
    procurements: Paginated<ProcurementRow>;
    filters: ProcurementFilters;
    options: FilterOptions;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const persist: Record<string, string> = partner
        ? { mitra: partner.key }
        : {};
    const total = page.meta?.total ?? page.total ?? page.data.length;

    return (
        <>
            <Head title="Daftar Pengadaan" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pengadaan"
                    title="Daftar Pengadaan"
                    description={
                        auth.permissions.viewAllProcurements
                            ? 'Seluruh pengadaan barang dan jasa yang terdaftar di UP Kendari. Klik + untuk melihat rincian, atau Detail untuk membuka pengadaan.'
                            : 'Pengadaan yang ditugaskan kepada Anda. Klik + untuk melihat rincian, atau Detail untuk membuka pengadaan.'
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
                    persist={persist}
                />

                {partner !== null && (
                    <OpenPartnerBar
                        partner={partner}
                        backHref={
                            procurements.index({
                                query: queryOf({ ...filters, mitra: null }),
                            }).url
                        }
                    >
                        {total} pengadaan
                    </OpenPartnerBar>
                )}

                <ProcurementListTable
                    page={page}
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
