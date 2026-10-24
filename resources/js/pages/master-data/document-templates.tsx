import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    Eye,
    FileCode2,
    Pencil,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { TemplatePreviewDialog } from '@/components/template-preview-dialog';
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
import masterData from '@/routes/master-data';
import type { EnumOption, Paginated } from '@/types';

type TemplateRow = {
    id: number;
    document_type_id: number;
    document_type: string;
    procurement_method_id: number | null;
    procurement_method: string | null;
    contract_number_format: string | null;
    work_category: string | null;
    name: string;
    version: number;
    placeholders: string[];
    /** default: shipped wording; modified: shipped then edited; custom: written here. */
    origin?: 'default' | 'modified' | 'custom';
    is_active: boolean;
    usage_count: number;
    updated_at: string | null;
};

type TemplatePreview = { id: number; title: string; body: string };

/** The filters of the list, each null when not set. */
type Filters = {
    q: string | null;
    jenis: string | null;
    metode: string | null;
    format: string | null;
    kategori: string | null;
    status: string | null;
    asal: string | null;
};

type FilterOptions = {
    documentTypes: EnumOption[];
    procurementMethods: EnumOption[];
    contractNumberFormats: EnumOption[];
    workCategories: EnumOption[];
};

/** A Select cannot carry an empty value: this one means "every one". */
const ALL = '__all__';

