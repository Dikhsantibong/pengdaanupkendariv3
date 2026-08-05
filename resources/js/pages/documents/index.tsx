import { Head, Link, router } from '@inertiajs/react';
import { Archive, Download, Eye, Pencil, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import documents from '@/routes/documents';
import procurements from '@/routes/procurements';
import type { Option, Paginated } from '@/types';

const ALL = 'all';

type ArchiveRow = {
    id: number;
    title: string;
    type: string;
    template_version: number;
    procurement_id: number;
    procurement_number: string;
    procurement_name: string;
    generated_by: string | null;
    generated_at: string;
};

export default function DocumentArchive({
    documents: page,
    filters,
    documentTypes,
}: {
    documents: Paginated<ArchiveRow>;
    filters: { search: string | null; document_type_id: number | null };
    documentTypes: Option[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    const visit = (overrides: Record<string, string | number | null>) => {
        const merged = { ...filters, ...overrides };
        const query: Record<string, string> = {};

        Object.entries(merged).forEach(([key, value]) => {
            if (value !== null && value !== '') {
                query[key] = String(value);
            }
        });

        router.get(documents.index().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            visit({ search: search || null });
        }, 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <>
            <Head title="Arsip Dokumen" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pengadaan"
                    title="Arsip Dokumen"
                    description="Seluruh dokumen yang pernah digenerate tersimpan apa adanya, termasuk versi template yang dipakai saat itu."
                />

                <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3 lg:flex-row lg:items-end">
                    <div className="flex-1 space-y-1.5">
                        <Label
                            htmlFor="document-search"
                            className="section-label"
                        >
                            Cari
                        </Label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="document-search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Judul dokumen atau nomor pengadaan"
                                className="pl-9"
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5 lg:w-64">
                        <Label className="section-label">Jenis Dokumen</Label>
                        <Select
                            value={
                                filters.document_type_id === null
                                    ? ALL
                                    : String(filters.document_type_id)
                            }
                            onValueChange={(value) =>
                                visit({
                                    document_type_id:
                                        value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Semua" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Semua</SelectItem>
                                {documentTypes.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={String(type.value)}
                                    >
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {page.data.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Archive}
                            title="Belum ada dokumen diarsipkan"
                            description="Dokumen akan muncul di sini setelah digenerate dari halaman detail pengadaan."
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Dokumen</TableHead>
                                    <TableHead>Pengadaan</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Digenerate</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            {row.title}
                                            <span className="tabular block text-xs font-normal text-muted-foreground">
                                                Template v{row.template_version}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={procurements.show(
                                                    row.procurement_id,
                                                )}
                                                className="block max-w-[16rem] truncate hover:text-primary hover:underline"
                                            >
                                                {row.procurement_name}
                                            </Link>
                                            <span className="tabular text-xs text-muted-foreground">
                                                {row.procurement_number}
                                            </span>
                                        </TableCell>
                                        <TableCell>{row.type}</TableCell>
                                        <TableCell className="tabular text-xs text-muted-foreground">
                                            {formatDateTime(row.generated_at)}
                                            {row.generated_by
                                                ? ` · ${row.generated_by}`
                                                : ''}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Link
                                                        href={
                                                            procurements.documents.edit(
                                                                {
                                                                    procurement:
                                                                        row.procurement_id,
                                                                    document:
                                                                        row.id,
                                                                },
                                                            ).url
                                                        }
                                                    >
                                                        <Pencil className="size-3.5" />
                                                        Edit
                                                    </Link>
                                                </Button>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <a
                                                        href={`${
                                                            procurements.documents.show(
                                                                {
                                                                    procurement:
                                                                        row.procurement_id,
                                                                    document:
                                                                        row.id,
                                                                },
                                                            ).url
                                                        }?format=html`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Eye className="size-3.5" />
                                                        Pratinjau
                                                    </a>
                                                </Button>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a
                                                        href={
                                                            procurements.documents.show(
                                                                {
                                                                    procurement:
                                                                        row.procurement_id,
                                                                    document:
                                                                        row.id,
                                                                },
                                                            ).url
                                                        }
                                                    >
                                                        <Download className="size-3.5" />
                                                        Unduh PDF
                                                    </a>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <DataPagination page={page} />
                    </div>
                )}
            </div>
        </>
    );
}

DocumentArchive.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Arsip Dokumen', href: documents.index() },
    ],
};
