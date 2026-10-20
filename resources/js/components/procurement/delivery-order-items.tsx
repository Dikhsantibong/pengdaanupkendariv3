import { AlertTriangle, ClipboardPaste, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import type {
    ContractItem,
    DeliveryOrder,
} from '@/components/procurement/delivery-order-tab';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';

/** A line of a DO as its forms keep it. */
export type Line = { contract_item_id: string; quantity: string };

/**
 * The lines of a DO, one per item of the price list (the server takes each
 * item once), the volumes of an item listed twice added up.
 */
export function toLines(order: DeliveryOrder): Line[] {
    const quantities = new Map<string, number>();

    order.items.forEach((item) => {
        if (item.contract_item_id === null) {
            return;
        }

        const key = String(item.contract_item_id);
        quantities.set(key, (quantities.get(key) ?? 0) + Number(item.quantity));
    });

    return Array.from(quantities, ([id, quantity]) => ({
        contract_item_id: id,
        quantity: String(quantity),
    }));
}

/** The lines that are sent: a volume of zero (or none) orders nothing. */
export function orderedLines(lines: Line[]): Line[] {
    return lines.filter((line) => Number(line.quantity) > 0);
}

/**
 * Where the contract's value stands: issued in DOs, held in DOs still on
 * their way, and what is left. Read from the DOs on the page.
 */
export function ContractValueSummary({
    contractValue,
    orders,
}: {
    contractValue: number | null;
    orders: DeliveryOrder[];
}) {
    const issued = orders
        .filter((order) => order.state === 'terbit')
        .reduce((sum, order) => sum + order.total, 0);
    const pending = orders
        .filter((order) => order.state !== 'terbit')
        .reduce((sum, order) => sum + order.total, 0);

    if (contractValue === null || contractValue <= 0) {
        return (
            <section className="grid gap-3 rounded-md border border-border bg-card p-4 sm:grid-cols-2">
                <Figure label="DO terbit" value={issued} />
                <Figure label="Sedang diproses / draf" value={pending} />
                <p className="text-xs text-muted-foreground sm:col-span-2">
                    Nilai kontrak (nilai setelah nego) belum diisi, sehingga
                    sisa nilai kontrak belum bisa dihitung.
                </p>
            </section>
        );
    }

    const remaining = contractValue - issued - pending;
    const share = (value: number) =>
        `${Math.min(100, Math.max(0, (value / contractValue) * 100))}%`;

    return (
        <section className="space-y-3 rounded-md border border-border bg-card p-4">
            <div className="grid gap-3 sm:grid-cols-4">
                <Figure label="Nilai kontrak (setelah nego)" value={contractValue} />
                <Figure label="DO terbit" value={issued} dot="bg-emerald-500" />
                <Figure
                    label="Sedang diproses / draf"
                    value={pending}
                    dot="bg-amber-500"
                />
                <Figure
                    label="Sisa nilai kontrak"
                    value={remaining}
                    emphasis={remaining < 0 ? 'danger' : 'strong'}
                />
            </div>
            <div
                className="flex h-2 overflow-hidden rounded-full bg-muted"
                aria-hidden="true"
            >
                <span
                    className="h-full bg-emerald-500"
                    style={{ width: share(issued) }}
                />
                <span
                    className="h-full bg-amber-500"
                    style={{ width: share(pending) }}
                />
            </div>
            {remaining < 0 && (
                <p className="flex items-center gap-1.5 text-xs text-destructive">
                    <AlertTriangle className="size-3.5" />
                    Total DO melebihi nilai kontrak sebesar{' '}
                    {formatCurrency(-remaining)}.
                </p>
            )}
        </section>
    );
}

function Figure({
    label,
    value,
    dot,
    emphasis,
}: {
    label: string;
    value: number;
    dot?: string;
    emphasis?: 'strong' | 'danger';
}) {
    return (
        <div className="min-w-0">
            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {dot && <span className={cn('size-2 rounded-full', dot)} />}
                {label}
            </p>
            <p
                className={cn(
                    'tabular truncate text-sm font-semibold',
                    emphasis === 'danger' && 'text-destructive',
                    emphasis === 'strong' && 'text-primary',
                )}
            >
                {formatCurrency(value)}
            </p>
        </div>
    );
}

/**
 * The price list as a table to order from: type the volume of each item
 * wanted (empty or 0 orders none). Search narrows the list; "hanya yang
 * dipilih" shows the order as it stands. The total runs below, set against
 * what is left of the contract's value, as a warning only.
 */
export function ItemPicker({
    lines,
    onChange,
    contractItems,
    errors,
    remaining = null,
}: {
    lines: Line[];
    onChange: (lines: Line[]) => void;
    contractItems: ContractItem[];
    errors: Record<string, string | undefined>;
    /** What is left of the contract's value for this DO, if known. */
    remaining?: number | null;
}) {
    const [query, setQuery] = useState('');
    const [onlyPicked, setOnlyPicked] = useState(false);
    const quantities = useMemo(
        () =>
            new Map(
                lines.map((line) => [line.contract_item_id, line.quantity]),
            ),
        [lines],
    );
    const sent = orderedLines(lines);

    const setQuantity = (id: number, value: string) => {
        const key = String(id);
        const next = new Map(quantities);

        if (value === '') {
            next.delete(key);
        } else {
            next.set(key, value);
        }

        // In the price list's own order.
        onChange(
            contractItems
                .filter((item) => next.has(String(item.id)))
                .map((item) => ({
                    contract_item_id: String(item.id),
                    quantity: next.get(String(item.id)) ?? '',
                })),
        );
    };

    const term = query.trim().toLowerCase();
    const shown = contractItems.filter((item) => {
        if (onlyPicked && !(Number(quantities.get(String(item.id))) > 0)) {
            return false;
        }

        return (
            term === '' ||
            [item.description, item.part_number, item.group_name]
                .filter(Boolean)
                .some((text) => String(text).toLowerCase().includes(term))
        );
    });

    const priceOf = (id: string) =>
        contractItems.find((item) => String(item.id) === id)?.unit_price ?? 0;
    const total = sent.reduce(
        (sum, line) =>
            sum + priceOf(line.contract_item_id) * Number(line.quantity),
        0,
    );
    const errorOf = (id: number) => {
        const index = sent.findIndex(
            (line) => line.contract_item_id === String(id),
        );

        return index === -1
            ? undefined
            : (errors[`items.${index}.quantity`] ??
                  errors[`items.${index}.contract_item_id`]);
    };

    // Each group's heading goes before its first item shown.
    const rows = shown.map((item, index) => ({
        item,
        heading:
            index === 0 || shown[index - 1].group_name !== item.group_name
                ? item.group_name
                : undefined,
    }));

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-48 flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Cari uraian, part number, atau kelompok"
                        className="h-8 pl-8 text-sm"
                    />
                </div>
                <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={onlyPicked}
                        onChange={(event) =>
                            setOnlyPicked(event.target.checked)
                        }
                        className="size-3.5 accent-primary"
                    />
                    Hanya yang dipilih ({sent.length})
                </label>
            </div>

            <div className="max-h-[50vh] overflow-auto rounded-sm border border-border">
                <table className="w-full text-xs">
                    <thead className="sticky top-0 z-10 bg-muted text-left">
                        <tr>
                            <th className="px-2 py-1.5">Item</th>
                            <th className="px-2 py-1.5 text-right">
                                Harga satuan
                            </th>
                            <th className="w-28 px-2 py-1.5">Volume</th>
                            <th className="px-2 py-1.5 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {shown.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-2 py-6 text-center text-muted-foreground"
                                >
                                    {contractItems.length === 0
                                        ? 'Daftar harga kontrak belum diisi.'
                                        : 'Tidak ada item yang cocok.'}
                                </td>
                            </tr>
                        )}
                        {rows.map(({ item, heading }) => {
                            const value = quantities.get(String(item.id)) ?? '';
                            const picked = Number(value) > 0;
                            const error = errorOf(item.id);

                            return (
                                <FragmentRows key={item.id} heading={heading}>
                                    <tr
                                        className={cn(picked && 'bg-primary/5')}
                                    >
                                        <td className="px-2 py-1.5">
                                            <span className="block">
                                                {item.description}
                                            </span>
                                            {item.part_number && (
                                                <span className="tabular block text-[11px] text-muted-foreground">
                                                    {item.part_number}
                                                </span>
                                            )}
                                        </td>
                                        <td className="tabular px-2 py-1.5 text-right whitespace-nowrap">
                                            {formatCurrency(item.unit_price)}
                                            <span className="text-muted-foreground">
                                                /{item.unit}
                                            </span>
                                        </td>
                                        <td className="px-2 py-1">
                                            <Input
                                                type="number"
                                                min={0}
                                                step="any"
                                                inputMode="decimal"
                                                aria-label={`Volume ${item.description}`}
                                                aria-invalid={
                                                    error ? true : undefined
                                                }
                                                value={value}
                                                onChange={(event) =>
                                                    setQuantity(
                                                        item.id,
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="0"
                                                className="tabular h-7 text-xs"
                                            />
                                            <InputError message={error} />
                                        </td>
                                        <td className="tabular px-2 py-1.5 text-right whitespace-nowrap">
                                            {picked
                                                ? formatCurrency(
                                                      item.unit_price *
                                                          Number(value),
                                                  )
                                                : '—'}
                                        </td>
                                    </tr>
                                </FragmentRows>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className="text-xs text-muted-foreground">
                    {sent.length} item dipilih
                    {remaining !== null &&
                        ` · Sisa nilai kontrak ${formatCurrency(remaining)}`}
                </span>
                <span className="tabular font-semibold">
                    Total {formatCurrency(total)}
                </span>
            </div>
            {remaining !== null && total > remaining && (
                <p className="flex items-start gap-1.5 rounded-sm border border-amber-500/30 bg-amber-500/5 px-2.5 py-2 text-xs text-amber-800 dark:text-amber-300">
                    <AlertTriangle className="mt-px size-3.5 shrink-0" />
                    Total DO ini melebihi sisa nilai kontrak sebesar{' '}
                    {formatCurrency(total - remaining)}. Periksa kembali
                    volumenya.
                </p>
            )}
            <InputError message={errors.items} />
        </div>
    );
}

/** A group's heading row before its first item, then the item. */
function FragmentRows({
    heading,
    children,
}: {
    heading: string | null | undefined;
    children: ReactNode;
}) {
    return (
        <>
            {heading !== undefined && (
                <tr className="bg-muted/40">
                    <td
                        colSpan={4}
                        className="px-2 py-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {heading ?? 'Tanpa kelompok'}
                    </td>
                </tr>
            )}
            {children}
        </>
    );
}

/** A row of the price list as the form keeps it. */
export type PriceListRow = {
    id: number | null;
    group_name: string;
    description: string;
    part_number: string;
    unit: string;
    unit_price: string;
};

/** "Rp 1.234.500,00" or "1,234,500.00" reads 1234500. */
function readPrice(text: string): string {
    const cleaned = text.replace(/[^\d.,]/g, '');
    const withoutDecimals = cleaned.replace(/[.,]\d{1,2}$/, '');

    return withoutDecimals.replace(/[.,]/g, '');
}

/**
 * Rows copied from a spreadsheet (tab separated), in the form's column
 * order: Kelompok, Uraian, Part Number, Satuan, Harga Satuan. Four columns
 * leave out the group, three keep only Uraian, Satuan and Harga. A heading
 * row (no number in its price) is passed over.
 */
export function parsePriceList(text: string): PriceListRow[] {
    return text
        .split(/\r?\n/)
        .map((line) => line.split('\t').map((cell) => cell.trim()))
        .filter((cells) => cells.some((cell) => cell !== ''))
        .map((cells): PriceListRow | null => {
            const [group, description, part, unit, price] =
                cells.length >= 5
                    ? cells
                    : cells.length === 4
                      ? ['', ...cells]
                      : cells.length === 3
                        ? ['', cells[0], '', cells[1], cells[2]]
                        : [];

            if (description === undefined || readPrice(price ?? '') === '') {
                return null;
            }

            return {
                id: null,
                group_name: group ?? '',
                description,
                part_number: part ?? '',
                unit: unit ?? '',
                unit_price: readPrice(price ?? ''),
            };
        })
        .filter((row): row is PriceListRow => row !== null);
}

/** Paste a block of the price list from Excel, then add it to the form. */
export function PriceListPaste({
    onAdd,
    onCancel,
}: {
    onAdd: (rows: PriceListRow[]) => void;
    onCancel: () => void;
}) {
    const [text, setText] = useState('');
    const rows = parsePriceList(text);

    return (
        <div className="space-y-2 rounded-sm border border-dashed border-primary/40 bg-primary/5 p-3">
            <Label
                htmlFor="price-list-paste"
                className="flex items-center gap-1.5"
            >
                <ClipboardPaste className="size-4" />
                Tempel dari Excel
            </Label>
            <p className="text-xs text-muted-foreground">
                Salin baris dari Excel dengan urutan kolom: Kelompok · Uraian ·
                Part Number · Satuan · Harga Satuan (tanpa Kelompok/Part Number
                juga bisa), lalu tempel di sini.
            </p>
            <Textarea
                id="price-list-paste"
                rows={5}
                value={text}
                onChange={(event) => setText(event.target.value)}
                className="bg-background font-mono text-xs"
                placeholder={
                    'Material A\tPiston, engine\t1.2670 N\tBh\t840341000'
                }
            />
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs text-muted-foreground">
                    {rows.length} baris terbaca
                </span>
                <Button
                    type="button"
                    size="sm"
                    disabled={rows.length === 0}
                    onClick={() => onAdd(rows)}
                >
                    Tambahkan {rows.length > 0 ? rows.length : ''} baris
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onCancel}
                >
                    Batal
                </Button>
            </div>
        </div>
    );
}
