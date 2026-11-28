export type ItemGridPriceField = 'price_before' | 'unit_price';

export type ItemGridTextField =
    | 'part_number'
    | 'unit'
    | 'previous_po_number'
    | 'previous_po_date'
    | 'previous_supplier';

export type ItemGridField =
    | ItemGridTextField
    | 'volume'
    | 'previous_price'
    | ItemGridPriceField;

export type ItemGridColumn =
    | { kind: 'description' }
    | { kind: 'field'; field: ItemGridField }
    | { kind: 'quote'; partnerId: number };

export type PasteableItemRow = {
    kind: 'group' | 'item';
    description: string;
    part_number: string;
    volume: string;
    unit: string;
    price_before: string;
    unit_price: string;
    previous_po_number: string;
    previous_po_date: string;
    previous_supplier: string;
    previous_price: string;
    quotes: Record<string, string>;
};

/** Format numbers in Indonesian display style (e.g. 2.520.000 or 2,5). */
export function formatGridNumber(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 3,
    }).format(value);
}

/**
 * Parse a decimal or thousand-separated number copied from Indonesian or
 * English/US Excel sheets (e.g. "25.000", "2,520,000.50", "2,5", "2.5").
 */
function parseFlexibleNumber(value: string): number | null {
    const cleaned = value.trim().replace(/[^0-9.,]/g, '');

    if (cleaned === '') {
        return null;
    }

    const lastDot = cleaned.lastIndexOf('.');
    const lastComma = cleaned.lastIndexOf(',');
    let normalised: string;

    if (lastDot !== -1 && lastComma !== -1) {
        normalised =
            lastComma > lastDot
                ? cleaned.replace(/\./g, '').replace(',', '.')
                : cleaned.replace(/,/g, '');
    } else if (lastComma !== -1) {
        const commaCount = (cleaned.match(/,/g) ?? []).length;

        normalised =
            commaCount > 1 || /^\d{1,3}(,\d{3})+$/.test(cleaned)
                ? cleaned.replace(/,/g, '')
                : cleaned.replace(',', '.');
    } else if (lastDot !== -1) {
        const dotCount = (cleaned.match(/\./g) ?? []).length;

        normalised =
            dotCount > 1 || /^\d{1,3}(\.\d{3})+$/.test(cleaned)
                ? cleaned.replace(/\./g, '')
                : cleaned;
    } else {
        normalised = cleaned;
    }

    const parsed = Number(normalised);

    return Number.isFinite(parsed) ? parsed : null;
}

/** Parse currency input or pasted Excel monetary value; blank stays null. */
export function parseMoney(value: string): number | null {
    return parseFlexibleNumber(value);
}

/** Parse quantity/volume input or pasted Excel number; blank stays null. */
export function parseVolume(value: string): number | null {
    return parseFlexibleNumber(value);
}

/**
 * Parse tab-separated clipboard text from Excel (RFC-4180 style quoting),
 * keeping quoted multiline cells intact while preserving interior blank rows.
 */
export function parseClipboardGrid(text: string): string[][] {
    if (text === '') {
        return [];
    }

    const parsedRows: string[][] = [];
    let currentRow: string[] = [];
    let cell = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const char = text[i];

        if (inQuotes) {
            if (char === '"') {
                if (text[i + 1] === '"') {
                    cell += '"';
                    i++;
                } else {
                    inQuotes = false;
                }
            } else if (char === '\r') {
                if (text[i + 1] === '\n') {
                    i++;
                }

                cell += '\n';
            } else {
                cell += char;
            }
        } else if (char === '"' && cell === '') {
            inQuotes = true;
        } else if (char === '\t') {
            currentRow.push(cell);
            cell = '';
        } else if (char === '\r' || char === '\n') {
            if (char === '\r' && text[i + 1] === '\n') {
                i++;
            }

            currentRow.push(cell);
            parsedRows.push(currentRow);
            currentRow = [];
            cell = '';
        } else {
            cell += char;
        }
    }

    if (cell !== '' || currentRow.length > 0) {
        currentRow.push(cell);
        parsedRows.push(currentRow);
    }

    while (
        parsedRows.length > 0 &&
        parsedRows[parsedRows.length - 1].every((item) => item.trim() === '')
    ) {
        parsedRows.pop();
    }

    return parsedRows;
}

function isTextGridField(field: ItemGridField): field is ItemGridTextField {
    return (
        field !== 'volume' &&
        field !== 'previous_price' &&
        field !== 'price_before' &&
        field !== 'unit_price'
    );
}

function formatCellForColumn(column: ItemGridColumn, rawValue: string): string {
    if (column.kind === 'description') {
        return rawValue.trim();
    }

    const singleLine = rawValue.replace(/\r?\n/g, ' ').trim();

    if (column.kind === 'field' && isTextGridField(column.field)) {
        return singleLine;
    }

    if (column.kind === 'field' && column.field === 'volume') {
        if (singleLine === '' || /^[-\u2013\u2014]+$/.test(singleLine)) {
            return '';
        }

        const parsed = parseVolume(singleLine);

        return parsed !== null ? formatGridNumber(parsed) : singleLine;
    }

    if (
        singleLine === '' ||
        /^(rp\.?\s*)?[-\u2013\u2014]+$/i.test(singleLine)
    ) {
        return '';
    }

    const parsed = parseMoney(singleLine);

    return parsed !== null ? formatGridNumber(parsed) : singleLine;
}

