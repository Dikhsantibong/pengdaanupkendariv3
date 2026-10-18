import { router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    FilePlus2,
    FileText,
    Paperclip,
    Plus,
    RotateCcw,
    Send,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';

export type ContractItem = {
    id: number;
    group_name: string | null;
    description: string;
    part_number: string | null;
    unit: string;
    unit_price: number;
};

type OrderLine = {
    contract_item_id: number | null;
    group_name: string | null;
    description: string;
    part_number: string | null;
    quantity: number;
    unit: string;
    unit_price: number;
    total: number;
};

export type DeliveryOrder = {
    id: number;
    sequence: number;
    state: string;
    state_label: string;
    request_memo_number: string | null;
    request_memo_date: string | null;
    request_file: { name: string; url: string } | null;
    do_memo_number: string | null;
    do_memo_date: string | null;
    number: string | null;
    order_date: string | null;
    contract_date: string | null;
    vendor_code: string | null;
    work_description: string | null;
    delivery_place: string | null;
    delivery_days: number | null;
    created_by: string | null;
    review_note: string | null;
    revision: number;
    total: number;
    items: OrderLine[];
    steps: Array<{ label: string; by: string | null; at: string }>;
    document: {
        id: number;
        title: string;
        edit_url: string;
        preview_url: string;
        download_url: string;
    } | null;
    missing: string[];
    can: {
        request: boolean;
        decide: boolean;
        forward: boolean;
        issue: boolean;
    };
};

export type DeliveryOrders = {
    available: boolean;
    contract_items: ContractItem[];
    orders: DeliveryOrder[];
};

const STATE_TONE: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    dikembalikan: 'bg-destructive/10 text-destructive',
    terbit: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
};

/**
 * The DO tab of a KHS contract: the contract's price list, and each Delivery
 * Order from the proposer's request (approved by their TL and Asman),
 * through Rendan's Nota Dinas DO, to Laksdan issuing it.
 */
export function DeliveryOrderTab({
    procurementId,
    data,
    canStart,
    canManageItems,
}: {
    procurementId: number;
    data: DeliveryOrders;
    canStart: boolean;
    canManageItems: boolean;
}) {
    const [starting, setStarting] = useState(false);

    return (
        <div className="space-y-4">
            <section className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-4">
                <div className="text-sm">
                    <p className="font-semibold">Delivery Order (DO)</p>
                    <p className="text-xs text-muted-foreground">
                        Kontrak KHS dipesan bertahap lewat DO. Alur: pengusul
                        mengajukan (nota dinas usulan + item) → disetujui TL dan
                        Asman → Rendan membuat Nota Dinas DO → Laksdan
                        menerbitkan DO (nomor otomatis berurutan untuk seluruh
                        kontrak KHS).
                    </p>
                </div>
                {canStart && (
                    <Button
                        disabled={starting || data.contract_items.length === 0}
                        title={
                            data.contract_items.length === 0
                                ? 'Daftar harga kontrak belum diisi Laksdan'
                                : undefined
                        }
                        onClick={() =>
                            router.post(
                                procurements.deliveryOrders.store(procurementId)
                                    .url,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setStarting(true),
                                    onFinish: () => setStarting(false),
                                },
                            )
                        }
                    >
                        <FilePlus2 className="size-4" />
                        Ajukan DO
                    </Button>
                )}
            </section>

            {!data.available && (
                <p className="rounded-md border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">
                    DO dapat diajukan setelah dokumen kontrak (SPK / Surat
                    Perjanjian) dibuat.
                </p>
            )}

            <ContractItemsSection
                procurementId={procurementId}
                items={data.contract_items}
                editable={canManageItems}
            />

            {data.orders.length === 0 ? (
                <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                    Belum ada Delivery Order.
                </p>
            ) : (
                [...data.orders]
                    .reverse()
                    .map((order) => (
                        <OrderCard
                            key={order.id}
                            procurementId={procurementId}
                            order={order}
                            contractItems={data.contract_items}
                        />
                    ))
            )}
        </div>
    );
}