function FilterSelect({
    id,
    label,
    value,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string | null;
    options: EnumOption[];
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="w-full space-y-1.5 sm:w-44">
            <Label htmlFor={id} className="section-label">
                {label}
            </Label>
            <Select
                value={value ?? ALL}
                onValueChange={(next) => onChange(next === ALL ? null : next)}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Semua</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export default function DocumentTemplates({
    templates: page,
    filters,
    filterOptions,
    preview: loadedPreview,
}: {
    templates: Paginated<TemplateRow>;
    filters: Filters;
    filterOptions: FilterOptions;
    /** Loaded on demand: the list itself ships without template bodies. */
    preview?: TemplatePreview | null;
}) {
    const templates = page.data;
    const [preview, setPreview] = useState<TemplatePreview | null>(null);
    const [search, setSearch] = useState(filters.q ?? '');
    const isFiltered = Object.values(filters).some((value) => value !== null);

    /** The list again with these filters, from its first page. */
    const visit = (next: Partial<Filters>) =>
        router.get(
            masterData.documentTemplates.index().url,
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // Typed search waits for a pause before the list follows it.
    const firstSearch = useRef(true);

    useEffect(() => {
        if (firstSearch.current) {
            firstSearch.current = false;

            return;
        }

        const timer = window.setTimeout(
            () => visit({ q: search.trim() || null }),
            300,
        );

        return () => window.clearTimeout(timer);
        // Only the typed text starts a search; the other filters ride along.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const scopeOptions = (label: string, options: EnumOption[]) => [
        // Applies to every one of them: none picked on the template.
        { value: 'semua', label },
        ...options,
    ];
    const [loadingId, setLoadingId] = useState<number | null>(null);

    const openPreview = (template: TemplateRow) => {
        if (loadedPreview?.id === template.id) {
            setPreview(loadedPreview);

            return;
        }

        setLoadingId(template.id);
        router.reload({
            only: ['preview'],
            data: { preview: template.id },
            onSuccess: (page) => {
                const loaded = (
                    page.props as { preview?: TemplatePreview | null }
                ).preview;

                if (loaded) {
                    setPreview(loaded);
                }
            },
            onFinish: () => setLoadingId(null),
        });
    };

    return (
        <>
            <Head title="Template Dokumen" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Data Master"
                    title="Template Dokumen"
                    description="Template standar sementara. Saat template resmi UP Kendari tersedia, cukup ubah templatenya di sini tanpa mengubah logika aplikasi. Setiap dokumen hanya memakai satu template."
                    actions={
                        <Button asChild>
                            <Link
                                href={masterData.documentTemplates.create().url}
                            >
                                <Plus className="size-4" />
                                Tambah Template
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <div className="w-full min-w-56 flex-1 space-y-1.5">
                        <Label
                            htmlFor="template-search"
                            className="section-label"
                        >
                            Cari
                        </Label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="template-search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Nama template atau jenis dokumen"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <FilterSelect
                        id="filter-jenis"
                        label="Jenis Dokumen"
                        value={filters.jenis}
                        options={filterOptions.documentTypes}
                        onChange={(jenis) => visit({ jenis })}
                    />
                    <FilterSelect
                        id="filter-metode"
                        label="Metode"
                        value={filters.metode}
                        options={scopeOptions(
                            'Semua metode (bawaan)',
                            filterOptions.procurementMethods,
                        )}
                        onChange={(metode) => visit({ metode })}
                    />
                    <FilterSelect
                        id="filter-format"
                        label="Format Kontrak"
                        value={filters.format}
                        options={scopeOptions(
                            'Semua format',
                            filterOptions.contractNumberFormats,
                        )}
                        onChange={(format) => visit({ format })}
                    />
                    <FilterSelect
                        id="filter-kategori"
                        label="Kategori"
                        value={filters.kategori}
                        options={scopeOptions(
                            'Semua kategori',
                            filterOptions.workCategories,
                        )}
                        onChange={(kategori) => visit({ kategori })}
                    />
                    <FilterSelect
                        id="filter-status"
                        label="Status"
                        value={filters.status}
                        options={[
                            { value: 'aktif', label: 'Aktif' },
                            { value: 'nonaktif', label: 'Nonaktif' },
                        ]}
                        onChange={(status) => visit({ status })}
                    />
                    <FilterSelect
                        id="filter-asal"
                        label="Asal"
                        value={filters.asal}
                        options={[
                            { value: 'bawaan', label: 'Template bawaan' },
                            { value: 'buatan', label: 'Dibuat di sini' },
                        ]}
                        onChange={(asal) => visit({ asal })}
                    />
                    {isFiltered && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    masterData.documentTemplates.index().url,
                                    {},
                                    { preserveScroll: true, replace: true },
                                );
                            }}
                        >
                            <RotateCcw className="size-4" />
                            Reset
                        </Button>
                    )}
                </div>

                {templates.length === 0 && isFiltered ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Search}
                            title="Tidak ada template yang cocok"
                            description="Ubah kata pencarian atau filter, atau klik Reset untuk melihat semua template."
                        />
                    </div>
                ) : templates.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={FileCode2}
                            title="Belum ada template"
                            description="Tambahkan template pertama agar dokumen dapat digenerate dari data pengadaan."
                            action={
                                <Button size="sm" asChild>
                                    <Link
                                        href={
                                            masterData.documentTemplates.create()
                                                .url
                                        }
                                    >
                                        Tambah Template
                                    </Link>
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Jenis Dokumen</TableHead>
                                    <TableHead>Berlaku Untuk</TableHead>
                                    <TableHead>Template</TableHead>
                                    <TableHead>Versi</TableHead>
                                    <TableHead>Placeholder</TableHead>
                                    <TableHead>Dipakai</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {templates.map((template) => (
                                    <TableRow key={template.id}>
                                        <TableCell>
                                            {template.document_type}
                                        </TableCell>
                                        <TableCell>
                                            {template.procurement_method ?? (
                                                <span className="text-muted-foreground">
                                                    Semua metode
                                                </span>
                                            )}
                                            {template.origin && (
                                                <span
                                                    className={
                                                        template.origin ===
                                                        'custom'
                                                            ? 'ml-1.5 inline-flex items-center rounded-sm bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400'
                                                            : template.origin ===
                                                                'modified'
                                                              ? 'ml-1.5 inline-flex items-center rounded-sm bg-amber-500/10 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400'
                                                              : 'ml-1.5 inline-flex items-center rounded-sm bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground'
                                                    }
                                                    title={
                                                        template.origin ===
                                                        'custom'
                                                            ? 'Dibuat di aplikasi'
                                                            : template.origin ===
                                                                'modified'
                                                              ? 'Template bawaan yang sudah diubah; tidak ditimpa saat pembaruan'
                                                              : 'Template bawaan sistem; ikut diperbarui saat template bawaan diperbarui'
                                                    }
                                                >
                                                    {template.origin ===
                                                    'custom'
                                                        ? 'Kustom'
                                                        : template.origin ===
                                                            'modified'
                                                          ? 'Bawaan · diubah'
                                                          : 'Bawaan'}
                                                </span>
                                            )}
                                            {template.contract_number_format && (
                                                <span className="ml-1.5 inline-flex items-center rounded-sm bg-muted px-1.5 py-0.5 text-[11px] font-medium text-foreground">
                                                    {
                                                        template.contract_number_format
                                                    }
                                                </span>
                                            )}
                                            {template.work_category && (
                                                <span className="ml-1.5 inline-flex items-center rounded-sm bg-primary/10 px-1.5 py-0.5 text-[11px] font-medium text-primary">
                                                    {template.work_category}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {template.name}
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                Diperbarui{' '}
                                                {formatDateTime(
                                                    template.updated_at,
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="tabular">
                                            v{template.version}
                                        </TableCell>
                                        <TableCell className="tabular">
                                            {template.placeholders.length}
                                        </TableCell>
                                        <TableCell className="tabular text-muted-foreground">
                                            {template.usage_count}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={
                                                    template.is_active
                                                        ? 'inline-flex items-center rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                                                        : 'inline-flex items-center rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
                                                }
                                            >
                                                {template.is_active
                                                    ? 'Aktif'
                                                    : 'Arsip'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                disabled={
                                                    loadingId === template.id
                                                }
                                                onClick={() =>
                                                    openPreview(template)
                                                }
                                            >
                                                <Eye className="size-3.5" />
                                                Pratinjau
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        masterData.documentTemplates.edit(
                                                            template.id,
                                                        ).url
                                                    }
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Ubah
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            `Arsipkan template ${template.name} v${template.version}? Dokumen yang sudah digenerate tidak berubah.`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            masterData.documentTemplates.destroy(
                                                                template.id,
                                                            ).url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Archive className="size-3.5" />
                                                Arsipkan
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <DataPagination page={page} />
                    </div>
                )}
            </div>

            <TemplatePreviewDialog
                title={preview?.title ?? ''}
                body={preview?.body ?? null}
                onClose={() => setPreview(null)}
            />
        </>
    );
}

DocumentTemplates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Template Dokumen',
            href: masterData.documentTemplates.index(),
        },
    ],
};
