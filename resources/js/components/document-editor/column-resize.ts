import { useEffect, useRef } from 'react';

/** How near a column line (in screen pixels) the pointer grabs it. */
const GRAB = 5;

/** The narrowest a column may be dragged to, in layout pixels. */
const MIN_WIDTH = 16;

type Grid = {
    /** Every cell of the table by row and column (a merged cell repeats). */
    rows: HTMLTableCellElement[][];
    columns: number;
};

type Drag = {
    table: HTMLTableElement;
    grid: Grid;
    /** The line dragged: between column `boundary` and the next. */
    boundary: number;
    startX: number;
    /** Screen pixels per layout pixel (the editor's zoom). */
    scale: number;
    /** Every column's width when the drag began, in layout pixels. */
    widths: number[];
    tableWidth: number;
};

/** The table laid out as a grid, merged cells spread over their columns. */
function gridOf(table: HTMLTableElement): Grid {
    const rows: HTMLTableCellElement[][] = [];

    Array.from(table.rows).forEach((row, rowIndex) => {
        rows[rowIndex] ??= [];
        let column = 0;

        Array.from(row.cells).forEach((cell) => {
            while (rows[rowIndex][column]) {
                column++;
            }

            for (let down = 0; down < Math.max(1, cell.rowSpan); down++) {
                rows[rowIndex + down] ??= [];

                for (
                    let across = 0;
                    across < Math.max(1, cell.colSpan);
                    across++
                ) {
                    rows[rowIndex + down][column + across] = cell;
                }
            }

            column += Math.max(1, cell.colSpan);
        });
    });

    return {
        rows,
        columns: Math.max(0, ...rows.map((row) => row.length)),
    };
}

/** The first and last column a cell covers. */
function spanOf(
    grid: Grid,
    cell: HTMLTableCellElement,
): [number, number] | null {
    for (const row of grid.rows) {
        const first = row.indexOf(cell);

        if (first !== -1) {
            return [first, row.lastIndexOf(cell)];
        }
    }

    return null;
}

/** Each column's width in layout pixels, read from a cell it holds alone. */
function widthsOf(grid: Grid, scale: number): number[] | null {
    const widths: number[] = [];

    for (let column = 0; column < grid.columns; column++) {
        const single = grid.rows
            .map((row) => row[column])
            .find((cell) => cell && cell.colSpan <= 1);

        if (!single) {
            return null;
        }

        widths.push(single.getBoundingClientRect().width / scale);
    }

    return widths;
}

/**
 * Put the widths on the table: on every cell holding one column alone (as
 * the printed PDF reads them), and on its <col>s when the table lays out by
 * its first row (table-layout: fixed), as the browser reads them then.
 */
function applyWidths(drag: Drag, widths: number[]): void {
    const percent = (width: number) =>
        `${Math.round((width / drag.tableWidth) * 1000) / 10}%`;

    for (const column of [drag.boundary, drag.boundary + 1]) {
        drag.grid.rows.forEach((row) => {
            const cell = row[column];

            if (cell && cell.colSpan <= 1) {
                cell.style.width = percent(widths[column]);
                cell.removeAttribute('width');
            }
        });
    }

    const view = drag.table.ownerDocument.defaultView;

    if (view?.getComputedStyle(drag.table).tableLayout !== 'fixed') {
        return;
    }

    let group = drag.table.querySelector(':scope > colgroup');

    if (!group) {
        group = drag.table.ownerDocument.createElement('colgroup');
        drag.table.prepend(group);
    }

    while (group.children.length < drag.grid.columns) {
        group.append(drag.table.ownerDocument.createElement('col'));
    }

    Array.from(group.children).forEach((col, column) => {
        if (column < widths.length) {
            (col as HTMLElement).style.width = percent(widths[column]);
            col.removeAttribute('width');
        }
    });
}

/**
 * Drag a table's column line to make one column wider and the next one
 * narrower, as in Word: the pointer turns into ↔ on a line between two
 * columns. The table keeps its width; the change is handed on when the
 * line is let go.
 *
 * @param root  gives the editable page (inside a frame, its body)
 * @param pageKey  changes when the page is another one (a frame reloaded)
 */
