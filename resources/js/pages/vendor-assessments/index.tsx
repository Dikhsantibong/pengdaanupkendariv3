import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import vendorAssessments from '@/routes/vendor-assessments';
import type { Paginated } from '@/types';

type AssessmentRow = {
    id: number;
    project: string;
    vendor_name: string;
    po_number: string | null;
    po_date: string | null;
    procurement_number: string | null;
    overall_average: number | null;
    scored: number;
    total: number;
    created_by: string | null;
    created_at: string | null;
};

export default function VendorAssessmentIndex({
    assessments,
    filters,
}: {
    assessments: Paginated<AssessmentRow>;
    filters: { search: string | null };
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
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    return (
        <>
            <Head title="Penilaian Kinerja Penyedia" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="SMT-FM-DAN-02.02"
                    title="Penilaian Kinerja Penyedia Barang dan Jasa"
                    description="Formulir penilaian penyedia yang dinilai oleh lima fungsi, dengan rekapitulasi hasil akhir."
                    actions={
                        <Button asChild>
                            <Link href={vendorAssessments.create()}>
                                <Plus className="size-4" />
                                Buat Formulir Penilaian
                            </Link>
                        </Button>
                    }
                />

                <div className="relative max-w-sm">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari pekerjaan, penyedia atau nomor PO"
                        className="pl-9"
                    />
                </div>

                {assessments.data.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={ClipboardCheck}
                            title="Belum ada formulir penilaian"
                            description="Buat formulir penilaian kinerja untuk penyedia yang sudah menyelesaikan pekerjaan."
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Pekerjaan</TableHead>
                                    <TableHead>Penyedia</TableHead>
                                    <TableHead>No PO</TableHead>
                                    <TableHead>Kelengkapan</TableHead>
                                    <TableHead>Nilai Akhir</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assessments.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            {row.project}
                                            {row.procurement_number && (
                                                <div className="tabular text-xs text-muted-foreground">
                                                    {row.procurement_number}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>{row.vendor_name}</TableCell>
                                        <TableCell className="tabular text-xs">
                                            {row.po_number ?? '—'}
                                            {row.po_date && (
                                                <div className="text-muted-foreground">
                                                    {formatDate(row.po_date)}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="tabular text-xs">
                                            {row.scored}/{row.total} aspek
                                        </TableCell>
                                        <TableCell className="tabular font-semibold">
                                            {row.overall_average === null
                                                ? '—'
                                                : row.overall_average
                                                      .toFixed(2)
                                                      .replace('.', ',')}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={vendorAssessments.show(
                                                        row.id,
                                                    )}
                                                >
                                                    Buka
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <DataPagination page={assessments} />
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
