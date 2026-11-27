import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    Clock,
    Copy,
    Eye,
    FileEdit,
    FileText,
    Pencil,
    Plus,
    Search,
    Send,
    Tag,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import coaRequests from '@/routes/coa-requests';
import type { Paginated } from '@/types';

type CoaStatus = 'draft' | 'menunggu_aktivasi' | 'aktif';

type SegmentKey =
    | 'segment_1'
    | 'segment_2'
    | 'segment_3'
    | 'segment_4'
    | 'segment_5'
    | 'segment_6'
    | 'segment_7'
    | 'expense_element';

type SegmentOption = {
    code: string;
    name: string;
    description: string | null;
};

type SegmentMeta = {
    value: SegmentKey;
    label: string;
    shortLabel: string;
};

type CoaRequestRow = {
    id: number;
    coa_code: string;
    segment_1: string;
    segment_2: string;
    segment_3: string;
    segment_4: string;
    segment_5: string;
    segment_6: string;
    segment_7: string;
    expense_element: string;
    segment_labels: Record<SegmentKey, string>;
    description: string | null;
    notes: string | null;
    status: CoaStatus;
    status_label: string;
    requester: {
        id: number;
        name: string;
        position: string | null;
    } | null;
    work_director: string | null;
    activator: string | null;
    updater: string | null;
    requested_at: string | null;
    activated_at: string | null;
    updated_at: string | null;
    can_edit: boolean;
    can_submit: boolean;
    can_activate: boolean;
    can_delete: boolean;
};

type CoaFormValues = {
    segment_1: string;
    segment_2: string;
    segment_3: string;
    segment_4: string;
    segment_5: string;
    segment_6: string;
    segment_7: string;
    expense_element: string;
    coa_code: string;
    description: string;
    notes: string;
    action: 'save' | 'request_activation' | 'activate';
};

type CoaRequestsPageProps = {
    coaRequests: Paginated<CoaRequestRow>;
    summary: {
        total: number;
        draft: number;
        pending: number;
        active: number;
    };
    filters: {
        search: string;
        status: string;
    };
    segments: Record<SegmentKey, SegmentOption[]>;
    defaultSegment2: { code: string; name: string };
    segmentMeta: SegmentMeta[];
    statusOptions: Array<{ value: CoaStatus; label: string }>;
    canRequest: boolean;
    canManage: boolean;
};

const SEGMENT_KEYS: SegmentKey[] = [
    'segment_1',
    'segment_2',
    'segment_3',
    'segment_4',
    'segment_5',
    'segment_6',
    'segment_7',
    'expense_element',
];

function composePreview(data: CoaFormValues): string {
    return [
        data.segment_1,
        data.segment_2,
        data.segment_3,
        data.segment_4,
        data.segment_5,
        data.segment_6,
        data.segment_7,
        data.expense_element,
    ]
        .map((part) => part.trim().toUpperCase())
        .join('');
}

const STATUS_BADGE: Record<CoaStatus, string> = {
    draft: 'border-slate-300 bg-slate-100 text-slate-700',
    menunggu_aktivasi: 'border-amber-300 bg-amber-100 text-amber-800',
    aktif: 'border-emerald-300 bg-emerald-100 text-emerald-800',
};