export function useColumnResize(
    root: () => HTMLElement | null,
    enabled: boolean,
    onResized: () => void,
    pageKey: unknown = null,
): void {
    const changed = useRef(onResized);

    useEffect(() => {
        changed.current = onResized;
    }, [onResized]);

    useEffect(() => {
        const page = root();

        if (!enabled || !page) {
            return;
        }

        const doc = page.ownerDocument;
        let hovered: {
            cell: HTMLTableCellElement;
            edge: 'left' | 'right';
        } | null = null;
        let drag: Drag | null = null;

        /** The column line under the pointer, if any. */
        const lineAt = (event: PointerEvent) => {
            const cell = (event.target as Element | null)?.closest?.(
                'td, th',
            ) as HTMLTableCellElement | null;

            if (!cell || !page.contains(cell)) {
                return null;
            }

            const rect = cell.getBoundingClientRect();

            if (Math.abs(event.clientX - rect.right) <= GRAB) {
                return { cell, edge: 'right' as const };
            }

            if (Math.abs(event.clientX - rect.left) <= GRAB) {
                return { cell, edge: 'left' as const };
            }

            return null;
        };

        /** The drag a line would start: null at the table's outer edges. */
        const dragFor = (
            line: { cell: HTMLTableCellElement; edge: 'left' | 'right' },
            startX: number,
        ): Drag | null => {
            const table = line.cell.closest('table') as HTMLTableElement | null;

            if (!table) {
                return null;
            }

            const grid = gridOf(table);
            const span = spanOf(grid, line.cell);

            if (!span) {
                return null;
            }

            const boundary = line.edge === 'right' ? span[1] : span[0] - 1;

            if (boundary < 0 || boundary >= grid.columns - 1) {
                return null;
            }

            const scale =
                table.getBoundingClientRect().width / table.offsetWidth || 1;
            const widths = widthsOf(grid, scale);

            if (!widths) {
                return null;
            }

            return {
                table,
                grid,
                boundary,
                startX,
                scale,
                widths,
                tableWidth: table.offsetWidth,
            };
        };

        const onHover = (event: PointerEvent) => {
            if (drag) {
                return;
            }

            const line = lineAt(event);
            hovered = line && dragFor(line, event.clientX) ? line : null;
            page.style.cursor = hovered ? 'col-resize' : '';
        };

        const onDown = (event: PointerEvent) => {
            if (event.button !== 0 || !hovered) {
                return;
            }

            drag = dragFor(hovered, event.clientX);

            if (!drag) {
                return;
            }

            // The caret stays where it was; nothing is selected while dragging.
            event.preventDefault();
            doc.body.style.userSelect = 'none';
            doc.addEventListener('pointermove', onDrag);
            doc.addEventListener('pointerup', onUp);
        };

        const onDrag = (event: PointerEvent) => {
            if (!drag) {
                return;
            }

            const left = drag.widths[drag.boundary];
            const right = drag.widths[drag.boundary + 1];
            const moved = (event.clientX - drag.startX) / drag.scale;
            const nextLeft = Math.min(
                Math.max(left + moved, MIN_WIDTH),
                left + right - MIN_WIDTH,
            );
            const widths = [...drag.widths];
            widths[drag.boundary] = nextLeft;
            widths[drag.boundary + 1] = left + right - nextLeft;

            applyWidths(drag, widths);
        };

        const onUp = () => {
            doc.removeEventListener('pointermove', onDrag);
            doc.removeEventListener('pointerup', onUp);
            doc.body.style.userSelect = '';
            page.style.cursor = '';

            if (drag) {
                drag = null;
                hovered = null;
                changed.current();
            }
        };

        page.addEventListener('pointermove', onHover);
        page.addEventListener('pointerdown', onDown);

        return () => {
            page.removeEventListener('pointermove', onHover);
            page.removeEventListener('pointerdown', onDown);
            doc.removeEventListener('pointermove', onDrag);
            doc.removeEventListener('pointerup', onUp);
            page.style.cursor = '';
        };
        // `root` is read again whenever the page changes (pageKey).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled, pageKey]);
}