type ContractItemRow = {
    id: number | null;
    group_name: string;
    description: string;
    part_number: string;
    unit: string;
    unit_price: string;
};

/** The KHS contract's price list, kept by Laksdan. */
function ContractItemsSection({
    procurementId,
    items,
    editable,
}: {
    procurementId: number;
    items: ContractItem[];
    editable: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm<{ items: ContractItemRow[] }>({
        items: items.map((item) => ({
            id: item.id,
            group_name: item.group_name ?? '',
            description: item.description,
            part_number: item.part_number ?? '',
            unit: item.unit,
            unit_price: String(item.unit_price),
        })),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const set = (index: number, key: keyof ContractItemRow, value: string) =>
        form.setData(
            'items',
            form.data.items.map((row, at) =>
                at === index ? { ...row, [key]: value } : row,
            ),
        );

    return (
        <section className="space-y-3 rounded-md border border-border bg-card p-4">
            <header className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold">
                        Daftar Harga Satuan Kontrak
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Material / jasa yang tercantum di lampiran kontrak KHS.
                        Setiap DO menarik item dari daftar ini.
                    </p>
                </div>
                {editable && !editing && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setEditing(true)}
                    >
                        Ubah Daftar
                    </Button>
                )}
            </header>

            {editing ? (
                <form
                    className="space-y-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(
                            procurements.deliveryOrders.contractItems(
                                procurementId,
                            ).url,
                            {
                                preserveScroll: true,
                                onSuccess: () => setEditing(false),
                            },
                        );
                    }}
                >
                    {form.data.items.map((row, index) => (
                        <div
                            key={index}
                            className="grid items-start gap-2 rounded-sm border border-border p-2 md:grid-cols-[1.2fr_2fr_1fr_0.7fr_1fr_auto]"
                        >
                            <Input
                                placeholder="Kelompok (opsional)"
                                value={row.group_name}
                                onChange={(event) =>
                                    set(index, 'group_name', event.target.value)
                                }
                            />
                            <div>
                                <Input
                                    placeholder="Uraian"
                                    value={row.description}
                                    onChange={(event) =>
                                        set(
                                            index,
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`items.${index}.description`]
                                    }
                                />
                            </div>
                            <Input
                                placeholder="Part number"
                                value={row.part_number}
                                onChange={(event) =>
                                    set(
                                        index,
                                        'part_number',
                                        event.target.value,
                                    )
                                }
                            />
                            <div>
                                <Input
                                    placeholder="Satuan"
                                    value={row.unit}
                                    onChange={(event) =>
                                        set(index, 'unit', event.target.value)
                                    }
                                />
                                <InputError
                                    message={errors[`items.${index}.unit`]}
                                />
                            </div>
                            <div>
                                <Input
                                    type="number"
                                    min={0}
                                    placeholder="Harga satuan"
                                    className="tabular"
                                    value={row.unit_price}
                                    onChange={(event) =>
                                        set(
                                            index,
                                            'unit_price',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`items.${index}.unit_price`]
                                    }
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Hapus item"
                                onClick={() =>
                                    form.setData(
                                        'items',
                                        form.data.items.filter(
                                            (_, at) => at !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                form.setData('items', [
                                    ...form.data.items,
                                    {
                                        id: null,
                                        group_name:
                                            form.data.items.at(-1)
                                                ?.group_name ?? '',
                                        description: '',
                                        part_number: '',
                                        unit: '',
                                        unit_price: '',
                                    },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Tambah Item
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            Simpan Daftar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                form.reset();
                                setEditing(false);
                            }}
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            ) : items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Daftar harga kontrak belum diisi.
                </p>
            ) : (
                <ItemTable
                    rows={items.map((item) => ({
                        group_name: item.group_name,
                        description: item.description,
                        part_number: item.part_number,
                        unit: item.unit,
                        unit_price: item.unit_price,
                    }))}
                />
            )}
        </section>
    );
}

/** A read-only list of lines, grouped under their headings. */
function ItemTable({
    rows,
}: {
    rows: Array<{
        group_name: string | null;
        description: string;
        part_number: string | null;
        unit: string;
        unit_price: number;
        quantity?: number;
        total?: number;
    }>;
}) {
    const withQuantity = rows.some((row) => row.quantity !== undefined);

    return (
        <div className="overflow-x-auto rounded-sm border border-border">
            <table className="w-full text-xs">
                <thead className="bg-muted/60 text-left">
                    <tr>
                        <th className="px-2 py-1.5">Kelompok</th>
                        <th className="px-2 py-1.5">Uraian</th>
                        <th className="px-2 py-1.5">Part Number</th>
                        {withQuantity && (
                            <th className="px-2 py-1.5 text-right">Volume</th>
                        )}
                        <th className="px-2 py-1.5">Satuan</th>
                        <th className="px-2 py-1.5 text-right">Harga Satuan</th>
                        {withQuantity && (
                            <th className="px-2 py-1.5 text-right">Jumlah</th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.map((row, index) => (
                        <tr key={index}>
                            <td className="px-2 py-1.5 text-muted-foreground">
                                {row.group_name ?? '—'}
                            </td>
                            <td className="px-2 py-1.5">{row.description}</td>
                            <td className="px-2 py-1.5">
                                {row.part_number ?? '—'}
                            </td>
                            {withQuantity && (
                                <td className="tabular px-2 py-1.5 text-right">
                                    {row.quantity}
                                </td>
                            )}
                            <td className="px-2 py-1.5">{row.unit}</td>
                            <td className="tabular px-2 py-1.5 text-right">
                                {formatCurrency(row.unit_price)}
                            </td>
                            {withQuantity && (
                                <td className="tabular px-2 py-1.5 text-right">
                                    {formatCurrency(row.total ?? 0)}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function OrderCard({
    procurementId,
    order,
    contractItems,
}: {
    procurementId: number;
    order: DeliveryOrder;
    contractItems: ContractItem[];
}) {
    const args = { procurement: procurementId, order: order.id };

    return (
        <section className="space-y-4 rounded-md border border-border bg-card p-4">
            <header className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold">
                        DO ke-{order.sequence}
                        {order.number ? ` · ${order.number}` : ''}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {order.created_by
                            ? `Diajukan oleh ${order.created_by}`
                            : ''}
                        {order.total > 0
                            ? ` · Total ${formatCurrency(order.total)}`
                            : ''}
                    </p>
                </div>
                <span
                    className={`rounded-sm px-2 py-0.5 text-xs font-medium ${
                        STATE_TONE[order.state] ??
                        'bg-amber-500/10 text-amber-800 dark:text-amber-300'
                    }`}
                >
                    {order.state_label}
                    {order.revision > 0 ? ` · revisi ke-${order.revision}` : ''}
                </span>
            </header>

            {order.state === 'dikembalikan' && order.review_note && (
                <p className="flex items-start gap-1.5 rounded-sm border border-destructive/40 bg-destructive/5 p-2.5 text-xs">
                    <RotateCcw className="mt-0.5 size-3.5 shrink-0 text-destructive" />
                    <span>
                        <b>Yang harus diperbaiki:</b> {order.review_note}
                    </span>
                </p>
            )}

            {order.can.request ? (
                <RequestForm
                    procurementId={procurementId}
                    order={order}
                    contractItems={contractItems}
                />
            ) : (
                <Summary order={order} />
            )}

            {order.can.request && (
                <RequestFile procurementId={procurementId} order={order} />
            )}

            {order.can.forward && (
                <MemoForm procurementId={procurementId} order={order} />
            )}

            {order.can.issue && (
                <IssueForm
                    procurementId={procurementId}
                    order={order}
                    contractItems={contractItems}
                />
            )}

            {order.document && (
                <div className="flex flex-wrap items-center gap-3 rounded-sm border border-border bg-muted/40 p-2.5 text-xs">
                    <FileText className="size-4 text-primary" />
                    <span className="font-medium">{order.document.title}</span>
                    <a
                        href={order.document.preview_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-primary hover:underline"
                    >
                        Pratinjau
                    </a>
                    <a
                        href={order.document.download_url}
                        className="text-primary hover:underline"
                    >
                        Unduh PDF
                    </a>
                    <a
                        href={order.document.edit_url}
                        className="text-primary hover:underline"
                    >
                        Buka di editor
                    </a>
                </div>
            )}

            {order.steps.length > 0 && (
                <ul className="space-y-1 text-xs text-muted-foreground">
                    {order.steps.map((step) => (
                        <li
                            key={step.label}
                            className="flex items-center gap-1.5"
                        >
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            {step.label}
                            {step.by ? ` (${step.by})` : ''} ·{' '}
                            {formatDateTime(step.at)}
                        </li>
                    ))}
                </ul>
            )}

            {order.can.request && (
                <ActionBar
                    missing={order.missing}
                    label={
                        order.state === 'dikembalikan'
                            ? 'Ajukan Ulang DO'
                            : 'Ajukan untuk Persetujuan'
                    }
                    url={procurements.deliveryOrders.submit(args).url}
                />
            )}

            {order.can.forward && (
                <ActionBar
                    missing={order.missing}
                    label="Teruskan ke Laksdan"
                    url={procurements.deliveryOrders.forward(args).url}
                />
            )}

            {order.can.issue && (
                <ActionBar
                    missing={order.missing}
                    label="Terbitkan DO"
                    url={procurements.deliveryOrders.issue(args).url}
                />
            )}

            {order.can.decide && (
                <DecisionForm procurementId={procurementId} order={order} />
            )}
        </section>
    );
}

type Line = { contract_item_id: string; quantity: string };

/** Pick lines from the price list and say how many. */
function LinePicker({
    lines,
    onChange,
    contractItems,
    errors,
}: {
    lines: Line[];
    onChange: (lines: Line[]) => void;
    contractItems: ContractItem[];
    errors: Record<string, string | undefined>;
}) {
    const priceOf = (id: string) =>
        contractItems.find((item) => String(item.id) === id)?.unit_price ?? 0;
    const total = lines.reduce(
        (sum, line) =>
            sum + priceOf(line.contract_item_id) * (Number(line.quantity) || 0),
        0,
    );

    return (
        <div className="space-y-2">
            <p className="text-sm font-semibold">Item DO</p>
            {lines.map((line, index) => (
                <div
                    key={index}
                    className="grid items-start gap-2 md:grid-cols-[3fr_1fr_1fr_auto]"
                >
                    <div>
                        <Select
                            value={line.contract_item_id}
                            onValueChange={(value) =>
                                onChange(
                                    lines.map((row, at) =>
                                        at === index
                                            ? {
                                                  ...row,
                                                  contract_item_id: value,
                                              }
                                            : row,
                                    ),
                                )
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pilih item dari daftar harga kontrak" />
                            </SelectTrigger>
                            <SelectContent>
                                {contractItems.map((item) => (
                                    <SelectItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.group_name
                                            ? `${item.group_name} · `
                                            : ''}
                                        {item.description}
                                        {item.part_number
                                            ? ` (${item.part_number})`
                                            : ''}{' '}
                                        — {formatCurrency(item.unit_price)}/
                                        {item.unit}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors[`items.${index}.contract_item_id`]}
                        />
                    </div>
                    <div>
                        <Input
                            type="number"
                            min={0}
                            step="any"
                            placeholder="Volume"
                            className="tabular"
                            value={line.quantity}
                            onChange={(event) =>
                                onChange(
                                    lines.map((row, at) =>
                                        at === index
                                            ? {
                                                  ...row,
                                                  quantity: event.target.value,
                                              }
                                            : row,
                                    ),
                                )
                            }
                        />
                        <InputError
                            message={errors[`items.${index}.quantity`]}
                        />
                    </div>
                    <p className="tabular pt-2 text-right text-sm">
                        {formatCurrency(
                            priceOf(line.contract_item_id) *
                                (Number(line.quantity) || 0),
                        )}
                    </p>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Hapus item"
                        onClick={() =>
                            onChange(lines.filter((_, at) => at !== index))
                        }
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ))}
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={contractItems.length === 0}
                    onClick={() =>
                        onChange([
                            ...lines,
                            { contract_item_id: '', quantity: '1' },
                        ])
                    }
                >
                    <Plus className="size-4" />
                    Tambah Item
                </Button>
                <p className="tabular text-sm font-semibold">
                    Total {formatCurrency(total)}
                </p>
            </div>
            <InputError message={errors.items} />
        </div>
    );
}

const toLines = (order: DeliveryOrder): Line[] =>
    order.items.map((item) => ({
        contract_item_id:
            item.contract_item_id === null ? '' : String(item.contract_item_id),
        quantity: String(item.quantity),
    }));

/** The proposer's request: the Nota Dinas usulan and the lines. */
function RequestForm({
    procurementId,
    order,
    contractItems,
}: {
    procurementId: number;
    order: DeliveryOrder;
    contractItems: ContractItem[];
}) {
    const form = useForm<{
        request_memo_number: string;
        request_memo_date: string;
        items: Line[];
    }>({
        request_memo_number: order.request_memo_number ?? '',
        request_memo_date: order.request_memo_date ?? '',
        items: toLines(order),
    });
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.deliveryOrders.request({
                        procurement: procurementId,
                        order: order.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <div className="grid gap-3 md:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`do-${order.id}-request-number`}>
                        Nomor Nota Dinas Usulan
                    </Label>
                    <Input
                        id={`do-${order.id}-request-number`}
                        value={form.data.request_memo_number}
                        onChange={(event) =>
                            form.setData(
                                'request_memo_number',
                                event.target.value,
                            )
                        }
                    />
                    <InputError message={errors.request_memo_number} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`do-${order.id}-request-date`}>
                        Tanggal Nota Dinas Usulan
                    </Label>
                    <Input
                        id={`do-${order.id}-request-date`}
                        type="date"
                        className="tabular"
                        value={form.data.request_memo_date}
                        onChange={(event) =>
                            form.setData(
                                'request_memo_date',
                                event.target.value,
                            )
                        }
                    />
                    <InputError message={errors.request_memo_date} />
                </div>
            </div>

            <LinePicker
                lines={form.data.items}
                onChange={(lines) => form.setData('items', lines)}
                contractItems={contractItems}
                errors={errors}
            />

            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Permohonan
            </Button>
        </form>
    );
}

/** The scan of the Nota Dinas usulan. */
function RequestFile({
    procurementId,
    order,
}: {
    procurementId: number;
    order: DeliveryOrder;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | undefined>();

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-sm border border-border px-3 py-2 text-sm">
            <div className="min-w-0">
                <p className="font-medium">Dokumen Nota Dinas Usulan</p>
                {order.request_file ? (
                    <a
                        href={order.request_file.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <Paperclip className="size-3" />
                        {order.request_file.name}
                    </a>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Belum diunggah
                    </p>
                )}
                <InputError message={error} />
            </div>
            <input
                ref={input}
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                className="sr-only"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';

                    if (!file) {
                        return;
                    }

                    router.post(
                        procurements.deliveryOrders.uploadRequest({
                            procurement: procurementId,
                            order: order.id,
                        }).url,
                        { file },
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onStart: () => {
                                setUploading(true);
                                setError(undefined);
                            },
                            onError: (errors) => setError(errors.file),
                            onFinish: () => setUploading(false),
                        },
                    );
                }}
            />
            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={uploading}
                onClick={() => input.current?.click()}
            >
                <Upload className="size-3.5" />
                {order.request_file ? 'Ganti' : 'Unggah'}
            </Button>
        </div>
    );
}

/** Rendan's Nota Dinas DO. */
function MemoForm({
    procurementId,
    order,
}: {
    procurementId: number;
    order: DeliveryOrder;
}) {
    const form = useForm({
        do_memo_number: order.do_memo_number ?? '',
        do_memo_date: order.do_memo_date ?? '',
    });

    return (
        <form
            className="space-y-3 rounded-sm border border-border p-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.deliveryOrders.memo({
                        procurement: procurementId,
                        order: order.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <p className="text-sm font-semibold">Nota Dinas DO (Rendan)</p>
            <div className="grid gap-3 md:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`do-${order.id}-memo-number`}>
                        Nomor Nota Dinas DO
                    </Label>
                    <Input
                        id={`do-${order.id}-memo-number`}
                        value={form.data.do_memo_number}
                        onChange={(event) =>
                            form.setData('do_memo_number', event.target.value)
                        }
                    />
                    <InputError message={form.errors.do_memo_number} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`do-${order.id}-memo-date`}>
                        Tanggal Nota Dinas DO
                    </Label>
                    <Input
                        id={`do-${order.id}-memo-date`}
                        type="date"
                        className="tabular"
                        value={form.data.do_memo_date}
                        onChange={(event) =>
                            form.setData('do_memo_date', event.target.value)
                        }
                    />
                    <InputError message={form.errors.do_memo_date} />
                </div>
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Nota Dinas DO
            </Button>
        </form>
    );
}

/** Laksdan's DO: number, date, delivery terms and the lines. */
function IssueForm({
    procurementId,
    order,
    contractItems,
}: {
    procurementId: number;
    order: DeliveryOrder;
    contractItems: ContractItem[];
}) {
    const form = useForm<{
        number: string;
        order_date: string;
        vendor_code: string;
        work_description: string;
        delivery_place: string;
        delivery_days: string;
        items: Line[];
    }>({
        number: order.number ?? '',
        order_date: order.order_date ?? '',
        vendor_code: order.vendor_code ?? '',
        work_description: order.work_description ?? '',
        delivery_place: order.delivery_place ?? '',
        delivery_days:
            order.delivery_days === null ? '' : String(order.delivery_days),
        items: toLines(order),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const field = (
        name:
            | 'number'
            | 'order_date'
            | 'vendor_code'
            | 'delivery_place'
            | 'delivery_days',
        label: string,
        type: 'text' | 'date' | 'number' = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`do-${order.id}-${name}`}>{label}</Label>
            <Input
                id={`do-${order.id}-${name}`}
                type={type}
                className={type === 'text' ? undefined : 'tabular'}
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <form
            className="space-y-4 rounded-sm border border-border p-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.deliveryOrders.issueData({
                        procurement: procurementId,
                        order: order.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <p className="text-sm font-semibold">Penerbitan DO (Laksdan)</p>
            <div className="grid gap-3 md:grid-cols-2">
                {field('number', 'Nomor DO (otomatis, bisa dikoreksi)')}
                {field('order_date', 'Tanggal DO', 'date')}
                {field('vendor_code', 'Kode Rekanan')}
                {field('delivery_days', 'Waktu Penyerahan (hari)', 'number')}
                <div className="grid gap-1.5 md:col-span-2">
                    <Label htmlFor={`do-${order.id}-place`}>
                        Tempat Penyerahan Pekerjaan
                    </Label>
                    <Input
                        id={`do-${order.id}-place`}
                        value={form.data.delivery_place}
                        onChange={(event) =>
                            form.setData('delivery_place', event.target.value)
                        }
                    />
                    <InputError message={errors.delivery_place} />
                </div>
                <div className="grid gap-1.5 md:col-span-2">
                    <Label htmlFor={`do-${order.id}-work`}>
                        Uraian Pekerjaan
                    </Label>
                    <Textarea
                        id={`do-${order.id}-work`}
                        rows={2}
                        value={form.data.work_description}
                        onChange={(event) =>
                            form.setData('work_description', event.target.value)
                        }
                    />
                    <InputError message={errors.work_description} />
                </div>
            </div>

            <LinePicker
                lines={form.data.items}
                onChange={(lines) => form.setData('items', lines)}
                contractItems={contractItems}
                errors={errors}
            />

            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Data DO
            </Button>
        </form>
    );
}

/** What the DO says, for everyone who is not editing that step. */
function Summary({ order }: { order: DeliveryOrder }) {
    const rows: Array<[string, string | null]> = [
        ['Nota Dinas Usulan', order.request_memo_number],
        [
            'Tanggal Nota Dinas Usulan',
            order.request_memo_date
                ? formatDate(order.request_memo_date)
                : null,
        ],
        ['Nota Dinas DO', order.do_memo_number],
        [
            'Tanggal Nota Dinas DO',
            order.do_memo_date ? formatDate(order.do_memo_date) : null,
        ],
        ['Tanggal DO', order.order_date ? formatDate(order.order_date) : null],
        ['Tempat Penyerahan', order.delivery_place],
    ];

    return (
        <div className="space-y-3 text-sm">
            <dl className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-3">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="tabular text-right font-medium">
                            {value ?? '—'}
                        </dd>
                    </div>
                ))}
            </dl>
            {order.request_file && (
                <a
                    href={order.request_file.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                >
                    <Paperclip className="size-3" />
                    {order.request_file.name}
                </a>
            )}
            {order.items.length > 0 && <ItemTable rows={order.items} />}
        </div>
    );
}

/** What still holds the step back, and the button that moves it on. */
function ActionBar({
    missing,
    label,
    url,
}: {
    missing: string[];
    label: string;
    url: string;
}) {
    const [sending, setSending] = useState(false);

    return (
        <div className="space-y-2 border-t border-border pt-3">
            {missing.length > 0 && (
                <div className="space-y-1 rounded-sm border border-border bg-muted/40 p-2.5 text-xs">
                    <p className="flex items-center gap-1.5 font-medium">
                        <CircleAlert className="size-3.5 text-amber-600" />
                        Lengkapi dulu (simpan isian terlebih dahulu):
                    </p>
                    <ul className="list-disc pl-4 text-muted-foreground">
                        {missing.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}
            <Button
                disabled={sending || missing.length > 0}
                onClick={() =>
                    router.post(
                        url,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setSending(true),
                            onFinish: () => setSending(false),
                        },
                    )
                }
            >
                <Send className="size-4" />
                {label}
            </Button>
        </div>
    );
}

/** The TL or Asman decision on the request. */
function DecisionForm({
    procurementId,
    order,
}: {
    procurementId: number;
    order: DeliveryOrder;
}) {
    const form = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    const decide = (approved: boolean) => {
        form.transform((data) => ({ ...data, approved }));
        form.post(
            procurements.deliveryOrders.decide({
                procurement: procurementId,
                order: order.id,
            }).url,
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-2 border-t border-border pt-3">
            <Label htmlFor={`do-${order.id}-note`}>Catatan Persetujuan</Label>
            <Textarea
                id={`do-${order.id}-note`}
                rows={2}
                value={form.data.note}
                onChange={(event) => form.setData('note', event.target.value)}
                placeholder="Wajib diisi bila dikembalikan"
            />
            <InputError message={form.errors.note} />
            <div className="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    disabled={form.processing}
                    onClick={() => decide(true)}
                >
                    Setujui
                </Button>
                <Button
                    size="sm"
                    variant="destructive"
                    disabled={form.processing}
                    onClick={() => decide(false)}
                >
                    Kembalikan
                </Button>
            </div>
        </div>
    );
}
