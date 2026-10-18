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
};

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
};

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
        });
    }

    return rows;
}

/**
 * Rows pasted from a spreadsheet: one line per row, columns in the grid's
 * order (Uraian, Part Number, Volume, Satuan, Harga Sebelum Nego, Harga
 * Setelah Nego). A line with only its first column is a group heading.
 */
function rowsFromClipboard(text: string): Row[] {
    return text
        .replace(/\r/g, '')
        .split('\n')
        .filter((line) => line.trim() !== '')
        .map((line) => {
            const [
                description = '',
                part = '',
                volume = '',
                unit = '',
                before = '',
                after = '',
            ] = line.split('\t').map((cell) => cell.trim());
            const isGroup = [part, volume, unit, before, after].every(
                (cell) => cell === '',
            );

            return {
                ...emptyItem(),
                kind: isGroup ? 'group' : 'item',
                description,
                part_number: part,
                volume: isGroup ? '' : volume || '1',
                unit,
                price_before: before,
                unit_price: after,
            };
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
        }));
}

/**
 * The Rincian Item of the document editor: the materials and services of
 * the job as a grid. Every change is handed to the editor, which redraws
 * the document's item table from it as it is typed; it is kept with the
 * document's own Simpan Perubahan. Rows can be pasted straight from Excel.
 */
export function ItemListCard({
    items,
    editable,
    errors = {},
    onRowsChange,
}: {
    items: ProcurementItem[];
    editable: boolean;
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

    const setRows = (next: Row[]) => {
        setRowsState(next);
        onRowsChange(payloadOf(next));
    };
    const update = (index: number, patch: Partial<Row>) =>
        setRows(
            rows.map((row, at) => (at === index ? { ...row, ...patch } : row)),
        );
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

        const pasted = rowsFromClipboard(text);

        if (pasted.length === 0) {
            return false;
        }

        // The row pasted into keeps its id; the rest are inserted below it.
        const [first, ...rest] = pasted;
        setRows([
            ...rows.slice(0, index),
            { ...first, id: rows[index].id, key: rows[index].key },
            ...rest,
            ...rows.slice(index + 1),
        ]);

        return true;
    };

    const itemRows = rows.filter((row) => row.kind === 'item');
    const amount = (row: Row, price: 'price_before' | 'unit_price') =>
        (parseVolume(row.volume) ?? 0) * (parseMoney(row[price]) ?? 0);
    const totalBefore = itemRows.reduce(
        (sum, row) => sum + amount(row, 'price_before'),
        0,
    );
    const totalAfter = itemRows.reduce(
        (sum, row) => sum + amount(row, 'unit_price'),
        0,
    );

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

    const cell = 'h-8 px-2 text-xs';
    const numberFields = [
        ['part_number', 'text-left'],
        ['volume', 'text-right'],
        ['unit', 'text-left'],
        ['price_before', 'text-right'],
        ['unit_price', 'text-right'],
    ] as const;

    return (
        <section className="rounded-md border border-border bg-card">
            {rows.length === 0 && !editable ? (
                <p className="px-4 py-6 text-center text-xs text-muted-foreground">
                    Belum ada rincian item.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[56rem] text-xs">
                        <thead className="bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th className="w-10 px-2 py-2 text-center">
                                    No
                                </th>
                                <th className="px-2 py-2">
                                    Uraian Barang/Jasa
                                </th>
                                <th className="w-28 px-2 py-2">Part No.</th>
                                <th className="w-20 px-2 py-2 text-right">
                                    Volume
                                </th>
                                <th className="w-20 px-2 py-2">Satuan</th>
                                <th className="w-32 px-2 py-2 text-right">
                                    Harga Sblm Nego
                                </th>
                                <th className="w-32 px-2 py-2 text-right">
                                    Harga Stlh Nego
                                </th>
                                <th className="w-32 px-2 py-2 text-right">
                                    Jumlah
                                </th>
                                {editable && <th className="w-40 px-2 py-2" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {rows.map((row, index) => {
                                const lineTotal =
                                    row.unit_price.trim() !== ''
                                        ? amount(row, 'unit_price')
                                        : amount(row, 'price_before');

                                return (
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
                                                            ? 7
                                                            : 1
                                                    }
                                                >
                                                    <Input
                                                        className={cn(
                                                            cell,
                                                            row.kind ===
                                                                'group' &&
                                                                'font-semibold',
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
                                                        {numberFields.map(
                                                            ([
                                                                field,
                                                                align,
                                                            ]) => (
                                                                <td
                                                                    key={field}
                                                                    className="px-1 py-1"
                                                                >
                                                                    <Input
                                                                        className={cn(
                                                                            cell,
                                                                            align,
                                                                            'tabular',
                                                                        )}
                                                                        inputMode={
                                                                            field ===
                                                                                'part_number' ||
                                                                            field ===
                                                                                'unit'
                                                                                ? 'text'
                                                                                : 'decimal'
                                                                        }
                                                                        value={
                                                                            row[
                                                                                field
                                                                            ]
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            update(
                                                                                index,
                                                                                {
                                                                                    [field]:
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                    <InputError
                                                                        message={
                                                                            errors[
                                                                                `item_rows.${index}.${field}`
                                                                            ]
                                                                        }
                                                                    />
                                                                </td>
                                                            ),
                                                        )}
                                                        <td className="tabular px-2 py-1.5 text-right">
                                                            {formatNumber(
                                                                lineTotal,
                                                            )}
                                                        </td>
                                                    </>
                                                )}
                                                <td className="px-1 py-1">
                                                    <div className="flex justify-end gap-0.5">
                                                        {row.kind ===
                                                        'group' ? (
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
                                                                        (
                                                                            _,
                                                                            at,
                                                                        ) =>
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
                                                colSpan={7}
                                                className="px-2 py-1.5"
                                            >
                                                {row.description}
                                            </td>
                                        ) : (
                                            <>
                                                <td className="px-2 py-1.5">
                                                    {row.description}
                                                </td>
                                                <td className="px-2 py-1.5 text-muted-foreground">
                                                    {row.part_number || '—'}
                                                </td>
                                                <td className="tabular px-2 py-1.5 text-right">
                                                    {row.volume}
                                                </td>
                                                <td className="px-2 py-1.5">
                                                    {row.unit}
                                                </td>
                                                <td className="tabular px-2 py-1.5 text-right">
                                                    {row.price_before || '—'}
                                                </td>
                                                <td className="tabular px-2 py-1.5 text-right">
                                                    {row.unit_price || '—'}
                                                </td>
                                                <td className="tabular px-2 py-1.5 text-right font-medium">
                                                    {formatNumber(lineTotal)}
                                                </td>
                                            </>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot className="border-t border-border bg-muted/40 font-semibold">
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-2 py-2 text-right"
                                >
                                    Total ({itemRows.length} item)
                                </td>
                                <td className="tabular px-2 py-2 text-right">
                                    {formatNumber(totalBefore)}
                                </td>
                                <td className="tabular px-2 py-2 text-right">
                                    {formatNumber(totalAfter)}
                                </td>
                                <td className="px-2 py-2" />
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
