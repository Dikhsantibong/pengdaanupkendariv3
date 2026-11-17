import { Head, router, useForm } from '@inertiajs/react';
import { Database, Pencil, Plus, Power, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FieldControl } from '@/components/master-data-page';
import type {
    MasterField,
    MasterFormValue,
    MasterFormValues,
    MasterRecord,
} from '@/components/master-data-page';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type MethodRecord = MasterRecord & {
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
};

type MethodTab = 'selection' | 'internal';

type TabConfig = {
    key: MethodTab;
    label: string;
    singularLabel: string;
    addLabel: string;
    description: string;
    searchPlaceholder: string;
    fields: MasterField[];
    defaults: MasterFormValues;
    storeUrl: string;
    updateUrl: (record: MethodRecord) => string;
    destroyUrl: (record: MethodRecord) => string;
};

const SELECTION_CONFIG: TabConfig = {
    key: 'selection',
    label: 'Metode Pengadaan',
    singularLabel: 'Metode Pengadaan',
    addLabel: 'Tambah Metode Pengadaan',
    description:
        'Cara mitra dipilih (Penunjukan Langsung, Pengadaan Langsung, Lelang Terbuka). Dipilih PIC Perencana di detail pengadaan dan tercetak pada dokumen.',
    searchPlaceholder: 'Cari metode atau kode pengadaan…',
    fields: [
        {
            name: 'name',
            label: 'Nama Metode',
            type: 'text',
            required: true,
            placeholder: 'Contoh: Penunjukan Langsung',
        },
        {
            name: 'code',
            label: 'Kode',
            type: 'text',
            required: true,
            placeholder: 'Contoh: penunjukan-langsung',
            hint: 'Disimpan sebagai slug untuk referensi internal.',
        },
        {
            name: 'description',
            label: 'Keterangan',
            type: 'text',
            placeholder: 'Opsional',
        },
        { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
        {
            name: 'is_active',
            label: 'Aktif',
            type: 'switch',
            hint: 'Hanya metode aktif yang dapat dipilih PIC Perencana.',
        },
    ],
    defaults: {
        name: '',
        code: '',
        description: '',
        sort_order: 0,
        is_active: true,
    },
    storeUrl: masterData.selectionMethods.store().url,
    updateUrl: (record) => masterData.selectionMethods.update(record.id).url,
    destroyUrl: (record) => masterData.selectionMethods.destroy(record.id).url,
};

const INTERNAL_CONFIG: TabConfig = {
    key: 'internal',
    label: 'Metode Internal (Template)',
    singularLabel: 'Metode Internal',
    addLabel: 'Tambah Metode Internal',
    description:
        'Metode internal yang mengikuti format kontrak (diatur di Format No Kontrak) untuk menentukan pilihan template dokumen dan tahapan checklist.',
    searchPlaceholder: 'Cari metode internal atau kode…',
    fields: [
        {
            name: 'name',
            label: 'Nama Metode',
            type: 'text',
            required: true,
            placeholder: 'Contoh: Surat Pesanan',
        },
        {
            name: 'code',
            label: 'Kode',
            type: 'text',
            required: true,
            placeholder: 'Contoh: surat-pesanan',
            hint: 'Dipakai sebagai referensi internal dan pada dokumen.',
        },
        {
            name: 'description',
            label: 'Keterangan',
            type: 'text',
            placeholder: 'Opsional',
        },
        { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
        {
            name: 'is_active',
            label: 'Aktif',
            type: 'switch',
            hint: 'Hanya metode aktif yang dipakai sebagai acuan template.',
        },
    ],
    defaults: {
        name: '',
        code: '',
        description: '',
        sort_order: 0,
        is_active: true,
    },
    storeUrl: masterData.procurementMethods.store().url,
    updateUrl: (record) => masterData.procurementMethods.update(record.id).url,
    destroyUrl: (record) => masterData.procurementMethods.destroy(record.id).url,
};

/**
 * Data Master · Metode Pengadaan & Metode Internal: both method lists on one
 * clean tabbed screen, backed by their separate tables without schema changes.
 */
export default function SelectionMethods({
    records,
    internalMethods = [],
    initialTab = 'selection',
}: {
    records: MethodRecord[];
    internalMethods?: MethodRecord[];
    initialTab?: MethodTab;
}) {
    const [tab, setTab] = useState<MethodTab>(initialTab);
    const [search, setSearch] = useState('');
    const [editing, setEditing] = useState<{
        config: TabConfig;
        record: MethodRecord | null;
    } | null>(null);

    const activeConfig =
        tab === 'internal' ? INTERNAL_CONFIG : SELECTION_CONFIG;

    const filterRows = useMemo(
        () => (rows: MethodRecord[]) => {
            const term = search.trim().toLowerCase();

            if (term === '') {
                return rows;
            }

            return rows.filter(
                (row) =>
                    row.name.toLowerCase().includes(term) ||
                    row.code.toLowerCase().includes(term) ||
                    (row.description ?? '').toLowerCase().includes(term),
            );
        },
        [search],
    );

    const visibleSelection = filterRows(records);
    const visibleInternal = filterRows(internalMethods);

    const openCreate = (config: TabConfig) =>
        setEditing({ config, record: null });
    const openEdit = (config: TabConfig, record: MethodRecord) =>
        setEditing({ config, record });

    return (
        <>
            <Head title="Metode Pengadaan" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Data Master"
                    title="Metode Pengadaan"
                    description="Kelola Metode Pengadaan (cara pemilihan penyedia) dan Metode Internal (acuan template dokumen & format kontrak) dalam satu menu."
                    actions={
                        <Button onClick={() => openCreate(activeConfig)}>
                            <Plus className="size-4" />
                            {activeConfig.addLabel}
                        </Button>
                    }
                />

                <Tabs
                    defaultValue={tab}
                    value={tab}
                    onValueChange={(next) => {
                        setTab(next as MethodTab);
                        setSearch('');
                    }}
                    className="flex flex-col gap-4"
                >
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <TabsList>
                            <TabsTrigger value="selection">
                                Metode Pengadaan ({records.length})
                            </TabsTrigger>
                            <TabsTrigger value="internal">
                                Metode Internal ({internalMethods.length})
                            </TabsTrigger>
                        </TabsList>

                        <div className="relative w-full sm:w-72">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={activeConfig.searchPlaceholder}
                                className="pl-9"
                                aria-label="Cari metode"
                            />
                        </div>
                    </div>

                    <TabsContent value="selection" className="mt-0 space-y-3">
                        <MethodSection
                            config={SELECTION_CONFIG}
                            rows={visibleSelection}
                            filtered={search.trim() !== ''}
                            onAdd={() => openCreate(SELECTION_CONFIG)}
                            onEdit={(record) =>
                                openEdit(SELECTION_CONFIG, record)
                            }
                        />
                    </TabsContent>

                    <TabsContent value="internal" className="mt-0 space-y-3">
                        <MethodSection
                            config={INTERNAL_CONFIG}
                            rows={visibleInternal}
                            filtered={search.trim() !== ''}
                            onAdd={() => openCreate(INTERNAL_CONFIG)}
                            onEdit={(record) =>
                                openEdit(INTERNAL_CONFIG, record)
                            }
                        />
                    </TabsContent>
                </Tabs>

                <p className="text-xs text-muted-foreground">
                    Data master yang sudah dipakai pada pengadaan tidak dihapus
                    permanen, melainkan dinonaktifkan agar riwayat pengadaan
                    lama tetap utuh.
                </p>
            </div>

            {editing !== null && (
                <MethodFormDialog
                    key={`${editing.config.key}-${editing.record?.id ?? 'new'}`}
                    config={editing.config}
                    record={editing.record}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function MethodSection({
    config,
    rows,
    filtered,
    onAdd,
    onEdit,
}: {
    config: TabConfig;
    rows: MethodRecord[];
    filtered: boolean;
    onAdd: () => void;
    onEdit: (record: MethodRecord) => void;
}) {
    return (
        <>
            <p className="rounded-md border border-border bg-muted/30 px-3.5 py-2 text-xs text-muted-foreground">
                {config.description}
            </p>

            {rows.length === 0 ? (
                <div className="rounded-md border border-border bg-card">
                    <EmptyState
                        icon={Database}
                        title={
                            filtered
                                ? 'Tidak ada data yang cocok'
                                : 'Belum ada data'
                        }
                        description={
                            filtered
                                ? 'Ubah kata kunci pencarian untuk melihat data lainnya.'
                                : `Tambahkan ${config.singularLabel.toLowerCase()} pertama.`
                        }
                        action={
                            filtered ? undefined : (
                                <Button onClick={onAdd} size="sm">
                                    {config.addLabel}
                                </Button>
                            )
                        }
                    />
                </div>
            ) : (
                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <Table>
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Metode</TableHead>
                                <TableHead>Kode</TableHead>
                                <TableHead>Keterangan</TableHead>
                                <TableHead>Urutan</TableHead>
                                <TableHead>Dipakai</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((record) => (
                                <TableRow key={record.id}>
                                    <TableCell className="font-medium">
                                        {record.name}
                                    </TableCell>
                                    <TableCell className="tabular">
                                        {record.code}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {record.description || '—'}
                                    </TableCell>
                                    <TableCell className="tabular">
                                        {record.sort_order}
                                    </TableCell>
                                    <TableCell className="tabular text-muted-foreground">
                                        {record.usage_count ?? 0}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={
                                                record.is_active
                                                    ? 'inline-flex items-center gap-1.5 rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                                                    : 'inline-flex items-center gap-1.5 rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
                                            }
                                        >
                                            {record.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => onEdit(record)}
                                        >
                                            <Pencil className="size-3.5" />
                                            Ubah
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Nonaktifkan "${record.name}"? Data pengadaan lama tetap utuh.`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        config.destroyUrl(
                                                            record,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            <Power className="size-3.5" />
                                            Nonaktifkan
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </>
    );
}

function MethodFormDialog({
    config,
    record,
    onClose,
}: {
    config: TabConfig;
    record: MethodRecord | null;
    onClose: () => void;
}) {
    const initial: MasterFormValues = {};
    Object.keys(config.defaults).forEach((key) => {
        initial[key] =
            (record?.[key] as MasterFormValue) ?? config.defaults[key];
    });

    const form = useForm<MasterFormValues>(initial);

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
        };

        if (record === null) {
            form.post(config.storeUrl, options);

            return;
        }

        form.put(config.updateUrl(record), options);
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {record === null
                            ? config.addLabel
                            : `Ubah ${record.name}`}
                    </DialogTitle>
                    <DialogDescription>
                        Perubahan pada data master langsung berlaku untuk
                        pengadaan berikutnya.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="method-master-form"
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    {config.fields.map((field) => (
                        <FieldControl
                            key={field.name}
                            field={field}
                            value={form.data[field.name]}
                            onChange={(next) => form.setData(field.name, next)}
                            error={form.errors[field.name]}
                        />
                    ))}
                </form>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={form.processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="method-master-form"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

SelectionMethods.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Metode Pengadaan',
            href: masterData.selectionMethods.index(),
        },
    ],
};