function applyCellsToRow<TRow extends PasteableItemRow>(
    row: TRow,
    pastedCells: string[],
    startColIndex: number,
    columns: ItemGridColumn[],
): TRow {
    const updated: TRow = {
        ...row,
        quotes: { ...row.quotes },
    };

    for (let offset = 0; offset < pastedCells.length; offset++) {
        const targetColIndex = startColIndex + offset;

        if (targetColIndex >= columns.length) {
            break;
        }

        const column = columns[targetColIndex];
        const formatted = formatCellForColumn(column, pastedCells[offset]);

        if (column.kind === 'description') {
            updated.description = formatted;
        } else if (column.kind === 'quote') {
            updated.quotes[String(column.partnerId)] = formatted;
        } else {
            updated[column.field] = formatted;
        }
    }

    return updated;
}

/**
 * Distribute rectangular Excel clipboard data starting at the focused cell,
 * updating only the pasted columns and rows without merging cells or wiping
 * neighbouring columns. Single-cell text returns null so the browser pastes inline.
 */
export function applyClipboardPaste<TRow extends PasteableItemRow>({
    rows,
    startRowIndex,
    startColIndex,
    columns,
    clipboardText,
    createEmptyRow,
}: {
    rows: TRow[];
    startRowIndex: number;
    startColIndex: number;
    columns: ItemGridColumn[];
    clipboardText: string;
    createEmptyRow: () => TRow;
}): TRow[] | null {
    const grid = parseClipboardGrid(clipboardText);

    if (grid.length === 0) {
        return null;
    }

    const maxCols = Math.max(...grid.map((row) => row.length));

    if (grid.length === 1 && maxCols <= 1) {
        return null;
    }

    const next = rows.map((row) => ({
        ...row,
        quotes: { ...row.quotes },
    }));

    let sourceRowStart = 0;
    let cursor = startRowIndex;

    if (next[startRowIndex]?.kind === 'group') {
        if (startColIndex === 0 && grid[0].length === 1) {
            next[startRowIndex].description = grid[0][0].trim();
            sourceRowStart = 1;
        }

        cursor = startRowIndex + 1;
    }

    for (let i = sourceRowStart; i < grid.length; i++) {
        while (cursor < next.length && next[cursor].kind === 'group') {
            cursor++;
        }

        if (cursor >= next.length) {
            next.push(createEmptyRow());
        }

        next[cursor] = applyCellsToRow(
            next[cursor],
            grid[i],
            startColIndex,
            columns,
        );
        cursor++;
    }

    return next;
}

export type ContractPriceItemRow = {
    id: number | null;
    group_name: string;
    description: string;
    part_number: string;
    unit: string;
    unit_price: string;
};

export type ContractPriceColumn =
    | 'group_name'
    | 'description'
    | 'part_number'
    | 'unit'
    | 'unit_price';

export const CONTRACT_PRICE_COLUMNS: ContractPriceColumn[] = [
    'group_name',
    'description',
    'part_number',
    'unit',
    'unit_price',
];

/**
 * Distribute rectangular Excel clipboard data across KHS contract price-list
 * inputs, keeping untouched columns and row ids intact. Single-cell paste
 * returns null so native inline paste remains active.
 */
export function applyContractItemPaste<TRow extends ContractPriceItemRow>({
    rows,
    startRowIndex,
    startColIndex,
    clipboardText,
    createEmptyRow,
}: {
    rows: TRow[];
    startRowIndex: number;
    startColIndex: number;
    clipboardText: string;
    createEmptyRow: () => TRow;
}): TRow[] | null {
    const grid = parseClipboardGrid(clipboardText);

    if (grid.length === 0) {
        return null;
    }

    const maxCols = Math.max(...grid.map((row) => row.length));

    if (grid.length === 1 && maxCols <= 1) {
        return null;
    }

    const next = rows.map((row) => ({ ...row }));

    for (let rowOffset = 0; rowOffset < grid.length; rowOffset++) {
        const targetRowIndex = startRowIndex + rowOffset;

        if (targetRowIndex >= next.length) {
            next.push(createEmptyRow());
        }

        const updated = { ...next[targetRowIndex] };
        const cells = grid[rowOffset];

        for (let colOffset = 0; colOffset < cells.length; colOffset++) {
            const targetColIndex = startColIndex + colOffset;

            if (targetColIndex >= CONTRACT_PRICE_COLUMNS.length) {
                break;
            }

            const field = CONTRACT_PRICE_COLUMNS[targetColIndex];
            const singleLine = cells[colOffset].replace(/\r?\n/g, ' ').trim();

            if (field === 'unit_price') {
                if (
                    singleLine === '' ||
                    /^(rp\.?\s*)?[-\u2013\u2014]+$/i.test(singleLine)
                ) {
                    updated.unit_price = '';
                } else {
                    const parsed = parseMoney(singleLine);

                    updated.unit_price =
                        parsed !== null ? String(parsed) : singleLine;
                }
            } else {
                updated[field] = singleLine;
            }
        }

        next[targetRowIndex] = updated;
    }

    return next;
}

/**
 * Parse a vertical Excel column of volumes for DO item selection. Single-cell
 * text returns null so the numeric input handles normal paste.
 */
export function parsePastedVolumes(clipboardText: string): string[] | null {
    const grid = parseClipboardGrid(clipboardText);

    if (grid.length <= 1) {
        return null;
    }

    return grid.map((cells) => {
        const singleLine = (cells[0] ?? '').replace(/\r?\n/g, ' ').trim();

        if (singleLine === '' || /^[-\u2013\u2014]+$/.test(singleLine)) {
            return '';
        }

        const parsed = parseVolume(singleLine);

        if (parsed === null || parsed <= 0) {
            return '';
        }

        return String(parsed);
    });
}
