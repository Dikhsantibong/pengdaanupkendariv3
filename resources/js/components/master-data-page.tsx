import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Database,
    Pencil,
    Plus,
    Power,
    X,
} from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types';

export type MasterRecord = {
    id: number;
    is_active: boolean;
    usage_count?: number;
    [key: string]: unknown;
};

export type MasterFormValue =
    string | number | boolean | null | number[] | string[];

export type MasterFormValues = Record<string, MasterFormValue>;

export type MasterField = {
    name: string;
    label: string;
    type:
        | 'text'
        | 'textarea'
        | 'number'
        | 'select'
        | 'switch'
        | 'multiselect'
        | 'list';
    options?: EnumOption[];
    placeholder?: string;
    hint?: string;
    required?: boolean;
    /** The label of the button that adds a row to a `list` field. */
    addLabel?: string;
    /**
     * A date typed beside each row of a `list` field, kept row by row in
     * another field of the form (blank for none).
     */
    rowDate?: { name: string; label: string };
    /** Shows the field only while the form's values call for it. */
    visible?: (values: MasterFormValues) => boolean;
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
    toolbar,
    filtered = false,
}: {
    /** Filters shown above the table, for pages that offer them. */
    toolbar?: ReactNode;
    /** The records are a filtered subset, so an empty list means no match. */
    filtered?: boolean;
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

    // The dates a list field keeps beside its rows, in their own field.
    const rowDatesOf = (field: MasterField): RowDates | undefined => {
        const rowDate = field.rowDate;

        if (rowDate === undefined) {
            return undefined;
        }

        return {
            value: form.data[rowDate.name],
            onChange: (value) => form.setData(rowDate.name, value),
        };
    };

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

                {toolbar}

                {records.length === 0 && filtered ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Database}
                            title="Tidak ada data yang cocok"
                            description="Ubah atau reset filter untuk melihat data lainnya."
                        />
                    </div>
                ) : records.length === 0 ? (
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
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
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
                        className="grid grid-cols-1 items-start gap-4 md:grid-cols-2"
                    >
                        {fields
                            .filter(
                                (field) =>
                                    field.visible === undefined ||
                                    field.visible(form.data),
                            )
                            .map((field) => (
                                <div
                                    key={field.name}
                                    className={
                                        field.type === 'list' ||
                                        field.type === 'multiselect' ||
                                        field.type === 'textarea'
                                            ? 'md:col-span-2'
                                            : ''
                                    }
                                >
                                    <FieldControl
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
                                        rowDates={rowDatesOf(field)}
                                    />
                                </div>
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

/**
 * A field holding an ordered list of short lines.
 *
 * The indicators of an aspect print as a, b, c on the official form, so the
 * order matters and is edited here rather than typed as one blob of text.
 */
/** The dates kept beside a list's rows, in another field of the form. */
type RowDates = {
    value: MasterFormValue;
    onChange: (value: MasterFormValue) => void;
};

function ListControl({
    field,
    value,
    error,
    onChange,
    rowDates,
}: {
    field: MasterField;
    value: MasterFormValue;
    error?: string;
    onChange: (value: MasterFormValue) => void;
    rowDates?: RowDates;
}) {
    const lines = (Array.isArray(value) ? value : []).map(String);
    const letters = 'abcdefghijklmnopqrstuvwxyz';
    // One date per row, so the dates follow their rows when moved or removed.
    const storedDates = Array.isArray(rowDates?.value) ? rowDates.value : [];
    const dates = lines.map((_, at) => String(storedDates[at] ?? ''));

    const replace = (index: number, line: string) =>
        onChange(lines.map((item, at) => (at === index ? line : item)));

    const replaceDate = (index: number, date: string) =>
        rowDates?.onChange(
            dates.map((item, at) => (at === index ? date : item)),
        );

    const remove = (index: number) => {
        onChange(lines.filter((_, at) => at !== index));
        rowDates?.onChange(dates.filter((_, at) => at !== index));
    };

    const move = (index: number, by: number) => {
        const target = index + by;

        if (target < 0 || target >= lines.length) {
            return;
        }

        const next = [...lines];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);

        const nextDates = [...dates];
        [nextDates[index], nextDates[target]] = [
            nextDates[target],
            nextDates[index],
        ];
        rowDates?.onChange(nextDates);
    };

    const add = () => {
        onChange([...lines, '']);
        rowDates?.onChange([...dates, '']);
    };

    return (
        <div className="grid gap-2">
            <Label>{field.label}</Label>

            <div className="grid gap-1.5">
                {field.rowDate && lines.length > 0 && (
                    <div className="hidden items-center gap-1.5 text-[11px] text-muted-foreground sm:flex">
                        <span className="w-4 shrink-0" />
                        <span className="flex-1">Nama</span>
                        <span className="w-36 shrink-0">
                            {field.rowDate.label}
                        </span>
                        <span className="w-[5.625rem] shrink-0" />
                    </div>
                )}
                {lines.map((line, index) => (
                    <div
                        key={index}
                        className={cn(
                            'flex items-center gap-1.5',
                            field.rowDate && 'flex-wrap sm:flex-nowrap',
                        )}
                    >
                        <span className="w-4 shrink-0 text-xs text-muted-foreground">
                            {letters[index] ?? '-'}.
                        </span>
                        <Input
                            value={line}
                            placeholder={field.placeholder}
                            onChange={(event) =>
                                replace(index, event.target.value)
                            }
                            className={cn(
                                'h-8 flex-1',
                                // A phone puts the date on the line below.
                                field.rowDate &&
                                    'basis-[calc(100%-1.375rem)] sm:basis-0',
                            )}
                            autoComplete="off"
                        />
                        {field.rowDate && (
                            <Input
                                type="date"
                                value={dates[index]}
                                title={field.rowDate.label}
                                aria-label={`${field.rowDate.label} ${line}`}
                                onChange={(event) =>
                                    replaceDate(index, event.target.value)
                                }
                                className="ml-[1.375rem] h-8 w-36 shrink-0 sm:ml-0"
                            />
                        )}
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7 shrink-0"
                            disabled={index === 0}
                            aria-label="Naikkan"
                            onClick={() => move(index, -1)}
                        >
                            <ChevronUp className="size-3.5" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7 shrink-0"
                            disabled={index === lines.length - 1}
                            aria-label="Turunkan"
                            onClick={() => move(index, 1)}
                        >
                            <ChevronDown className="size-3.5" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7 shrink-0 text-destructive hover:text-destructive"
                            aria-label="Hapus baris"
                            onClick={() => remove(index)}
                        >
                            <X className="size-3.5" />
                        </Button>
                    </div>
                ))}

                {lines.length === 0 && (
                    <p className="rounded-md border border-dashed border-border px-3 py-2.5 text-xs text-muted-foreground">
                        Belum ada baris.
                    </p>
                )}
            </div>

            <Button
                type="button"
                size="sm"
                variant="outline"
                className="justify-self-start"
                onClick={add}
            >
                <Plus className="size-3.5" />
                {field.addLabel ?? 'Tambah Baris'}
            </Button>

            {field.hint && (
                <p className="text-xs text-muted-foreground">{field.hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

function FieldControl({
    field,
    value,
    error,
    onChange,
    rowDates,
}: {
    field: MasterField;
    value: MasterFormValue;
    error?: string;
    onChange: (value: MasterFormValue) => void;
    rowDates?: RowDates;
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

    if (field.type === 'list') {
        return (
            <ListControl
                field={field}
                value={value}
                error={error}
                onChange={onChange}
                rowDates={rowDates}
            />
        );
    }

    if (field.type === 'multiselect') {
        const selected = (Array.isArray(value) ? value : []).map(Number);

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

    if (field.type === 'textarea') {
        return (
            <div className="grid gap-2">
                <Label htmlFor={field.name}>{field.label}</Label>
                <Textarea
                    id={field.name}
                    rows={3}
                    value={
                        value === null || value === undefined
                            ? ''
                            : String(value)
                    }
                    placeholder={field.placeholder}
                    required={field.required}
                    onChange={(event) => onChange(event.target.value)}
                />
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