export default function CoaRequestsIndex({
    coaRequests: page,
    summary,
    filters,
    segments,
    defaultSegment2,
    segmentMeta,
    canRequest,
    canManage,
}: CoaRequestsPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<CoaRequestRow | null>(null);
    const [viewing, setViewing] = useState<CoaRequestRow | null>(null);
    const [copiedId, setCopiedId] = useState<number | null>(null);
    const [customCodeMode, setCustomCodeMode] = useState(false);

    const emptyDefaults = (): CoaFormValues => ({
        segment_1: '',
        segment_2: defaultSegment2.code,
        segment_3: '',
        segment_4: '',
        segment_5: '',
        segment_6: '',
        segment_7: '',
        expense_element: '',
        coa_code: '',
        description: '',
        notes: '',
        action: 'save',
    });

    const form = useForm<CoaFormValues>(emptyDefaults());

    const visitFilter = (next: { search?: string; status?: string }) => {
        router.get(
            coaRequests.index().url,
            {
                search: next.search !== undefined ? next.search : search,
                status: next.status !== undefined ? next.status : filters.status,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const openCreate = () => {
        const defaults = emptyDefaults();
        setEditing(null);
        setCustomCodeMode(false);
        form.setDefaults(defaults);
        form.setData(defaults);
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (row: CoaRequestRow) => {
        const composed = composePreview({
            segment_1: row.segment_1,
            segment_2: row.segment_2,
            segment_3: row.segment_3,
            segment_4: row.segment_4,
            segment_5: row.segment_5,
            segment_6: row.segment_6,
            segment_7: row.segment_7,
            expense_element: row.expense_element,
            coa_code: row.coa_code,
            description: row.description ?? '',
            notes: row.notes ?? '',
            action: 'save',
        });
        const values: CoaFormValues = {
            segment_1: row.segment_1,
            segment_2: row.segment_2,
            segment_3: row.segment_3,
            segment_4: row.segment_4,
            segment_5: row.segment_5,
            segment_6: row.segment_6,
            segment_7: row.segment_7,
            expense_element: row.expense_element,
            coa_code: row.coa_code,
            description: row.description ?? '',
            notes: row.notes ?? '',
            action: 'save',
        };
        setEditing(row);
        setCustomCodeMode(canManage && row.coa_code !== composed);
        form.setDefaults(values);
        form.setData(values);
        form.clearErrors();
        setDialogOpen(true);
    };

    const submitWithAction = (
        action: 'save' | 'request_activation' | 'activate',
    ) => {
        const payload = {
            ...form.data,
            action,
            coa_code:
                canManage && editing !== null && customCodeMode
                    ? form.data.coa_code
                    : '',
        };

        form.transform(() => payload);

        if (editing === null) {
            form.post(coaRequests.store().url, {
                preserveScroll: true,
                onSuccess: () => setDialogOpen(false),
            });

            return;
        }

        form.put(coaRequests.update(editing.id).url, {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        });
    };

    const handleFormSubmit = (event: FormEvent) => {
        event.preventDefault();
        submitWithAction('save');
    };

    const handleQuickSubmit = (row: CoaRequestRow) => {
        router.post(
            coaRequests.submit(row.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const handleQuickActivate = (row: CoaRequestRow) => {
        router.post(
            coaRequests.activate(row.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (row: CoaRequestRow) => {
        if (
            !window.confirm(
                `Hapus draft request COA ${row.coa_code}? Tindakan ini tidak dapat dibatalkan.`,
            )
        ) {
            return;
        }

        router.delete(coaRequests.destroy(row.id).url, {
            preserveScroll: true,
        });
    };

    const copyCode = (row: CoaRequestRow) => {
        navigator.clipboard?.writeText(row.coa_code);
        setCopiedId(row.id);
        window.setTimeout(() => {
            setCopiedId((current) => (current === row.id ? null : current));
        }, 1500);
    };

    const previewCode = composePreview(form.data);
    const allSegmentsFilled = SEGMENT_KEYS.every(
        (key) => form.data[key].trim() !== '',
    );

    const summaryCards: Array<{
        key: string;
        label: string;
        value: number;
        icon: typeof Tag;
        bg: string;
        border: string;
        text: string;
        iconBg: string;
    }> = [
        {
            key: '',
            label: 'Total COA',
            value: summary.total,
            icon: Tag,
            bg: 'bg-[#1d4ed8]',
            border: 'border-[#1e40af]',
            text: 'text-white',
            iconBg: 'bg-white/15 text-white',
        },
        {
            key: 'draft',
            label: 'Draft',
            value: summary.draft,
            icon: FileEdit,
            bg: 'bg-[#475569]',
            border: 'border-[#334155]',
            text: 'text-white',
            iconBg: 'bg-white/15 text-white',
        },
        {
            key: 'menunggu_aktivasi',
            label: 'Menunggu Aktivasi',
            value: summary.pending,
            icon: Clock,
            bg: 'bg-[#d97706]',
            border: 'border-[#b45309]',
            text: 'text-white',
            iconBg: 'bg-white/15 text-white',
        },
        {
            key: 'aktif',
            label: 'COA Aktif',
            value: summary.active,
            icon: CheckCircle2,
            bg: 'bg-[#15803d]',
            border: 'border-[#166534]',
            text: 'text-white',
            iconBg: 'bg-white/15 text-white',
        },
    ];

    const isFinanceEditing = canManage && editing !== null;

    return (
        <>
            <Head title="Request COA" />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pengadaan · Chart of Accounts"
                    title="Request COA"
                    description={
                        canManage && !canRequest
                            ? 'Tinjau, perbaiki bila perlu, dan aktifkan usulan nomor COA dari seluruh bidang.'
                            : 'Susun dan ajukan aktivasi nomor COA. COA aktif otomatis tersedia pada Usulan Pekerjaan.'
                    }
                    actions={
                        canRequest ? (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                Buat Request COA
                            </Button>
                        ) : undefined
                    }
                />

                {/* Flat solid summary cards */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {summaryCards.map((card) => {
                        const Icon = card.icon;
                        const selected = filters.status === card.key;

                        return (
                            <button
                                key={card.label}
                                type="button"
                                onClick={() =>
                                    visitFilter({ status: card.key })
                                }
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-4 text-left transition-all',
                                    card.bg,
                                    card.border,
                                    card.text,
                                    selected
                                        ? 'ring-2 ring-foreground/40 ring-offset-2'
                                        : 'opacity-95 hover:opacity-100',
                                )}
                            >
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide opacity-85">
                                        {card.label}
                                    </p>
                                    <p className="tabular mt-1 text-2xl font-bold">
                                        {card.value}
                                    </p>
                                </div>
                                <span
                                    className={cn(
                                        'flex size-10 items-center justify-center rounded-lg',
                                        card.iconBg,
                                    )}
                                >
                                    <Icon className="size-5" />
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Filter & search bar */}
                <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-1.5">
                        {[
                            { value: '', label: 'Semua' },
                            { value: 'menunggu_aktivasi', label: 'Menunggu Aktivasi' },
                            { value: 'draft', label: 'Draft' },
                            { value: 'aktif', label: 'Aktif' },
                        ].map((tab) => (
                            <button
                                key={tab.value || 'all'}
                                type="button"
                                onClick={() =>
                                    visitFilter({ status: tab.value })
                                }
                                className={cn(
                                    'rounded-md border px-3 py-1.5 text-xs font-medium transition-colors',
                                    filters.status === tab.value
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-foreground',
                                )}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <form
                        className="relative flex w-full gap-2 sm:w-80"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visitFilter({ search });
                        }}
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari kode COA, keperluan, pengusul..."
                                className="pl-8"
                            />
                        </div>
                        <Button type="submit" variant="outline" size="sm">
                            Cari
                        </Button>
                    </form>
                </div>

                {/* Table */}
                {page.data.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={FileText}
                            title="Belum ada data Request COA"
                            description={
                                filters.search || filters.status
                                    ? 'Tidak ada request COA yang cocok dengan filter saat ini.'
                                    : canRequest
                                      ? 'Klik "Buat Request COA" untuk menyusun nomor COA baru.'
                                      : 'Belum ada usulan aktivasi COA yang masuk.'
                            }
                        />
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="min-w-[240px]">
                                        Kode COA & Ringkasan Segmen
                                    </TableHead>
                                    <TableHead className="min-w-[160px]">
                                        Pengusul / Bidang
                                    </TableHead>
                                    <TableHead className="min-w-[200px]">
                                        Keperluan & Catatan
                                    </TableHead>
                                    <TableHead className="min-w-[150px]">
                                        Status
                                    </TableHead>
                                    <TableHead className="min-w-[200px] text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="align-top">
                                            <div className="flex items-center gap-1.5">
                                                <span className="rounded bg-muted px-2 py-0.5 font-mono text-xs font-semibold text-foreground">
                                                    {row.coa_code}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => copyCode(row)}
                                                    title="Salin kode COA"
                                                    className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                >
                                                    {copiedId === row.id ? (
                                                        <Check className="size-3.5 text-emerald-600" />
                                                    ) : (
                                                        <Copy className="size-3.5" />
                                                    )}
                                                </button>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {row.segment_labels.segment_3} ·{' '}
                                                {row.segment_labels.segment_4} ·{' '}
                                                {row.segment_labels.segment_6}
                                            </p>
                                            <p className="text-[11px] text-muted-foreground/80">
                                                {row.segment_labels.segment_7} ·{' '}
                                                {
                                                    row.segment_labels
                                                        .expense_element
                                                }
                                            </p>
                                        </TableCell>
                                        <TableCell className="align-top">
                                            <p className="text-sm font-medium text-foreground">
                                                {row.requester?.name ?? '—'}
                                            </p>
                                            {row.work_director && (
                                                <p className="text-xs text-muted-foreground">
                                                    {row.work_director}
                                                </p>
                                            )}
                                            {row.requested_at && (
                                                <p className="tabular mt-0.5 text-[11px] text-muted-foreground">
                                                    Diajukan{' '}
                                                    {formatDateTime(
                                                        row.requested_at,
                                                    )}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="align-top">
                                            <p className="text-sm text-foreground">
                                                {row.description || '—'}
                                            </p>
                                            {row.notes && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Catatan: {row.notes}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="align-top">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                                    STATUS_BADGE[row.status],
                                                )}
                                            >
                                                {row.status_label}
                                            </span>
                                            {row.status === 'aktif' &&
                                                row.activator && (
                                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                                        Oleh {row.activator}
                                                        {row.activated_at
                                                            ? ` · ${formatDateTime(row.activated_at)}`
                                                            : ''}
                                                    </p>
                                                )}
                                        </TableCell>
                                        <TableCell className="align-top text-right">
                                            <div className="flex flex-wrap items-center justify-end gap-1.5">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setViewing(row)
                                                    }
                                                >
                                                    <Eye className="size-3.5" />
                                                    Detail
                                                </Button>
                                                {row.can_edit && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEdit(row)
                                                        }
                                                    >
                                                        <Pencil className="size-3.5" />
                                                        Edit
                                                    </Button>
                                                )}
                                                {row.can_submit && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleQuickSubmit(
                                                                row,
                                                            )
                                                        }
                                                    >
                                                        <Send className="size-3.5" />
                                                        Request Aktivasi
                                                    </Button>
                                                )}
                                                {row.can_activate && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                                                        onClick={() =>
                                                            handleQuickActivate(
                                                                row,
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle2 className="size-3.5" />
                                                        Aktivasi
                                                    </Button>
                                                )}
                                                {row.can_delete && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-muted-foreground hover:text-destructive"
                                                        aria-label={`Hapus ${row.coa_code}`}
                                                        onClick={() =>
                                                            handleDelete(row)
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <DataPagination page={page} />
            </div>

            {/* Create / Edit COA Dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editing === null
                                ? 'Buat Request COA'
                                : isFinanceEditing
                                  ? 'Perbaiki & Kelola Request COA'
                                  : 'Ubah Draft Request COA'}
                        </DialogTitle>
                        <DialogDescription>
                            Pilih tiap segmen COA. Kode COA tersusun otomatis
                            dari gabungan seluruh segmen.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleFormSubmit} className="space-y-4">
                        {/* Live COA Code Preview */}
                        <div className="rounded-lg border border-border bg-muted/40 p-3.5">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Output Kode COA
                                </span>
                                {allSegmentsFilled ? (
                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800">
                                        Lengkap (8 segmen)
                                    </span>
                                ) : (
                                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                                        Lengkapi seluruh segmen
                                    </span>
                                )}
                            </div>
                            <p className="mt-1.5 break-all font-mono text-lg font-bold tracking-wide text-foreground">
                                {isFinanceEditing &&
                                customCodeMode &&
                                form.data.coa_code
                                    ? form.data.coa_code
                                    : previewCode || '—'}
                            </p>
                            <div className="mt-2 flex flex-wrap gap-1">
                                {segmentMeta.map((meta) => {
                                    const val = form.data[meta.value];

                                    return (
                                        <span
                                            key={meta.value}
                                            className={cn(
                                                'rounded border px-1.5 py-0.5 font-mono text-[11px]',
                                                val
                                                    ? 'border-primary/30 bg-primary/10 text-foreground'
                                                    : 'border-dashed border-border text-muted-foreground',
                                            )}
                                        >
                                            {meta.shortLabel}:{' '}
                                            <strong>{val || '?'}</strong>
                                        </span>
                                    );
                                })}
                            </div>
                        </div>

                        {/* 8 Segment Dropdowns */}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {segmentMeta.map((meta) => {
                                const key = meta.value;
                                const list = segments[key] ?? [];
                                const currentValue = form.data[key];
                                const isDistrictLocked =
                                    key === 'segment_2' &&
                                    !isFinanceEditing &&
                                    list.length <= 1;

                                const hasCurrentInList = list.some(
                                    (opt) => opt.code === currentValue,
                                );

                                return (
                                    <div key={key} className="grid gap-1.5">
                                        <Label htmlFor={`coa-${key}`}>
                                            {meta.label}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>

                                        {isDistrictLocked ? (
                                            <Input
                                                id={`coa-${key}`}
                                                value={`${defaultSegment2.code} — ${defaultSegment2.name}`}
                                                readOnly
                                                className="bg-muted/60 font-medium text-muted-foreground"
                                            />
                                        ) : (
                                            <Select
                                                value={currentValue}
                                                onValueChange={(value) =>
                                                    form.setData(key, value)
                                                }
                                            >
                                                <SelectTrigger
                                                    id={`coa-${key}`}
                                                    className="w-full"
                                                >
                                                    <SelectValue
                                                        placeholder={`Pilih ${meta.shortLabel}`}
                                                    />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-72">
                                                    {currentValue !== '' &&
                                                        !hasCurrentInList && (
                                                            <SelectItem
                                                                value={
                                                                    currentValue
                                                                }
                                                            >
                                                                <span className="font-mono font-semibold">
                                                                    {
                                                                        currentValue
                                                                    }
                                                                </span>
                                                            </SelectItem>
                                                        )}
                                                    {list.map((opt) => (
                                                        <SelectItem
                                                            key={opt.code}
                                                            value={opt.code}
                                                        >
                                                            <span className="font-mono font-semibold">
                                                                {opt.code}
                                                            </span>
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                — {opt.name}
                                                            </span>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}

                                        <InputError
                                            message={form.errors[key]}
                                        />
                                    </div>
                                );
                            })}
                        </div>

                        {/* Optional manual COA code override for Keuangan */}
                        {isFinanceEditing && (
                            <div className="space-y-2 rounded-md border border-border bg-muted/20 p-3">
                                <label className="flex items-center gap-2 text-xs font-medium text-foreground">
                                    <input
                                        type="checkbox"
                                        checked={customCodeMode}
                                        onChange={(event) => {
                                            const checked =
                                                event.target.checked;
                                            setCustomCodeMode(checked);

                                            if (checked && !form.data.coa_code) {
                                                form.setData(
                                                    'coa_code',
                                                    previewCode,
                                                );
                                            }
                                        }}
                                        className="size-4 rounded border-border"
                                    />
                                    Sesuaikan kode COA final secara manual
                                    (opsional, khusus Keuangan)
                                </label>
                                {customCodeMode && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="coa-manual-code">
                                            Kode COA Final
                                        </Label>
                                        <Input
                                            id="coa-manual-code"
                                            value={form.data.coa_code}
                                            onChange={(event) =>
                                                form.setData(
                                                    'coa_code',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            className="font-mono"
                                            placeholder={previewCode}
                                        />
                                        <InputError
                                            message={form.errors.coa_code}
                                        />
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="grid gap-1.5">
                            <Label htmlFor="coa-description">
                                Keterangan / Keperluan Pekerjaan
                            </Label>
                            <Input
                                id="coa-description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Contoh: Pemeliharaan Water Way System PLTMH Mikuasi"
                            />
                            <InputError message={form.errors.description} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="coa-notes">
                                Catatan{' '}
                                <span className="font-normal text-muted-foreground">
                                    (opsional)
                                </span>
                            </Label>
                            <Textarea
                                id="coa-notes"
                                rows={2}
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                placeholder={
                                    isFinanceEditing
                                        ? 'Catatan perbaikan atau aktivasi dari bagian Keuangan'
                                        : 'Catatan tambahan untuk bagian Keuangan'
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <DialogFooter className="gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                disabled={form.processing}
                            >
                                Batal
                            </Button>

                            {isFinanceEditing ? (
                                <>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={form.processing}
                                        onClick={() => submitWithAction('save')}
                                    >
                                        Simpan Perubahan
                                    </Button>
                                    {editing.status !== 'aktif' && (
                                        <Button
                                            type="button"
                                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                                            disabled={form.processing}
                                            onClick={() =>
                                                submitWithAction('activate')
                                            }
                                        >
                                            <CheckCircle2 className="size-4" />
                                            Simpan & Aktivasi
                                        </Button>
                                    )}
                                </>
                            ) : (
                                <>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={form.processing}
                                        onClick={() => submitWithAction('save')}
                                    >
                                        Simpan
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={form.processing}
                                        onClick={() =>
                                            submitWithAction(
                                                'request_activation',
                                            )
                                        }
                                    >
                                        <Send className="size-4" />
                                        Request Aktivasi
                                    </Button>
                                </>
                            )}
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Detail Dialog */}
            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setViewing(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-xl">
                    {viewing && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="font-mono">
                                    {viewing.coa_code}
                                </DialogTitle>
                                <DialogDescription>
                                    Rincian komposisi 8 segmen COA dan riwayat
                                    status.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-3 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-muted/30 px-3 py-2">
                                    <span className="text-xs text-muted-foreground">
                                        Status saat ini
                                    </span>
                                    <span
                                        className={cn(
                                            'inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                            STATUS_BADGE[viewing.status],
                                        )}
                                    >
                                        {viewing.status_label}
                                    </span>
                                </div>

                                <dl className="divide-y divide-border rounded-md border border-border">
                                    {segmentMeta.map((meta) => (
                                        <div
                                            key={meta.value}
                                            className="grid grid-cols-12 gap-2 px-3 py-2"
                                        >
                                            <dt className="col-span-5 text-xs text-muted-foreground">
                                                {meta.label}
                                            </dt>
                                            <dd className="col-span-7 flex items-center gap-2">
                                                <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-semibold">
                                                    {viewing[meta.value]}
                                                </span>
                                                <span className="text-xs text-foreground">
                                                    {
                                                        viewing.segment_labels[
                                                            meta.value
                                                        ]
                                                    }
                                                </span>
                                            </dd>
                                        </div>
                                    ))}
                                </dl>

                                {viewing.description && (
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Keperluan
                                        </p>
                                        <p className="mt-0.5 text-sm text-foreground">
                                            {viewing.description}
                                        </p>
                                    </div>
                                )}

                                {viewing.notes && (
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Catatan
                                        </p>
                                        <p className="mt-0.5 text-sm text-foreground">
                                            {viewing.notes}
                                        </p>
                                    </div>
                                )}

                                <div className="grid grid-cols-2 gap-2 border-t border-border pt-2 text-xs text-muted-foreground">
                                    <div>
                                        <span className="block font-medium text-foreground">
                                            Pengusul
                                        </span>
                                        {viewing.requester?.name ?? '—'}
                                        {viewing.work_director
                                            ? ` (${viewing.work_director})`
                                            : ''}
                                    </div>
                                    <div>
                                        <span className="block font-medium text-foreground">
                                            Aktivasi Keuangan
                                        </span>
                                        {viewing.activator
                                            ? `${viewing.activator} · ${viewing.activated_at ? formatDateTime(viewing.activated_at) : ''}`
                                            : 'Belum diaktifkan'}
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

CoaRequestsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Request COA', href: coaRequests.index() },
    ],
};
