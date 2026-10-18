import { ArrowDown, ArrowUp, FolderPlus, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** One item of the Rincian Item, as the server keeps it. */
export type ProcurementItem = {
    /** Null for a row read from the document's table, not saved yet. */
    id: number | null;
    group_name: string | null;
    description: string;
    part_number: string | null;
    volume: number;
    unit: string;
    price_before: number | null;
    unit_price: number | null;
    /** The unit price each calon mitra quoted, keyed by the mitra's id. */
    quotes?: Record<string, number>;
};

/** A calon mitra, given its own price column (Evaluasi Harga). */
export type ItemPartner = { id: number; name: string };

type PriceField = 'price_before' | 'unit_price';
type Field = 'part_number' | 'volume' | 'unit' | PriceField;

/** A row of the grid: a group heading or an item, every field as typed. */
type Row = {
    key: string;
    id: number | null;
    kind: 'group' | 'item';
    description: string;
    part_number: string;
    volume: string;
    unit: string;
    price_before: string;
    unit_price: string;
    quotes: Record<string, string>;
};

type Column = { field: Field; label: string };

/**
 * The columns a document's item table asks for, in its own words: the BA
 * Negosiasi compares the prices before and after negotiation, the Surat
 * Pesanan and SPK print the agreed price, the HPE/HPS/offer the price
 * before negotiation, and the UPB no price at all. Fields a document does
 * not show are kept as they are.
 */
function columnsFor(layout: string | null): Column[] {
    const goods = (part: string, volume: string): Column[] => [
        { field: 'part_number', label: part },
        { field: 'volume', label: volume },
        { field: 'unit', label: 'Satuan' },
    ];

    switch (layout) {
        case 'surat-pesanan':
        case 'purchase-order':
        case 'spk':
        case 'lampiran-spk':
            return [
                ...goods('Part No.', 'Volume'),
                { field: 'unit_price', label: 'Harga Satuan' },
            ];
        case 'hpe':
        case 'rab':
            return [
                ...goods('Stock Code', 'Qty'),
                { field: 'price_before', label: 'Harga HPE' },
            ];
        case 'penyusunan-hps':
            return [
                ...goods('Part No.', 'Volume'),
                { field: 'price_before', label: 'Harga Satuan HPS' },
            ];
        case 'penawaran':
            return [
                ...goods('Part No.', 'Volume'),
                { field: 'price_before', label: 'Harga Penawaran' },
            ];
        case 'upb':
            return goods('Stock Code', 'Qty');
        default:
            return [
                ...goods('Part No.', 'Volume'),
                { field: 'price_before', label: 'Harga Sblm Nego' },
                { field: 'unit_price', label: 'Harga Stlh Nego' },
            ];
    }
}

const isPrice = (field: Field): field is PriceField =>
    field === 'price_before' || field === 'unit_price';

let nextKey = 0;
const newKey = () => `row-${++nextKey}`;

const emptyItem = (): Row => ({
    key: newKey(),
    id: null,
    kind: 'item',
    description: '',
    part_number: '',
    volume: '1',
    unit: '',
    price_before: '',
    unit_price: '',
    quotes: {},
});

const formatNumber = (value: number) =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(value);

/** "25.000" or "25000" → 25000; blank stays blank. */
const parseMoney = (value: string): number | null => {
    const digits = value.replace(/[^0-9,]/g, '').replace(',', '.');

    return digits === '' ? null : Number(digits);
};

/** "2,5" → 2.5, "1.000" → 1000, "2.5" → 2.5. */
const parseVolume = (value: string): number | null => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const normalised = trimmed.includes(',')
        ? trimmed.replace(/\./g, '').replace(',', '.')
        : /^\d{1,3}(\.\d{3})+$/.test(trimmed)
          ? trimmed.replace(/\./g, '')
          : trimmed;
    const number = Number(normalised.replace(/[^0-9.]/g, ''));

    return Number.isFinite(number) ? number : null;
};

