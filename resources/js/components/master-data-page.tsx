import { Head, router, useForm } from '@inertiajs/react';
import { Database, Pencil, Plus, Power } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { EnumOption } from '@/types';

export type MasterRecord = {
    id: number;
    is_active: boolean;
    usage_count?: number;
    [key: string]: unknown;
};

export type MasterFormValue = string | number | boolean | null | number[];

export type MasterFormValues = Record<string, MasterFormValue>;

export type MasterField = {
    name: string;
    label: string;
    type: 'text' | 'number' | 'select' | 'switch' | 'multiselect';
    options?: EnumOption[];
    placeholder?: string;
    hint?: string;
    required?: boolean;
};

export type MasterColumn<T extends MasterRecord> = {
    key: string;
    label: string;
    className?: string;
    render?: (record: T) => ReactNode;
};

export function MasterDataPage<T extends MasterRecord>({
    title,
    eyebrow = 'Data Master',
    description,
    addLabel,
    records,
    columns,
    fields,
    defaults,
    storeUrl,
    updateUrl,
    destroyUrl,
    nameKey,
}: {
    title: string;
    eyebrow?: string;
    description: string;
    addLabel: string;
    records: T[];
    columns: MasterColumn<T>[];
    fields: MasterField[];
    defaults: MasterFormValues;
    storeUrl: string;
    updateUrl: (record: T) => string;
    destroyUrl: (record: T) => string;
    nameKey: string;
}) {
    const [editing, setEditing] = useState<T | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm<MasterFormValues>({ ...defaults });

    const openCreate = () => {
        setEditing(null);
        form.setDefaults({ ...defaults });
        form.reset();
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (record: T) => {
        const values: MasterFormValues = {};
        Object.keys(defaults).forEach((key) => {
            values[key] = (record[key] as MasterFormValue) ?? defaults[key];
        });

        setEditing(record);
        form.setDefaults(values);
        form.setData(values);
        form.clearErrors();
        setOpen(true);
    };

    const submit = () => {
        if (editing === null) {
            form.post(storeUrl, {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.put(updateUrl(editing), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <Head title={title} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    actions={
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            {addLabel}
                        </Button>
                    }
                />

                {records.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Database}
                            title="Belum ada data"
                            description="Tambahkan data master pertama agar dapat dipilih pada form pengadaan."
                            action={
                                <Button onClick={openCreate} size="sm">
                                    {addLabel}
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    {columns.map((column) => (
                                        <TableHead
                                            key={column.key}
                                            className={column.className}
                                        >
                                            {column.label}
                                        </TableHead>
                                    ))}
                                    <TableHead>Dipakai</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {records.map((record) => (
                                    <TableRow key={record.id}>
                                        {columns.map((column) => (
                                            <TableCell
                                                key={column.key}
                                                className={column.className}
                                            >
                                                {column.render
                                                    ? column.render(record)
                                                    : String(
                                                          record[column.key] ??
                                                              '—',
                                                      )}
                                            </TableCell>
                                        ))}

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
                                                onClick={() => openEdit(record)}
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
                                                            `Nonaktifkan "${String(record[nameKey])}"? Data pengadaan lama tetap utuh.`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            destroyUrl(record),
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

                <p className="text-xs text-muted-foreground">
                    Data master yang sudah dipakai pada pengadaan tidak dihapus
                    permanen, melainkan dinonaktifkan agar riwayat pengadaan
                    lama tetap utuh.
                </p>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing === null ? addLabel : `Ubah ${title}`}
                        </DialogTitle>
                        <DialogDescription>
                            Perubahan berlaku langsung tanpa perlu deploy ulang
                            aplikasi.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        id="master-data-form"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                        className="space-y-4"
                    >
                        {fields.map((field) => (
                            <FieldControl
                                key={field.name}
                                field={field}
                                value={form.data[field.name]}
                                error={
                                    form.errors[
                                        field.name as keyof typeof form.errors
                                    ] as string | undefined
                                }
                                onChange={(value) =>
                                    form.setData(field.name, value)
                                }
                            />
                        ))}
                    </form>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="master-data-form"
                            disabled={form.processing}
                        >
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function FieldControl({
    field,
    value,
    error,
    onChange,
}: {
    field: MasterField;
    value: MasterFormValue;
    error?: string;
    onChange: (value: MasterFormValue) => void;
}) {
    if (field.type === 'switch') {
        return (
            <div className="flex items-center justify-between gap-4 rounded-md border border-border px-3 py-2.5">
                <div className="space-y-0.5">
                    <Label htmlFor={field.name}>{field.label}</Label>
                    {field.hint && (
                        <p className="text-xs text-muted-foreground">
                            {field.hint}
                        </p>
                    )}
                </div>
                <Switch
                    id={field.name}
                    checked={Boolean(value)}
                    onCheckedChange={(checked) => onChange(checked)}
                />
            </div>
        );
    }

    if (field.type === 'multiselect') {
        const selected = Array.isArray(value) ? value : [];

        return (
            <div className="grid gap-2">
                <Label>{field.label}</Label>
                <div className="grid gap-1.5 rounded-md border border-border px-3 py-2.5">
                    {(field.options ?? []).map((option) => {
                        const id = Number(option.value);
                        const checked = selected.includes(id);

                        return (
                            <label
                                key={option.value}
                                className="flex cursor-pointer items-center gap-2.5 text-sm"
                            >
                                <Checkbox
                                    checked={checked}
                                    onCheckedChange={(next) =>
                                        onChange(
                                            next === true
                                                ? [...selected, id]
                                                : selected.filter(
                                                      (item) => item !== id,
                                                  ),
                                        )
                                    }
                                />
                                {option.label}
                            </label>
                        );
                    })}
                    {(field.options ?? []).length === 0 && (
                        <p className="text-xs text-muted-foreground">
                            Belum ada pilihan tersedia.
                        </p>
                    )}
                </div>
                {field.hint && (
                    <p className="text-xs text-muted-foreground">
                        {field.hint}
                    </p>
                )}
                <InputError message={error} />
            </div>
        );
    }

    if (field.type === 'select') {
        return (
            <div className="grid gap-2">
                <Label htmlFor={field.name}>{field.label}</Label>
                <Select
                    value={value === null ? undefined : String(value)}
                    onValueChange={(next) => onChange(next)}
                >
                    <SelectTrigger id={field.name} className="w-full">
                        <SelectValue placeholder={field.placeholder} />
                    </SelectTrigger>
                    <SelectContent>
                        {(field.options ?? []).map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {field.hint && (
                    <p className="text-xs text-muted-foreground">
                        {field.hint}
                    </p>
                )}
                <InputError message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={field.name}>{field.label}</Label>
            <Input
                id={field.name}
                type={field.type === 'number' ? 'number' : 'text'}
                value={
                    value === null || value === undefined ? '' : String(value)
                }
                placeholder={field.placeholder}
                required={field.required}
                autoComplete="off"
                onChange={(event) =>
                    onChange(
                        field.type === 'number'
                            ? Number(event.target.value)
                            : event.target.value,
                    )
                }
            />
            {field.hint && (
                <p className="text-xs text-muted-foreground">{field.hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
