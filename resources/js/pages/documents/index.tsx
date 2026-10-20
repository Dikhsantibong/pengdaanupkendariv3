import { Head, Link, router } from '@inertiajs/react';
import { Archive, Folder, FolderOpen, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PeriodFilter } from '@/components/period-filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import documents from '@/routes/documents';
import type { Option, Paginated } from '@/types';

const ALL = 'all';

/** One procurement (pekerjaan), opened as a folder of its own page. */
type ArchiveGroup = {
    id: number;
    number: string;
    name: string;
    document_count: number;
};

type ArchiveFolder = {
    key: string;
    name: string;
    description: string;
    count: number;
};

export default function DocumentArchive({
    groups: page,
    folders,
    filters,
    documentTypes,
    years,
}: {
    groups: Paginated<ArchiveGroup>;
    folders: ArchiveFolder[];
    filters: {
        search: string | null;
        document_type_id: number | null;
        folder: string | null;
        year: number | null;
        month: number | null;
    };
    documentTypes: Option[];
    /** The years documents were drawn in. */
    years: number[];
}) {
    const openFolder = folders.find((folder) => folder.key === filters.folder);

    const [search, setSearch] = useState(filters.search ?? '');
    // The filters a work folder is opened from, to come back to them.
    const folderQuery = Object.fromEntries(
        Object.entries({
            ...filters,
            page:
                (page.meta?.current_page ?? page.current_page ?? 1) > 1
                    ? (page.meta?.current_page ?? page.current_page)
                    : null,
        }).filter(([, value]) => value !== null && value !== undefined),
    ) as Record<string, string | number>;
    const perPage = page.meta?.per_page ?? page.per_page ?? 30;
    const isFullPage = page.data.length >= perPage;
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

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pengadaan"
                    title="Arsip Dokumen"
                    description="Seluruh dokumen yang pernah digenerate tersimpan apa adanya, dikelompokkan dalam folder per jenis kontrak (SPK, PJ, SPPL) lalu per pekerjaan."
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {[
                        {
                            key: null,
                            name: 'Semua Dokumen',
                            description: 'Seluruh folder',
                            count: folders.reduce(
                                (total, folder) => total + folder.count,
                                0,
                            ),
                        },
                        ...folders,
                    ].map((folder) => {
                        const isOpen = folder.key === filters.folder;
                        const Icon = isOpen ? FolderOpen : Folder;

                        return (
                            <button
                                key={folder.key ?? 'all'}
                                type="button"
                                onClick={() => visit({ folder: folder.key })}
                                aria-pressed={isOpen}
                                className={cn(
                                    'flex items-center gap-3 rounded-md border border-border bg-card p-3 text-left transition-colors hover:border-primary/50 hover:bg-accent/40',
                                    isOpen &&
                                        'border-primary bg-primary/5 ring-1 ring-primary/30',
                                )}
                            >
                                <Icon
                                    className={cn(
                                        'size-8 shrink-0',
                                        isOpen
                                            ? 'text-primary'
                                            : 'text-amber-500 dark:text-amber-400',
                                    )}
                                />
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-semibold text-foreground">
                                        {folder.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {folder.description}
                                    </span>
                                    <span className="tabular block text-xs text-muted-foreground">
                                        {folder.count} dokumen
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>

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
                                placeholder={
                                    openFolder
                                        ? `Cari di folder ${openFolder.name}`
                                        : 'Judul dokumen, nomor, atau nama pekerjaan'
                                }
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

                    <PeriodFilter
                        years={years}
                        year={filters.year}
                        month={filters.month}
                        onChange={(next) => visit(next)}
                    />
                </div>

                {page.data.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Archive}
                            title={
                                openFolder
                                    ? `Folder ${openFolder.name} masih kosong`
                                    : 'Belum ada dokumen diarsipkan'
                            }
                            description="Dokumen akan muncul di sini setelah digenerate dari halaman detail pengadaan."
                        />
                    </div>
                ) : (
                    <div className="flex flex-1 flex-col gap-4">
                        <div
                            className={cn(
                                'grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5',
                                // A full page stretches its rows to the bottom
                                // of the screen, leaving no empty band.
                                isFullPage && 'flex-1 auto-rows-fr',
                            )}
                        >
                            {page.data.map((group) => (
                                <Link
                                    key={group.id}
                                    href={
                                        documents.procurement(group.id, {
                                            query: folderQuery,
                                        }).url
                                    }
                                    title={group.name}
                                    className="group flex h-full items-start gap-2 rounded-md border border-border bg-card p-2.5 text-left transition-colors hover:border-primary/50 hover:bg-accent/40"
                                >
                                    <Folder className="mt-0.5 size-5 shrink-0 text-amber-500 group-hover:hidden dark:text-amber-400" />
                                    <FolderOpen className="mt-0.5 hidden size-5 shrink-0 text-primary group-hover:block" />
                                    <span className="min-w-0">
                                        <span className="line-clamp-2 text-xs font-semibold text-foreground">
                                            {group.name}
                                        </span>
                                        <span className="tabular block truncate text-[11px] text-muted-foreground">
                                            {group.number}
                                        </span>
                                        <span className="tabular block text-[11px] text-muted-foreground">
                                            {group.document_count} dokumen
                                        </span>
                                    </span>
                                </Link>
                            ))}
                        </div>

                        <div className="mt-auto overflow-hidden rounded-md border border-border bg-card">
                            <DataPagination page={page} />
                        </div>
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
