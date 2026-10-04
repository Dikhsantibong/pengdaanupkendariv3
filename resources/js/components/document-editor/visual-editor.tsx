import {
    Bold,
    Calculator,
    Columns3,
    Italic,
    List,
    ListOrdered,
    Minus,
    PenLine,
    Plus,
    Redo2,
    RotateCcw,
    Rows3,
    Scissors,
    SquareDashed,
    Table as TableIcon,
    Trash2,
    Underline,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/** The block styles an author picks from, in their own words. */
const BLOCK_STYLES = [
    { value: 'p', label: 'Paragraf' },
    { value: 'h1', label: 'Judul Dokumen' },
    { value: 'h2', label: 'Judul Bab' },
    { value: 'h3', label: 'Sub Judul' },
    { value: 'h4', label: 'Sub Sub Judul' },
];

function isNegotiationTable(table: HTMLTableElement): boolean {
    const text = (table.innerText || '').toUpperCase();
    return (
        (text.includes('HARGA SEBELUM NEGO') || text.includes('SEBELUM NEGO')) &&
        (text.includes('TOTAL HARGA') || text.includes('DPP'))
    );
}

function parseNegoNumber(str: string): number {
    const clean = str.replace(/[^0-9]/g, '');
    return parseInt(clean, 10) || 0;
}

function parseNegoVolume(str: string): number {
    const clean = str.replace(/[^0-9.,]/g, '').replace(',', '.');
    return parseFloat(clean) || 1;
}

function formatRupiahDisplay(num: number): string {
    return new Intl.NumberFormat('id-ID').format(Math.round(num));
}

function formatCellAsRupiahWithCaret(cell: HTMLElement) {
    const raw = cell.innerText.replace(/[^0-9]/g, '');
    if (!raw) {
        return;
    }
    const num = parseInt(raw, 10);
    const formatted = formatRupiahDisplay(num);
    if (cell.innerText.trim() === formatted) {
        return;
    }

    const sel = window.getSelection();
    let digitsBefore = 0;
    if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        if (cell.contains(range.startContainer)) {
            const preRange = range.cloneRange();
            preRange.selectNodeContents(cell);
            preRange.setEnd(range.startContainer, range.startOffset);
            digitsBefore = preRange.toString().replace(/[^0-9]/g, '').length;
        }
    }

    cell.innerText = formatted;

    if (sel) {
        let count = 0;
        let targetOffset = formatted.length;
        for (let i = 0; i < formatted.length; i++) {
            if (/[0-9]/.test(formatted[i])) {
                count++;
            }
            if (count >= digitsBefore) {
                targetOffset = i + 1;
                break;
            }
        }

        try {
            const textNode = cell.firstChild;
            if (textNode) {
                const newRange = document.createRange();
                const offset = Math.min(targetOffset, textNode.textContent?.length || 0);
                newRange.setStart(textNode, offset);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        } catch {
            // Keep going if range cannot be restored
        }
    }
}

function recalculateNegotiationTable(table: HTMLTableElement, activeCell?: HTMLElement | null) {
    const tbody = table.querySelector('tbody') || table;
    const rows = Array.from(tbody.querySelectorAll('tr'));

    const itemRows: HTMLTableRowElement[] = [];
    let rowTotal: HTMLTableRowElement | null = null;
    let rowDpp: HTMLTableRowElement | null = null;
    let rowPpn: HTMLTableRowElement | null = null;
    let rowGrand: HTMLTableRowElement | null = null;

    for (const r of rows) {
        const text = r.innerText.toUpperCase();
        if (text.includes('TOTAL HARGA')) {
            rowTotal = r;
        } else if (text.includes('DPP')) {
            rowDpp = r;
        } else if (text.includes('PPN')) {
            rowPpn = r;
        } else if (text.includes('JUMLAH TOTAL')) {
            rowGrand = r;
        } else if (r.cells.length === 8) {
            itemRows.push(r);
        }
    }

    if (itemRows.length === 0) return;

    let sumTotalBefore = 0;
    let sumTotalAfter = 0;

    itemRows.forEach((row, idx) => {
        const cells = row.cells;
        if (cells.length < 8) return;

        // Auto re-number NO
        if (cells[0] !== activeCell) {
            cells[0].innerText = String(idx + 1);
        }

        const vol = parseNegoVolume(cells[2].innerText);
        const priceBefore = parseNegoNumber(cells[4].innerText);
        const totalBefore = Math.round(vol * priceBefore);

        const priceAfter = parseNegoNumber(cells[6].innerText);
        const totalAfter = Math.round(vol * priceAfter);

        // Update Jumlah Harga Sebelum (cell 5)
        if (cells[5] !== activeCell) {
            cells[5].innerText = totalBefore > 0 ? formatRupiahDisplay(totalBefore) : '0';
        }

        // Update Jumlah Harga Setelah (cell 7)
        if (cells[7] !== activeCell) {
            cells[7].innerText = totalAfter > 0 ? formatRupiahDisplay(totalAfter) : '0';
        }

        sumTotalBefore += totalBefore;
        sumTotalAfter += totalAfter;
    });

    const dppBefore = Math.round(sumTotalBefore * 11 / 12);
    const dppAfter = Math.round(sumTotalAfter * 11 / 12);

    const ppnBefore = Math.round(sumTotalBefore * 0.12);
    const ppnAfter = Math.round(sumTotalAfter * 0.12);

    const grandBefore = sumTotalBefore + ppnBefore;
    const grandAfter = sumTotalAfter + ppnAfter;

    // Update TOTAL HARGA
    if (rowTotal && rowTotal.cells.length >= 3) {
        const cBefore = rowTotal.cells[rowTotal.cells.length - 2];
        const cAfter = rowTotal.cells[rowTotal.cells.length - 1];
        if (cBefore !== activeCell) cBefore.innerText = formatRupiahDisplay(sumTotalBefore);
        if (cAfter !== activeCell) cAfter.innerText = formatRupiahDisplay(sumTotalAfter);
    }

    // Update DPP 11/12
    if (rowDpp && rowDpp.cells.length >= 3) {
        const cBefore = rowDpp.cells[rowDpp.cells.length - 2];
        const cAfter = rowDpp.cells[rowDpp.cells.length - 1];
        if (cBefore !== activeCell) cBefore.innerText = formatRupiahDisplay(dppBefore);
        if (cAfter !== activeCell) cAfter.innerText = formatRupiahDisplay(dppAfter);
    }

    // Update PPN 12%
    if (rowPpn && rowPpn.cells.length >= 3) {
        const cBefore = rowPpn.cells[rowPpn.cells.length - 2];
        const cAfter = rowPpn.cells[rowPpn.cells.length - 1];
        if (cBefore !== activeCell) cBefore.innerText = formatRupiahDisplay(ppnBefore);
        if (cAfter !== activeCell) cAfter.innerText = formatRupiahDisplay(ppnAfter);
    }

    // Update JUMLAH TOTAL
    if (rowGrand && rowGrand.cells.length >= 3) {
        const cBefore = rowGrand.cells[rowGrand.cells.length - 2];
        const cAfter = rowGrand.cells[rowGrand.cells.length - 1];
        if (cBefore !== activeCell) cBefore.innerText = formatRupiahDisplay(grandBefore);
        if (cAfter !== activeCell) cAfter.innerText = formatRupiahDisplay(grandAfter);
    }
}

/**
 * Edit a document the way it will be printed.
 *
 * The editable area *is* the document: the same markup and the same print
 * styling, edited in place. Nothing is re-parsed through a foreign document
 * model, so the page breaks, signature blocks and tables the RKS depends on
 * survive editing untouched — which a generic rich text editor cannot promise.
 */
export function VisualEditor({
    value,
    onChange,
    disabled = false,
}: {
    value: string;
    onChange: (html: string) => void;
    disabled?: boolean;
}) {
    const area = useRef<HTMLDivElement>(null);
    const lastEmitted = useRef(value);
    const seeded = useRef(false);
    const [inTable, setInTable] = useState(false);
    const [blockStyle, setBlockStyle] = useState('p');
    const isLandscape = value.toLowerCase().includes('landscape');

    // The area is uncontrolled while typing: writing innerHTML on every render
    // would move the caret to the start on each keystroke. So it is written on
    // the first mount, and afterwards only when the body changes from outside,
    // such as after a template reload. The explicit `seeded` flag matters: on
    // mount the value already matches what was emitted, so comparing the two
    // would skip the very write that puts the document on screen.
    useEffect(() => {
        if (area.current === null) {
            return;
        }

        if (!seeded.current || value !== lastEmitted.current) {
            // An empty body leaves nowhere to put the caret, so give the author
            // a paragraph to start typing in.
            area.current.innerHTML =
                value.trim() === '' ? '<p><br></p>' : value;
            area.current.querySelectorAll('.page-break').forEach((el) => {
                el.setAttribute('contenteditable', 'false');
            });
            lastEmitted.current = value;
            seeded.current = true;
        }
    }, [value]);

    const scrollContainer = useRef<HTMLDivElement>(null);
    const paperContainer = useRef<HTMLDivElement>(null);
    const [tableMenuPos, setTableMenuPos] = useState<{
        top: number;
        left: number;
    } | null>(null);
    const lastActiveCell = useRef<HTMLTableCellElement | null>(null);

    const emit = useCallback(() => {
        if (!area.current) return;

        // If the caret is in a negotiation table, handle price formatting & formula recalculation
        const sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            const node = sel.getRangeAt(0).startContainer;
            const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : (node as HTMLElement);
            const cell = element?.closest('td, th') as HTMLTableCellElement | null;
            const table = cell?.closest('table') as HTMLTableElement | null;

            if (cell && table && isNegotiationTable(table)) {
                const row = cell.closest('tr');
                if (row && row.cells.length === 8) {
                    const idx = cell.cellIndex;
                    // Harga Satuan Sebelum (4) or Harga Satuan Setelah (6)
                    if (idx === 4 || idx === 6) {
                        formatCellAsRupiahWithCaret(cell);
                        recalculateNegotiationTable(table, null);
                    } else if (idx === 2) {
                        // Volume
                        recalculateNegotiationTable(table, cell);
                    } else {
                        recalculateNegotiationTable(table, cell);
                    }
                } else {
                    recalculateNegotiationTable(table, cell);
                }
            }
        }

        lastEmitted.current = area.current.innerHTML;
        onChange(lastEmitted.current);
    }, [onChange]);

    /** The element the caret sits in, or null when it is outside the area. */
    const caretElement = useCallback((): HTMLElement | null => {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0 || !area.current) {
            return null;
        }

        const node = selection.getRangeAt(0).startContainer;
        const element =
            node.nodeType === Node.TEXT_NODE
                ? node.parentElement
                : (node as HTMLElement);

        return element && area.current.contains(element) ? element : null;
    }, []);

    const updateTableMenuPosition = useCallback(() => {
        const element = caretElement();
        const cell = (element?.closest('td, th') as HTMLTableCellElement) ?? null;
        const container = scrollContainer.current;

        if (!cell || !container) {
            setTableMenuPos(null);

            return;
        }

        const cellRect = cell.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();

        const relTop =
            cellRect.top - containerRect.top + container.scrollTop - 40;
        const relLeft =
            cellRect.left - containerRect.left + container.scrollLeft;

        const minTop = container.scrollTop + 8;
        const maxTop = container.scrollTop + containerRect.height - 48;
        const top = Math.min(maxTop, Math.max(minTop, relTop));
        const left = Math.max(12, Math.min(containerRect.width - 340, relLeft));

        setTableMenuPos({ top, left });
    }, [caretElement]);

    // Track what the caret is sitting in so the toolbar can reflect it.
    useEffect(() => {
        const onSelectionChange = () => {
            const element = caretElement();
            const cell = (element?.closest('td, th') as HTMLTableCellElement) ?? null;
            const isInTable = cell !== null;

            // When navigating away from a cell in a negotiation table, ensure price/volume is formatted
            if (lastActiveCell.current && lastActiveCell.current !== cell) {
                const prevCell = lastActiveCell.current;
                const prevTable = prevCell.closest('table') as HTMLTableElement | null;
                if (prevTable && isNegotiationTable(prevTable)) {
                    const row = prevCell.closest('tr');
                    if (row && row.cells.length === 8) {
                        const idx = prevCell.cellIndex;
                        if (idx === 4 || idx === 6) {
                            const raw = parseNegoNumber(prevCell.innerText);
                            if (raw > 0) {
                                prevCell.innerText = formatRupiahDisplay(raw);
                            }
                        } else if (idx === 2) {
                            const vol = parseNegoVolume(prevCell.innerText);
                            if (vol > 0) {
                                prevCell.innerText = String(vol);
                            }
                        }
                    }
                    recalculateNegotiationTable(prevTable, null);
                    emit();
                }
            }
            lastActiveCell.current = cell;

            setInTable(isInTable);

            const block = element?.closest('h1, h2, h3, h4, p');
            setBlockStyle(block ? block.tagName.toLowerCase() : 'p');

            if (isInTable) {
                updateTableMenuPosition();
            } else {
                setTableMenuPos(null);
            }
        };

        document.addEventListener('selectionchange', onSelectionChange);
        window.addEventListener('resize', updateTableMenuPosition);

        return () => {
            document.removeEventListener('selectionchange', onSelectionChange);
            window.removeEventListener('resize', updateTableMenuPosition);
        };
    }, [caretElement, emit, updateTableMenuPosition]);

    /** Run a built-in editing command against the current selection. */
    const run = (command: string, argument?: string) => {
        area.current?.focus();
        document.execCommand(command, false, argument);
        emit();
    };

    /** Drop a prepared fragment of markup in at the caret. */
    const insert = useCallback(
        (html: string) => {
            area.current?.focus();
            document.execCommand('insertHTML', false, html);
            emit();
        },
        [emit],
    );

    const insertPageBreak = () => {
        insert(
            '<div class="page-break" contenteditable="false"></div><p><br></p>',
        );
    };

    /** Insert a table of the given size, ready to type into. */
    const insertTable = (rows: number, columns: number) => {
        const head =
            '<tr>' +
            Array.from(
                { length: columns },
                (_, index) => `<th>Kolom ${index + 1}</th>`,
            ).join('') +
            '</tr>';

        const body = Array.from(
            { length: rows },
            () => '<tr>' + '<td>&nbsp;</td>'.repeat(columns) + '</tr>',
        ).join('');

        insert(`<table>${head}${body}</table><p>&nbsp;</p>`);
    };

    /** The table cell the caret is in, if any. */
    const currentCell = (): HTMLTableCellElement | null =>
        (caretElement()?.closest('td, th') as HTMLTableCellElement) ?? null;

    const addRow = () => {
        const cell = currentCell();
        const row = cell?.closest('tr');
        const table = cell?.closest('table') as HTMLTableElement | null;

        if (!row || !table) {
            return;
        }

        // Negotiation table: insert a fresh 8-cell item row above summary rows
        if (isNegotiationTable(table)) {
            const tbody = table.querySelector('tbody') || table;
            const allRows = Array.from(tbody.querySelectorAll('tr'));

            // Find the first summary row to insert before
            let insertBefore: HTMLTableRowElement | null = null;
            let itemCount = 0;
            for (const r of allRows) {
                const txt = r.innerText.toUpperCase();
                if (
                    txt.includes('TOTAL HARGA') ||
                    txt.includes('DPP') ||
                    txt.includes('PPN') ||
                    txt.includes('JUMLAH TOTAL')
                ) {
                    if (!insertBefore) insertBefore = r;
                } else if (r.cells.length === 8) {
                    itemCount++;
                }
            }

            // Build a clean item row with 8 cells
            const fresh = document.createElement('tr');
            for (let i = 0; i < 8; i++) {
                const td = document.createElement('td');
                td.style.border = '1px solid #000';
                td.style.padding = '5px 3px';
                if (i === 0) {
                    td.style.textAlign = 'center';
                    td.textContent = String(itemCount + 1);
                } else if (i === 1) {
                    td.style.textAlign = 'left';
                    td.style.padding = '5px 4px';
                    td.innerHTML = '&nbsp;';
                } else if (i === 2) {
                    td.style.textAlign = 'center';
                    td.textContent = '1';
                } else if (i === 3) {
                    td.style.textAlign = 'center';
                    td.textContent = 'Lot';
                } else {
                    td.style.textAlign = 'right';
                    td.textContent = '0';
                }
                fresh.appendChild(td);
            }

            if (insertBefore) {
                insertBefore.parentNode?.insertBefore(fresh, insertBefore);
            } else {
                tbody.appendChild(fresh);
            }

            // Recalculate numbering and totals
            recalculateNegotiationTable(table, null);
            emit();
            setTimeout(updateTableMenuPosition, 10);
            return;
        }

        // Default: clone current row for non-negotiation tables
        const fresh = row.cloneNode(true) as HTMLTableRowElement;

        fresh.querySelectorAll('td, th').forEach((clone) => {
            clone.innerHTML = '&nbsp;';
        });

        row.after(fresh);
        emit();
        setTimeout(updateTableMenuPosition, 10);
    };

    const removeRow = () => {
        const row = currentCell()?.closest('tr');
        const table = (row?.closest('table') as HTMLTableElement | null) ?? null;

        // Never leave an empty table behind: removing the last row removes it.
        if (!row || !table) {
            return;
        }

        if (isNegotiationTable(table)) {
            // Guard: DO NOT allow deleting summary rows or header rows!
            const txt = (row.innerText || '').toUpperCase();
            if (
                txt.includes('TOTAL HARGA') ||
                txt.includes('DPP') ||
                txt.includes('PPN') ||
                txt.includes('JUMLAH TOTAL') ||
                row.cells.length !== 8
            ) {
                return;
            }

            // Don't delete if it's the only item row
            const tbody = table.querySelector('tbody') || table;
            const itemRows = Array.from(tbody.querySelectorAll('tr')).filter(
                (r) => r.cells.length === 8 && !r.innerText.toUpperCase().includes('TOTAL')
            );
            if (itemRows.length <= 1) {
                return;
            }

            row.remove();
            recalculateNegotiationTable(table, null);
            emit();
            setTimeout(updateTableMenuPosition, 10);
            return;
        }

        if (table.rows.length <= 1) {
            table.remove();
            setInTable(false);
            setTableMenuPos(null);
        } else {
            row.remove();
            setTimeout(updateTableMenuPosition, 10);
        }

        emit();
    };

    const addColumn = () => {
        const cell = currentCell();
        const table = cell?.closest('table');

        if (!cell || !table) {
            return;
        }

        const index = cell.cellIndex;

        Array.from(table.rows).forEach((row) => {
            const reference = row.cells[index];
            const fresh = document.createElement(
                reference?.tagName === 'TH' ? 'th' : 'td',
            );

            fresh.innerHTML = '&nbsp;';

            if (reference) {
                reference.after(fresh);
            } else {
                row.append(fresh);
            }
        });

        emit();
        setTimeout(updateTableMenuPosition, 10);
    };

    const removeColumn = () => {
        const cell = currentCell();
        const table = cell?.closest('table');

        if (!cell || !table) {
            return;
        }

        const index = cell.cellIndex;

        if (table.rows[0]?.cells.length <= 1) {
            table.remove();
            setInTable(false);
            setTableMenuPos(null);
            emit();

            return;
        }

        Array.from(table.rows).forEach((row) => row.cells[index]?.remove());
        emit();
        setTimeout(updateTableMenuPosition, 10);
    };

    /** Force-recalculate the negotiation table the caret is currently in. */
    const recalculateCurrentTable = () => {
        const cell = currentCell();
        const table = cell?.closest('table') as HTMLTableElement | null;
        if (!table || !isNegotiationTable(table)) return;

        // Format all price cells as Rupiah first
        const tbody = table.querySelector('tbody') || table;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        for (const r of rows) {
            if (r.cells.length !== 8) continue;
            // Price columns: 4 (Harga Satuan Sebelum) and 6 (Harga Satuan Setelah)
            for (const idx of [4, 6]) {
                const raw = parseNegoNumber(r.cells[idx].innerText);
                if (raw > 0) {
                    r.cells[idx].innerText = formatRupiahDisplay(raw);
                }
            }
        }

        recalculateNegotiationTable(table, null);
        emit();
    };

    return (
        <div className="flex flex-col rounded-md border border-border bg-card shadow-sm">
            {/* Toolbar header: pinned at the top */}
            <div className="sticky top-0 z-30 flex flex-col gap-1.5 border-b border-border bg-card/95 backdrop-blur px-3 py-2">
                <div className="flex flex-wrap items-center gap-1">
                    <Select
                        value={blockStyle}
                        onValueChange={(next) => run('formatBlock', next)}
                    >
                        <SelectTrigger className="h-8 w-40 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {BLOCK_STYLES.map((style) => (
                                <SelectItem key={style.value} value={style.value}>
                                    {style.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    <ToolButton
                        label="Tebal"
                        icon={<Bold className="size-4" />}
                        onClick={() => run('bold')}
                    />
                    <ToolButton
                        label="Miring"
                        icon={<Italic className="size-4" />}
                        onClick={() => run('italic')}
                    />
                    <ToolButton
                        label="Garis bawah"
                        icon={<Underline className="size-4" />}
                        onClick={() => run('underline')}
                    />

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    <ToolButton
                        label="Daftar bertitik"
                        icon={<List className="size-4" />}
                        onClick={() => run('insertUnorderedList')}
                    />
                    <ToolButton
                        label="Daftar bernomor"
                        icon={<ListOrdered className="size-4" />}
                        onClick={() => run('insertOrderedList')}
                    />

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    <ToolButton
                        label="Sisipkan tabel 3 kolom"
                        icon={<TableIcon className="size-4" />}
                        onClick={() => insertTable(3, 3)}
                    />
                    <ToolButton
                        label="Isian titik-titik"
                        icon={<PenLine className="size-4" />}
                        onClick={() =>
                            insert(
                                '<span class="fill">..........................</span>',
                            )
                        }
                    />
                    <ToolButton
                        label="Halaman baru"
                        icon={<SquareDashed className="size-4" />}
                        onClick={() =>
                            insert(
                                '<section class="bab"><h2 class="bab-heading">JUDUL BAB</h2><p>Isi bab.</p></section>',
                            )
                        }
                    />
                    <ToolButton
                        label="Blok tanda tangan"
                        icon={<Minus className="size-4" />}
                        onClick={() =>
                            insert(
                                '<table class="signature"><tr><td class="role">Jabatan Kiri</td><td class="role">Jabatan Kanan</td></tr>' +
                                    '<tr><td class="space"></td><td class="space"></td></tr>' +
                                    '<tr><td class="name fill">( Nama Jelas )</td><td class="name fill">( Nama Jelas )</td></tr></table>',
                            )
                        }
                    />
                    <ToolButton
                        label="Sisipkan Batas Halaman (Page Break)"
                        icon={<Scissors className="size-4" />}
                        onClick={insertPageBreak}
                    />

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    <ToolButton
                        label="Batalkan"
                        icon={<Undo2 className="size-4" />}
                        onClick={() => run('undo')}
                    />
                    <ToolButton
                        label="Ulangi"
                        icon={<Redo2 className="size-4" />}
                        onClick={() => run('redo')}
                    />
                </div>

                {inTable && (
                    <div className="flex flex-wrap items-center gap-1.5 rounded-md border border-primary/40 bg-primary/10 px-2.5 py-1.5 shadow-sm animate-in fade-in slide-in-from-top-1">
                        <span className="mr-1 text-xs font-semibold text-primary flex items-center gap-1">
                            <TableIcon className="size-3.5" />
                            Aksi Tabel:
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-7 text-xs font-medium"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={addRow}
                        >
                            <Rows3 className="size-3.5 mr-1 text-primary" />
                            Tambah Baris
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-7 text-xs font-medium"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={addColumn}
                        >
                            <Columns3 className="size-3.5 mr-1 text-primary" />
                            Tambah Kolom
                        </Button>
                        <Separator orientation="vertical" className="h-4 mx-1" />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-7 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={removeRow}
                        >
                            <Trash2 className="size-3.5 mr-1" />
                            Hapus Baris
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-7 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={removeColumn}
                        >
                            <Trash2 className="size-3.5 mr-1" />
                            Hapus Kolom
                        </Button>
                        <Separator orientation="vertical" className="h-4 mx-1" />
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-7 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={recalculateCurrentTable}
                            title="Format angka ke Rupiah dan hitung ulang seluruh rumus tabel otomatis"
                        >
                            <Calculator className="size-3.5 mr-1" />
                            Hitung Ulang &amp; Format
                        </Button>
                    </div>
                )}
            </div>

            {/* Scrollable Document Area: ONLY this area scrolls */}
            <div
                ref={scrollContainer}
                onScroll={updateTableMenuPosition}
                className="relative h-[72vh] min-h-[520px] overflow-y-auto bg-muted/25 p-4 sm:p-8"
            >
                {/* Floating Quick Action Menu right at the Table/Cell */}
                {tableMenuPos !== null && inTable && (
                    <div
                        style={{
                            top: `${tableMenuPos.top}px`,
                            left: `${tableMenuPos.left}px`,
                        }}
                        className="absolute z-20 flex items-center gap-1 rounded-lg border border-primary/40 bg-card/95 backdrop-blur px-2 py-1 shadow-lg animate-in fade-in zoom-in-95 text-xs ring-1 ring-black/5"
                    >
                        <span className="font-semibold text-primary px-1 text-xs flex items-center gap-1">
                            <TableIcon className="size-3.5" />
                            Tabel:
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-6 px-2 text-xs font-medium"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={addRow}
                        >
                            <Rows3 className="size-3 mr-1 text-primary" />
                            + Baris
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-6 px-2 text-xs font-medium"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={addColumn}
                        >
                            <Columns3 className="size-3 mr-1 text-primary" />
                            + Kolom
                        </Button>
                        <Separator orientation="vertical" className="h-3.5 mx-0.5" />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-6 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={removeRow}
                        >
                            <Trash2 className="size-3 mr-1" />
                            - Baris
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-6 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={removeColumn}
                        >
                            <Trash2 className="size-3 mr-1" />
                            - Kolom
                        </Button>
                        <Separator orientation="vertical" className="h-3.5 mx-0.5" />
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-6 px-2 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={recalculateCurrentTable}
                            title="Format Rupiah dan hitung ulang rumus"
                        >
                            <Calculator className="size-3 mr-1" />
                            Hitung
                        </Button>
                    </div>
                )}

                {/* Paper Canvas */}
                <div
                    ref={paperContainer}
                    className={`relative mx-auto w-full ${isLandscape ? 'max-w-[297mm]' : 'max-w-[210mm]'} min-h-[65vh] rounded-sm border border-border bg-white p-6 sm:p-12 shadow-sm focus-within:ring-2 focus-within:ring-primary/40`}
                >
                    <div
                        ref={area}
                        contentEditable={!disabled}
                        suppressContentEditableWarning
                        role="textbox"
                        aria-multiline="true"
                        aria-label="Isi dokumen"
                        spellCheck={false}
                        onInput={emit}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                const cell = currentCell();
                                if (cell) {
                                    event.preventDefault();
                                    const nextCell = (cell.nextElementSibling as HTMLTableCellElement) ?? null;
                                    if (nextCell) {
                                        const range = document.createRange();
                                        range.selectNodeContents(nextCell);
                                        range.collapse(false);
                                        const sel = window.getSelection();
                                        sel?.removeAllRanges();
                                        sel?.addRange(range);
                                    }
                                }
                            } else if (event.key === 'Tab') {
                                const cell = currentCell();
                                if (cell) {
                                    event.preventDefault();
                                    const row = cell.closest('tr');
                                    let targetCell: HTMLTableCellElement | null = null;
                                    if (event.shiftKey) {
                                        targetCell = (cell.previousElementSibling as HTMLTableCellElement) ?? null;
                                        if (!targetCell && row?.previousElementSibling) {
                                            const prevRowCells = (row.previousElementSibling as HTMLTableRowElement).cells;
                                            targetCell = prevRowCells[prevRowCells.length - 1] ?? null;
                                        }
                                    } else {
                                        targetCell = (cell.nextElementSibling as HTMLTableCellElement) ?? null;
                                        if (!targetCell && row?.nextElementSibling) {
                                            targetCell = (row.nextElementSibling as HTMLTableRowElement).cells[0] ?? null;
                                        }
                                    }
                                    if (targetCell) {
                                        const range = document.createRange();
                                        range.selectNodeContents(targetCell);
                                        range.collapse(false);
                                        const sel = window.getSelection();
                                        sel?.removeAllRanges();
                                        sel?.addRange(range);
                                    }
                                }
                            }
                        }}
                        onBlur={() => {
                            if (lastActiveCell.current) {
                                const prevCell = lastActiveCell.current;
                                const prevTable = prevCell.closest('table') as HTMLTableElement | null;
                                if (prevTable && isNegotiationTable(prevTable)) {
                                    const row = prevCell.closest('tr');
                                    if (row && row.cells.length === 8) {
                                        const idx = prevCell.cellIndex;
                                        if (idx === 4 || idx === 6) {
                                            const raw = parseNegoNumber(prevCell.innerText);
                                            if (raw > 0) {
                                                prevCell.innerText = formatRupiahDisplay(raw);
                                            }
                                        }
                                    }
                                    recalculateNegotiationTable(prevTable, null);
                                }
                                lastActiveCell.current = null;
                            }
                            emit();
                        }}
                        onPaste={(event) => {
                            event.preventDefault();
                            const text = event.clipboardData.getData('text/plain');
                            document.execCommand('insertText', false, text);
                            emit();
                        }}
                        className="document-preview min-h-[60vh] outline-none"
                    />
                </div>
            </div>
        </div>
    );
}

function ToolButton({
    label,
    icon,
    onClick,
}: {
    label: string;
    icon: React.ReactNode;
    onClick: () => void;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="size-8 p-0"
                    // Keep the caret where it is: the button must not steal focus.
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={onClick}
                    aria-label={label}
                >
                    {icon}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