/** The grid's rows from the stored items: a heading where a group starts. */
function rowsFrom(items: ProcurementItem[]): Row[] {
    const rows: Row[] = [];
    let group: string | null = null;

    for (const item of items) {
        if (item.group_name && item.group_name !== group) {
            rows.push({
                ...emptyItem(),
                kind: 'group',
                description: item.group_name,
                volume: '',
            });
        }

        group = item.group_name;
        rows.push({
            key: newKey(),
            id: item.id,
            kind: 'item',
            description: item.description,
            part_number: item.part_number ?? '',
            volume: formatNumber(item.volume),
            unit: item.unit,
            price_before:
                item.price_before === null
                    ? ''
                    : formatNumber(item.price_before),
            unit_price:
                item.unit_price === null ? '' : formatNumber(item.unit_price),
            quotes: Object.fromEntries(
                Object.entries(item.quotes ?? {}).map(([partner, price]) => [
                    partner,
                    formatNumber(price),
                ]),
            ),
        });
    }

    return rows;
}

/**
 * Rows pasted from a spreadsheet: one line per row, Uraian first and then
 * the grid's own columns in order. A line with only its first column is a
 * group heading.
 */
function rowsFromClipboard(text: string, fields: Field[]): Row[] {
    return text
        .replace(/\r/g, '')
        .split('\n')
        .filter((line) => line.trim() !== '')
        .map((line) => {
            const [description = '', ...cells] = line
                .split('\t')
                .map((cell) => cell.trim());
            const isGroup = cells.every((cell) => cell === '');
            const row: Row = {
                ...emptyItem(),
                kind: isGroup ? 'group' : 'item',
                description,
            };

            fields.forEach((field, at) => {
                row[field] = cells[at] ?? '';
            });

            row.volume = isGroup ? '' : row.volume || '1';

            return row;
        });
}

/** A row as the server takes it (to draw the table, or to keep it). */
export type ItemRowPayload = {
    id: number | null;
    kind: 'group' | 'item';
    description: string;
    part_number: string | null;
    volume: number | null;
    unit: string | null;
    price_before: number | null;
    unit_price: number | null;
    /** Kept as they are, so saving from any editor keeps every quote. */
    quotes: Record<string, number>;
};

/** The rows as the server takes them; rows left blank are dropped. */
function payloadOf(rows: Row[]): ItemRowPayload[] {
    return rows
        .filter((row) => row.description.trim() !== '')
        .map((row) => ({
            id: row.id,
            kind: row.kind,
            description: row.description.trim(),
            part_number: row.part_number.trim() || null,
            volume: parseVolume(row.volume),
            unit: row.unit.trim() || null,
            price_before: parseMoney(row.price_before),
            unit_price: parseMoney(row.unit_price),
            quotes: Object.fromEntries(
                Object.entries(row.quotes).flatMap(([partner, price]) => {
                    const value = parseMoney(price);

                    return value === null ? [] : [[partner, value]];
                }),
            ),
        }));
}

/**
 * The Rincian Item of the document editor: the materials and services of
 * the job as a grid, with the columns the document being edited prints.
 * Every change is handed to the editor, which redraws the document's item
 * table from it as it is typed; it is kept with the document's own Simpan
 * Perubahan. Rows can be pasted straight from Excel. Given calon mitra
 * (the Evaluasi Harga), each gets a column for the unit price it quoted.
 */
