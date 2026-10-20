import { Head, Link, router } from '@inertiajs/react';
import {
    ClipboardCheck,
    FileSpreadsheet,
    Folder,
    FolderOpen,
    Plus,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AssessmentRequestsPanel } from '@/components/assessment-requests-panel';
import type { AssessmentRequestRow } from '@/components/assessment-requests-panel';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PeriodFilter } from '@/components/period-filter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import vendorAssessments from '@/routes/vendor-assessments';
import type { Paginated } from '@/types';

/** One vendor's folder: how many assessments, and how many fully scored. */
type VendorFolder = {
    name: string;
    assessment_count: number;
    complete_count: number;
    latest_at: string | null;
};

export default function VendorAssessmentIndex({
    vendors,
    requests,
    requestFormUrl,
    filters,
    years,
}: {
    vendors: Paginated<VendorFolder>;
    requests: AssessmentRequestRow[];
    requestFormUrl: string;
    filters: {
        search: string | null;
        year: number | null;
        month: number | null;
    };
    /** The years assessments were opened in. */
    years: number[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => {
            router.get(
                vendorAssessments.index().url,
                {
                    search: search || undefined,
                    year: filters.year ?? undefined,
                    month: filters.month ?? undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
        // Only the typed search triggers it; the period travels along.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const currentPage = vendors.meta?.current_page ?? vendors.current_page ?? 1;
    const perPage = vendors.meta?.per_page ?? vendors.per_page ?? 30;
    // A full page stretches its rows to the bottom of the screen.
    const isFullPage = vendors.data.length >= perPage;

    return (
        <>
            <Head title="Penilaian Kinerja Penyedia" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="SMT-FM-DAN-02.02"
                    title="Penilaian Kinerja Penyedia Barang dan Jasa"
                    description="Formulir penilaian penyedia yang dinilai oleh lima fungsi, dikelompokkan dalam folder per mitra. Klik folder untuk melihat penilaian mitra itu beserta PDF-nya."
                    actions={
                        <Button asChild>
                            <Link href={vendorAssessments.create()}>
                                <Plus className="size-4" />
                                Buat Formulir Penilaian
                            </Link>
                        </Button>
                    }
                />

                <AssessmentRequestsPanel
                    requests={requests}
                    formUrl={requestFormUrl}
                />

                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div className="flex flex-1 flex-wrap items-end gap-3">
                        <div className="relative w-full max-w-sm">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari pekerjaan, penyedia atau nomor PO"
                                className="pl-9"
                            />
                        </div>
                        <PeriodFilter
                            years={years}
                            year={filters.year}
                            month={filters.month}
                            onChange={(next) =>
                                router.get(
                                    vendorAssessments.index().url,
                                    Object.fromEntries(
                                        Object.entries({
                                            search: filters.search,
                                            year: filters.year,
                                            month: filters.month,
                                            ...next,
                                        }).filter(([, value]) => value),
                                    ),
                                    { preserveState: true, replace: true },
                                )
                            }
                        />
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="w-full border-green-600 text-green-600 hover:bg-green-600 hover:text-white sm:w-auto dark:border-green-500 dark:text-green-500 dark:hover:bg-green-600 dark:hover:text-white"
                    >
                        <a
                            href={vendorAssessments.export().url}
                            target="_blank"
                        >
                            <FileSpreadsheet className="mr-2 size-4" />
                            Ekspor Excel
                        </a>
                    </Button>
                </div>

                {vendors.data.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={ClipboardCheck}
                            title="Belum ada formulir penilaian"
                            description="Buat formulir penilaian kinerja untuk penyedia yang sudah menyelesaikan pekerjaan."
                        />
                    </div>
                ) : (
                    <div className="flex flex-1 flex-col gap-4">
                        <div
                            className={cn(
                                'grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5',
                                isFullPage && 'flex-1 auto-rows-fr',
                            )}
                        >
                            {vendors.data.map((vendor) => {
                                const isComplete =
                                    vendor.complete_count ===
                                    vendor.assessment_count;

                                return (
                                    <Link
                                        key={vendor.name}
                                        href={
                                            vendorAssessments.vendor({
                                                query: {
                                                    nama: vendor.name,
                                                    ...(filters.year
                                                        ? { year: filters.year }
                                                        : {}),
                                                    ...(filters.month
                                                        ? {
                                                              month: filters.month,
                                                          }
                                                        : {}),
                                                    ...(filters.search
                                                        ? {
                                                              search: filters.search,
                                                          }
                                                        : {}),
                                                    ...(currentPage > 1
                                                        ? { page: currentPage }
                                                        : {}),
                                                },
                                            }).url
                                        }
                                        title={vendor.name}
                                        className="group flex h-full items-start gap-2 rounded-md border border-border bg-card p-2.5 text-left transition-colors hover:border-primary/50 hover:bg-accent/40"
                                    >
                                        <Folder className="mt-0.5 size-5 shrink-0 text-amber-500 group-hover:hidden dark:text-amber-400" />
                                        <FolderOpen className="mt-0.5 hidden size-5 shrink-0 text-primary group-hover:block" />
                                        <span className="min-w-0 space-y-0.5">
                                            <span className="line-clamp-2 text-xs font-semibold text-foreground">
                                                {vendor.name}
                                            </span>
                                            <span className="tabular block text-[11px] text-muted-foreground">
                                                {vendor.assessment_count}{' '}
                                                penilaian
                                            </span>
                                            <span
                                                className={cn(
                                                    'tabular inline-block rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                                                    isComplete
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                        : 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
                                                )}
                                            >
                                                {isComplete
                                                    ? 'Semua lengkap'
                                                    : `${vendor.complete_count}/${vendor.assessment_count} lengkap`}
                                            </span>
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>

                        <div className="mt-auto overflow-hidden rounded-md border border-border bg-card">
                            <DataPagination page={vendors} />
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

VendorAssessmentIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penilaian Penyedia', href: vendorAssessments.index() },
    ],
};
