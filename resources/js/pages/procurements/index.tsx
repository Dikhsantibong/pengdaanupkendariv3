import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
    folders,
    partner,
    procurements: page,
    filters,
    options,
}: {
    /** One folder per partner, while none is open. */
    folders: Paginated<PartnerFolder> | null;
    partner: OpenPartner | null;
    /** The open folder's work, or every match while searching. */
    procurements: Paginated<ProcurementRow>;
    filters: ProcurementFilters;
    options: FilterOptions;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const persist: Record<string, string> = partner
        ? { mitra: partner.key }
        : {};
    // A search looks across every folder at once.
    const isSearching = partner === null && Boolean(filters.search);
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
                            ? 'Seluruh pengadaan barang dan jasa yang terdaftar di UP Kendari, dikelompokkan dalam folder per mitra. Klik folder untuk melihat pengadaan mitra itu.'
                            : 'Pengadaan yang ditugaskan kepada Anda, dikelompokkan dalam folder per mitra.'
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

                {isSearching && (
                    <p className="text-xs text-muted-foreground">
                        Hasil pencarian di semua folder mitra: {total}{' '}
                        pengadaan.
                    </p>
                )}

                {partner === null && !isSearching && folders !== null ? (
                    <PartnerFolderGrid
                        page={folders}
                        emptyTitle="Tidak ada pengadaan yang cocok"
                        href={(folder) =>
                            procurements.index({
                                query: queryOf({
                                    ...filters,
                                    mitra: folder.key,
                                }),
                            }).url
                        }
                        details={(folder) => (
                            <>
                                <span className="tabular block text-[11px] text-muted-foreground">
                                    {folder.total} pengadaan
                                </span>
                                <FolderBadge
                                    done={folder.done}
                                    total={folder.total}
                                    label="selesai"
                                />
                            </>
                        )}
                    />
                ) : (
                    <ProcurementTable
                        page={page}
                        columns={[
                            'unit',
                            'budgetSource',
                            'hpe',
                            'status',
                            'approval',
                            'planner',
                            'executor',
                        ]}
                        emptyDescription="Tidak ada pengadaan yang cocok dengan filter saat ini."
                    />
                )}
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