export function ItemListCard({
    items,
    editable,
    layout = null,
    partners = [],
    errors = {},
    onRowsChange,
}: {
    items: ProcurementItem[];
    editable: boolean;
    /** The document type whose item table the grid fills. */
    layout?: string | null;
    partners?: ItemPartner[];
    /** Validation messages keyed item_rows.{index}.{field}. */
    errors?: Record<string, string | undefined>;
    onRowsChange: (rows: ItemRowPayload[]) => void;
}) {
    const [rows, setRowsState] = useState<Row[]>(() => rowsFrom(items));

    // Saved items come back with their ids: the grid takes them, so the
    // next save updates those rows instead of adding them again.
    const knownItems = useRef(items);

    useEffect(() => {
        if (knownItems.current === items) {
            return;
        }

        knownItems.current = items;
        setRowsState(rowsFrom(items));
    }, [items]);

    const columns = columnsFor(layout);
    const goodsColumns = columns.filter((column) => !isPrice(column.field));
    const priceColumns = columns.filter(
        (column): column is Column & { field: PriceField } =>
            isPrice(column.field),
    );
    const hasAmount = priceColumns.length > 0;
    // Uraian, then every column up to the amount.
    const span = 1 + columns.length + partners.length + (hasAmount ? 1 : 0);

    const setRows = (next: Row[]) => {
        setRowsState(next);
        onRowsChange(payloadOf(next));
    };
    const update = (index: number, patch: Partial<Row>) =>
        setRows(
            rows.map((row, at) => (at === index ? { ...row, ...patch } : row)),
        );
    const quote = (index: number, partner: number, price: string) =>
        update(index, {
            quotes: { ...rows[index].quotes, [partner]: price },
        });
    const move = (index: number, by: -1 | 1) => {
        const target = index + by;

        if (target < 0 || target >= rows.length) {
            return;
        }

        const next = [...rows];
        [next[index], next[target]] = [next[target], next[index]];
        setRows(next);
    };

    /**
     * A new item where it belongs: at the end of a group when added from
     * the group's row (before the next group starts), or straight below an
     * item's row, so an earlier group can still grow after later ones exist.
     */
    const insertItem = (index: number) => {
        let at = index + 1;

        if (rows[index].kind === 'group') {
            while (at < rows.length && rows[at].kind === 'item') {
                at++;
            }
        }

        setRows([...rows.slice(0, at), emptyItem(), ...rows.slice(at)]);
    };

    const pasteInto = (index: number, text: string): boolean => {
        if (!text.includes('\t') && !text.includes('\n')) {
            return false;
        }

        const pasted = rowsFromClipboard(
            text,
            columns.map((column) => column.field),
        );

        if (pasted.length === 0) {
            return false;
        }

        // The row pasted into keeps its id and the fields this grid does not
        // show; the rest are inserted below it.
        const [first, ...rest] = pasted;
        const kept = rows[index];
        const shown = Object.fromEntries(
            columns.map((column) => [column.field, first[column.field]]),
        );
        setRows([
            ...rows.slice(0, index),
            {
                ...kept,
                ...shown,
                kind: first.kind,
                description: first.description,
                volume: first.volume,
            },
            ...rest,
            ...rows.slice(index + 1),
        ]);

        return true;
    };

    const itemRows = rows.filter((row) => row.kind === 'item');
    const amount = (row: Row, price: PriceField) =>
        (parseVolume(row.volume) ?? 0) * (parseMoney(row[price]) ?? 0);
    // The line's amount at the last price the document shows that is filled
    // in (after negotiation once known, else before).
    const lineTotal = (row: Row) => {
        const filled = [...priceColumns]
            .reverse()
            .find((column) => row[column.field].trim() !== '');

        return filled ? amount(row, filled.field) : 0;
    };

    // A group is lettered, its items numbered from 1 under it.
    const labels: string[] = [];
    rows.reduce(
        (count, row) => {
            if (row.kind === 'group') {
                labels.push(String.fromCharCode(65 + count.groups));

                return { groups: count.groups + 1, items: 0 };
            }

            labels.push(String(count.items + 1));

            return { ...count, items: count.items + 1 };
        },
        { groups: 0, items: 0 },
    );

    // Filled, darker fields: the grid reads at once as something to type in.
    const cell =
        'h-8 border-input bg-muted px-2 text-xs shadow-none hover:border-primary/60 focus-visible:bg-background';
    const alignOf = (field: Field) =>
        field === 'part_number' || field === 'unit'
            ? 'text-left'
            : 'text-right';
    const widthOf = (field: Field) =>
        field === 'part_number'
            ? 'w-28'
            : field === 'volume' || field === 'unit'
              ? 'w-20'
              : 'w-32';

    const fieldInput = (row: Row, index: number, field: Field) => (
        <td key={field} className="px-1 py-1">
            <Input
                className={cn(cell, alignOf(field), 'tabular')}
                inputMode={
                    field === 'part_number' || field === 'unit'
                        ? 'text'
                        : 'decimal'
                }
                value={row[field]}
                onChange={(event) =>
                    update(index, { [field]: event.target.value })
                }
            />
            <InputError message={errors[`item_rows.${index}.${field}`]} />
        </td>
    );
    const quoteInputs = (row: Row, index: number) =>
        partners.map((partner) => (
            <td key={`quote-${partner.id}`} className="px-1 py-1">
                <Input
                    className={cn(cell, 'tabular text-right')}
                    inputMode="decimal"
                    aria-label={`Harga ${partner.name}`}
                    value={row.quotes[partner.id] ?? ''}
                    onChange={(event) =>
                        quote(index, partner.id, event.target.value)
                    }
                />
            </td>
        ));

    return (
        <section className="rounded-md border border-border bg-card">
            {rows.length === 0 && !editable ? (
                <p className="px-4 py-6 text-center text-xs text-muted-foreground">
                    Belum ada rincian item.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table
                        className="w-full text-xs"
                        style={{
                            minWidth: `${36 + (columns.length + partners.length) * 8}rem`,
                        }}
                    >
                        <thead className="border-b border-input bg-muted text-left text-[11px] font-semibold tracking-wide text-foreground/80 uppercase">
                            <tr>
                                <th className="w-10 px-2 py-2 text-center">
                                    No
                                </th>
                                <th className="px-2 py-2">
                                    Uraian Barang/Jasa
                                </th>
                                {goodsColumns.map((column) => (
                                    <th
                                        key={column.field}
                                        className={cn(
                                            widthOf(column.field),
                                            alignOf(column.field),
                                            'px-2 py-2',
                                        )}
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                {partners.map((partner) => (
                                    <th
                                        key={`quote-${partner.id}`}
                                        className="w-32 px-2 py-2 text-right"
                                        title={`Harga satuan ${partner.name}`}
                                    >
                                        {partner.name}
                                    </th>
                                ))}
                                {priceColumns.map((column) => (
                                    <th
                                        key={column.field}
                                        className="w-32 px-2 py-2 text-right"
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                {hasAmount && (
                                    <th className="w-32 px-2 py-2 text-right">
                                        Jumlah
                                    </th>
                                )}
                                {editable && <th className="w-40 px-2 py-2" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {rows.map((row, index) => (
                                <tr
                                    key={row.key}
                                    className={cn(
                                        row.kind === 'group' &&
                                            'bg-primary/5 font-semibold',
                                    )}
                                >
                                    <td className="tabular px-2 py-1.5 text-center">
                                        {labels[index]}
                                    </td>
                                    {editable ? (
                                        <>
                                            <td
                                                className="px-1 py-1"
                                                colSpan={
                                                    row.kind === 'group'
                                                        ? span
                                                        : 1
                                                }
                                            >
                                                <Input
                                                    className={cn(
                                                        cell,
                                                        row.kind === 'group' &&
                                                            'border-primary/50 bg-primary/10 font-semibold',
                                                    )}
                                                    value={row.description}
                                                    title="Bisa tempel baris dari Excel"
                                                    placeholder={
                                                        row.kind === 'group'
                                                            ? 'Nama kelompok'
                                                            : 'Uraian barang/jasa'
                                                    }
                                                    onChange={(event) =>
                                                        update(index, {
                                                            description:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    onPaste={(event) => {
                                                        if (
                                                            pasteInto(
                                                                index,
                                                                event.clipboardData.getData(
                                                                    'text',
                                                                ),
                                                            )
                                                        ) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `item_rows.${index}.description`
                                                        ]
                                                    }
                                                />
                                            </td>
                                            {row.kind === 'item' && (
                                                <>
                                                    {goodsColumns.map(
                                                        (column) =>
                                                            fieldInput(
                                                                row,
                                                                index,
                                                                column.field,
                                                            ),
                                                    )}
                                                    {quoteInputs(row, index)}
                                                    {priceColumns.map(
                                                        (column) =>
                                                            fieldInput(
                                                                row,
                                                                index,
                                                                column.field,
                                                            ),
                                                    )}
                                                    {hasAmount && (
                                                        <td className="tabular px-2 py-1.5 text-right">
                                                            {formatNumber(
                                                                lineTotal(row),
                                                            )}
                                                        </td>
                                                    )}
                                                </>
                                            )}
                                            <td className="px-1 py-1">
                                                <div className="flex justify-end gap-0.5">
                                                    {row.kind === 'group' ? (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 px-2 text-xs font-medium"
                                                            title="Tambah item di akhir kelompok ini"
                                                            onClick={() =>
                                                                insertItem(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Plus className="size-3.5" />
                                                            Item
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7"
                                                            title="Sisipkan item di bawah baris ini"
                                                            onClick={() =>
                                                                insertItem(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Plus className="size-3.5" />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        title="Naik"
                                                        onClick={() =>
                                                            move(index, -1)
                                                        }
                                                    >
                                                        <ArrowUp className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        title="Turun"
                                                        onClick={() =>
                                                            move(index, 1)
                                                        }
                                                    >
                                                        <ArrowDown className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7 text-destructive hover:text-destructive"
                                                        title="Hapus baris"
                                                        onClick={() =>
                                                            setRows(
                                                                rows.filter(
                                                                    (_, at) =>
                                                                        at !==
                                                                        index,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </>
                                    ) : row.kind === 'group' ? (
                                        <td
                                            colSpan={span}
                                            className="px-2 py-1.5"
                                        >
                                            {row.description}
                                        </td>
                                    ) : (
                                        <>
                                            <td className="px-2 py-1.5">
                                                {row.description}
                                            </td>
                                            {goodsColumns.map((column) => (
                                                <td
                                                    key={column.field}
                                                    className={cn(
                                                        'px-2 py-1.5',
                                                        alignOf(column.field),
                                                        column.field ===
                                                            'part_number' &&
                                                            'text-muted-foreground',
                                                    )}
                                                >
                                                    {row[column.field] || '—'}
                                                </td>
                                            ))}
                                            {partners.map((partner) => (
                                                <td
                                                    key={`quote-${partner.id}`}
                                                    className="tabular px-2 py-1.5 text-right"
                                                >
                                                    {row.quotes[partner.id] ||
                                                        '—'}
                                                </td>
                                            ))}
                                            {priceColumns.map((column) => (
                                                <td
                                                    key={column.field}
                                                    className="tabular px-2 py-1.5 text-right"
                                                >
                                                    {row[column.field] || '—'}
                                                </td>
                                            ))}
                                            {hasAmount && (
                                                <td className="tabular px-2 py-1.5 text-right font-medium">
                                                    {formatNumber(
                                                        lineTotal(row),
                                                    )}
                                                </td>
                                            )}
                                        </>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t border-border bg-muted/40 font-semibold">
                            <tr>
                                <td
                                    colSpan={
                                        2 +
                                        goodsColumns.length +
                                        partners.length
                                    }
                                    className="px-2 py-2 text-right"
                                >
                                    Total ({itemRows.length} item)
                                </td>
                                {priceColumns.map((column) => (
                                    <td
                                        key={column.field}
                                        className="tabular px-2 py-2 text-right"
                                    >
                                        {formatNumber(
                                            itemRows.reduce(
                                                (sum, row) =>
                                                    sum +
                                                    amount(row, column.field),
                                                0,
                                            ),
                                        )}
                                    </td>
                                ))}
                                {hasAmount && <td className="px-2 py-2" />}
                                {editable && <td />}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}

            {editable && (
                <div className="flex flex-wrap gap-2 border-t border-border p-3">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setRows([...rows, emptyItem()])}
                    >
                        <Plus className="size-3.5" />
                        Tambah Item
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            setRows([
                                ...rows,
                                { ...emptyItem(), kind: 'group', volume: '' },
                            ])
                        }
                    >
                        <FolderPlus className="size-3.5" />
                        Tambah Kelompok
                    </Button>
                </div>
            )}
        </section>
    );
}
