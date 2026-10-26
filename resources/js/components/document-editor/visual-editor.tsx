import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    AlignVerticalJustifyCenter,
    AlignVerticalJustifyEnd,
    AlignVerticalJustifyStart,
    Baseline,
    Bold,
    Highlighter,
    IndentDecrease,
    IndentIncrease,
    RemoveFormatting,
    Strikethrough,
    Subscript,
    Superscript,
    Calculator,
    Columns3,
    FileCog,
    Italic,
    List,
    ListOrdered,
    Maximize2,
    Minimize2,
    Minus,
    PenLine,
    Redo2,
    Rows3,
    Scissors,
    SquareDashed,
    Table as TableIcon,
    Trash2,
    Underline,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import { useColumnResize } from '@/components/document-editor/column-resize';
import {
    PageBoundaries,
    withoutSheetMarks,
} from '@/components/document-editor/page-boundaries';
import { PageSetupDialog } from '@/components/document-editor/page-setup-dialog';
import {
    useDocumentZoom,
    useWheelZoom,
    ZoomControls,
} from '@/components/document-editor/zoom-controls';
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
import {
    describePageSetup,
    hasPageSetup,
    paperDimensions,
    PX_PER_MM,
    readPageSetup,
    withoutPageSetup,
    withPageSetup,
} from '@/lib/page-setup';
import type { PageSetup } from '@/lib/page-setup';
import { cn } from '@/lib/utils';

/** Where the text of a table cell sits, top to bottom. */
type CellAlign = 'top' | 'middle' | 'bottom';

const CELL_ALIGNS: {
    value: CellAlign;
    label: string;
    icon: React.ReactNode;
}[] = [
    {
        value: 'top',
        label: 'Teks sel di atas',
        icon: <AlignVerticalJustifyStart className="size-3.5" />,
    },
    {
        value: 'middle',
        label: 'Teks sel di tengah',
        icon: <AlignVerticalJustifyCenter className="size-3.5" />,
    },
    {
        value: 'bottom',
        label: 'Teks sel di bawah',
        icon: <AlignVerticalJustifyEnd className="size-3.5" />,
    },
];

/** Where the text of a cell sits now, as the page draws it. */
function cellAlignOf(cell: HTMLTableCellElement): CellAlign | null {
    const align = window.getComputedStyle(cell).verticalAlign;

    return align === 'top' || align === 'middle' || align === 'bottom'
        ? align
        : null;
}

/** The height of the desk drawn between two sheets, in px. */
const DESK_GAP = 32;

/** The block styles an author picks from, in their own words. */
/** A tab's worth of space, kept as non-breaking spaces so the PDF keeps it. */
const TAB_SPACE = '\u00a0'.repeat(8);

/** The commands whose result is written as an inline style. */
const STYLED_COMMANDS = ['fontSize', 'foreColor', 'hiliteColor'];

/** The font sizes offered, as the editor's 1–7 scale. */
const FONT_SIZES = [
    { value: '1', label: '8 pt' },
    { value: '2', label: '10 pt' },
    { value: '3', label: '12 pt' },
    { value: '4', label: '14 pt' },
    { value: '5', label: '18 pt' },
    { value: '6', label: '24 pt' },
];

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
        (text.includes('HARGA SEBELUM NEGO') ||
            text.includes('SEBELUM NEGO')) &&
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
                const offset = Math.min(
                    targetOffset,
                    textNode.textContent?.length || 0,
                );
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

/**
 * A group heading ("A | Fabrikasi Material") or blank spacer row: it has no
 * volume and no prices, so it is neither numbered nor totalled.
 */
function isNegotiationLayoutRow(row: HTMLTableRowElement): boolean {
    return [2, 4, 5, 6, 7].every(
        (index) =>
            (row.cells[index]?.innerText ?? '').replace(/[^0-9]/g, '') === '',
    );
}

function recalculateNegotiationTable(
    table: HTMLTableElement,
    activeCell?: HTMLElement | null,
) {
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
        } else if (r.cells.length === 8 && !isNegotiationLayoutRow(r)) {
            itemRows.push(r);
        }
    }

    if (itemRows.length === 0) {
        return;
    }

    let sumTotalBefore = 0;
    let sumTotalAfter = 0;

    itemRows.forEach((row, idx) => {
        const cells = row.cells;

        if (cells.length < 8) {
            return;
        }

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
            cells[5].innerText =
                totalBefore > 0 ? formatRupiahDisplay(totalBefore) : '0';
        }

        // Update Jumlah Harga Setelah (cell 7)
        if (cells[7] !== activeCell) {
            cells[7].innerText =
                totalAfter > 0 ? formatRupiahDisplay(totalAfter) : '0';
        }

        sumTotalBefore += totalBefore;
        sumTotalAfter += totalAfter;
    });

    const dppBefore = Math.round((sumTotalBefore * 11) / 12);
    const dppAfter = Math.round((sumTotalAfter * 11) / 12);

    const ppnBefore = Math.round(sumTotalBefore * 0.12);
    const ppnAfter = Math.round(sumTotalAfter * 0.12);

    const grandBefore = sumTotalBefore + ppnBefore;
    const grandAfter = sumTotalAfter + ppnAfter;

    // Update TOTAL HARGA
    if (rowTotal && rowTotal.cells.length >= 3) {
        const cBefore = rowTotal.cells[rowTotal.cells.length - 2];
        const cAfter = rowTotal.cells[rowTotal.cells.length - 1];

        if (cBefore !== activeCell) {
            cBefore.innerText = formatRupiahDisplay(sumTotalBefore);
        }

        if (cAfter !== activeCell) {
            cAfter.innerText = formatRupiahDisplay(sumTotalAfter);
        }
    }

    // Update DPP 11/12
    if (rowDpp && rowDpp.cells.length >= 3) {
        const cBefore = rowDpp.cells[rowDpp.cells.length - 2];
        const cAfter = rowDpp.cells[rowDpp.cells.length - 1];

        if (cBefore !== activeCell) {
            cBefore.innerText = formatRupiahDisplay(dppBefore);
        }

        if (cAfter !== activeCell) {
            cAfter.innerText = formatRupiahDisplay(dppAfter);
        }
    }

    // Update PPN 12%
    if (rowPpn && rowPpn.cells.length >= 3) {
        const cBefore = rowPpn.cells[rowPpn.cells.length - 2];
        const cAfter = rowPpn.cells[rowPpn.cells.length - 1];

        if (cBefore !== activeCell) {
            cBefore.innerText = formatRupiahDisplay(ppnBefore);
        }

        if (cAfter !== activeCell) {
            cAfter.innerText = formatRupiahDisplay(ppnAfter);
        }
    }

    // Update JUMLAH TOTAL
    if (rowGrand && rowGrand.cells.length >= 3) {
        const cBefore = rowGrand.cells[rowGrand.cells.length - 2];
        const cAfter = rowGrand.cells[rowGrand.cells.length - 1];

        if (cBefore !== activeCell) {
            cBefore.innerText = formatRupiahDisplay(grandBefore);
        }

        if (cAfter !== activeCell) {
            cAfter.innerText = formatRupiahDisplay(grandAfter);
        }
    }
}

function isSuratPesananTable(table: HTMLTableElement): boolean {
    const text = (table.innerText || '').toUpperCase();

    return (
        text.includes('BATAS WAKTU') &&
        (text.includes('PENYERAHAN') || text.includes('PENYELESAIAN')) &&
        (text.includes('PERHATIAN') || text.includes('TOTAL'))
    );
}

function recalculateSuratPesananTable(
    table: HTMLTableElement,
    activeCell?: HTMLElement | null,
) {
    const tbody = table.querySelector('tbody') || table;
    const rows = Array.from(tbody.querySelectorAll('tr'));

    const itemRows: HTMLTableRowElement[] = [];
    let rowTotal: HTMLTableRowElement | null = null;

    for (const r of rows) {
        const text = r.innerText.toUpperCase();

        if (text.includes('PERHATIAN') && text.includes('TOTAL')) {
            rowTotal = r;
        } else if (
            (r.cells.length === 7 || r.cells.length === 6) &&
            !text.includes('NOTE') &&
            !text.includes('PERHATIAN') &&
            !text.includes('TERBILANG')
        ) {
            let hasColspan = false;

            for (let i = 0; i < Math.min(6, r.cells.length); i++) {
                if (r.cells[i].colSpan > 1) {
                    hasColspan = true;
                    break;
                }
            }

            if (!hasColspan) {
                itemRows.push(r);
            }
        }
    }

    if (itemRows.length === 0) {
        return;
    }

    // Ensure deadline column is merged across all item rows
    const firstRow = itemRows[0];

    if (firstRow) {
        // Remove redundant cell 6 on rows 1..N if they have 7 cells
        for (let k = 1; k < itemRows.length; k++) {
            if (itemRows[k].cells.length >= 7) {
                itemRows[k].deleteCell(6);
            }
        }

        // Set rowspan on cell 6 of first row
        if (firstRow.cells.length >= 7) {
            if (itemRows.length > 1) {
                firstRow.cells[6].rowSpan = itemRows.length;
            } else {
                firstRow.cells[6].removeAttribute('rowspan');
            }
        }
    }

    let sumTotal = 0;

    itemRows.forEach((row, idx) => {
        const cells = row.cells;

        if (cells.length < 6) {
            return;
        }

        // Auto re-number NO
        if (cells[0] !== activeCell) {
            cells[0].innerText = String(idx + 1);
        }

        const vol = parseNegoVolume(cells[2].innerText);
        const price = parseNegoNumber(cells[4].innerText);
        const total = Math.round(vol * price);

        if (cells[5] !== activeCell && price > 0) {
            cells[5].innerText = 'Rp ' + formatRupiahDisplay(total);
        }

        sumTotal += total > 0 ? total : parseNegoNumber(cells[5].innerText);
    });

    if (rowTotal && sumTotal > 0) {
        const totalCell = rowTotal.cells.length >= 3 ? rowTotal.cells[2] : null;

        if (totalCell && totalCell !== activeCell) {
            totalCell.innerText = 'Rp ' + formatRupiahDisplay(sumTotal);
        }
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
    pagesEndpoint,
    title = '',
    tableTools = true,
}: {
    value: string;
    onChange: (html: string) => void;
    disabled?: boolean;
    /** Where the PDF engine reports the printed sheets of the draft. */
    pagesEndpoint?: string;
    title?: string;
    /**
     * The table actions (insert a table, add or remove rows and columns,
     * recalculate). Off where the table is filled from the Rincian Item,
     * so a row is never added in two places.
     */
    tableTools?: boolean;
}) {
    const area = useRef<HTMLDivElement>(null);
    const scrollContainer = useRef<HTMLDivElement>(null);
    const paperContainer = useRef<HTMLDivElement>(null);
    // The last selection in the page, put back when a control (the colour
    // picker) took the focus away before its command runs.
    const savedRange = useRef<Range | null>(null);

    useEffect(() => {
        const remember = () => {
            const selection = window.getSelection();

            if (
                selection &&
                selection.rangeCount > 0 &&
                area.current?.contains(selection.anchorNode)
            ) {
                savedRange.current = selection.getRangeAt(0).cloneRange();
            }
        };

        document.addEventListener('selectionchange', remember);

        return () => document.removeEventListener('selectionchange', remember);
    }, []);
    const lastEmitted = useRef(value);
    const seeded = useRef(false);
    const [inTable, setInTable] = useState(false);
    const [cellAlign, setCellAlign] = useState<CellAlign | null>(null);
    const [blockStyle, setBlockStyle] = useState('p');

    // The paper, orientation and margins the document prints with. A setup
    // chosen here travels in the body as one @page block, kept out of the
    // editable area and written back on every change.
    const setup = useMemo(() => readPageSetup(value), [value]);
    const isCustomSetup = useMemo(() => hasPageSetup(value), [value]);
    const chosenSetup = useRef<PageSetup | null>(isCustomSetup ? setup : null);
    const [setupOpen, setSetupOpen] = useState(false);
    const [fullscreen, setFullscreen] = useState(false);

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
            const content = withoutPageSetup(value);
            chosenSetup.current = hasPageSetup(value)
                ? readPageSetup(value)
                : null;
            // An empty body leaves nowhere to put the caret, so give the author
            // a paragraph to start typing in.
            area.current.innerHTML =
                content.trim() === '' ? '<p><br></p>' : content;
            area.current.querySelectorAll('.page-break').forEach((el) => {
                el.setAttribute('contenteditable', 'false');
            });
            lastEmitted.current = value;
            seeded.current = true;
        }
    }, [value]);

    const [tableMenuPos, setTableMenuPos] = useState<{
        top: number;
        left: number;
    } | null>(null);
    const lastActiveCell = useRef<HTMLTableCellElement | null>(null);

    const emit = useCallback(() => {
        if (!area.current) {
            return;
        }

        // If the caret is in a negotiation table, handle price formatting & formula recalculation
        const sel = window.getSelection();

        if (sel && sel.rangeCount > 0) {
            const node = sel.getRangeAt(0).startContainer;
            const element =
                node.nodeType === Node.TEXT_NODE
                    ? node.parentElement
                    : (node as HTMLElement);
            const cell = element?.closest(
                'td, th',
            ) as HTMLTableCellElement | null;
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
            } else if (cell && table && isSuratPesananTable(table)) {
                const row = cell.closest('tr');

                if (row && (row.cells.length === 7 || row.cells.length === 6)) {
                    recalculateSuratPesananTable(table, cell);
                }
            }
        }

        lastEmitted.current = withPageSetup(
            withoutSheetMarks(area.current.innerHTML),
            chosenSetup.current,
        );
        onChange(lastEmitted.current);
    }, [onChange]);

    // A column line dragged, as in Word: one column wider, the next narrower.
    useColumnResize(() => area.current, !disabled, emit);

    /** Lay the document on another paper or margins; null: the template's. */
    const applySetup = (next: PageSetup | null) => {
        chosenSetup.current = next;
        emit();
    };

    // The sheet on screen: its paper and margins in millimetres, scaled by
    // the zoom. The scaling is a transform, so the layout (and the printed
    // sheets laid over it) stays that of the real paper.
    const paper = paperDimensions(setup);
    const { zoom, fit, setZoom, fitWidth, zoomIn, zoomOut } = useDocumentZoom(
        scrollContainer,
        paper.width * PX_PER_MM,
    );
    useWheelZoom(scrollContainer, zoom, setZoom);

    const [sheetHeight, setSheetHeight] = useState(0);

    useEffect(() => {
        const element = paperContainer.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(() =>
            setSheetHeight(element.offsetHeight),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const sheetGap =
        (setup.margins.top + setup.margins.bottom) * PX_PER_MM + DESK_GAP;

    const sheetStyle = {
        width: `${paper.width}mm`,
        minHeight: `${paper.height}mm`,
        padding: `${setup.margins.top}mm ${setup.margins.right}mm ${setup.margins.bottom}mm ${setup.margins.left}mm`,
        transform: zoom === 1 ? undefined : `scale(${zoom})`,
        transformOrigin: 'top left',
        '--page-margin-top': `${setup.margins.top}mm`,
        '--page-margin-right': `${setup.margins.right}mm`,
        '--page-margin-bottom': `${setup.margins.bottom}mm`,
        '--page-margin-left': `${setup.margins.left}mm`,
        '--sheet-gap': `${sheetGap}px`,
    } as CSSProperties;

    // Full screen gives the whole window to the page, as Word's focus mode;
    // Escape leaves it.
    useEffect(() => {
        if (!fullscreen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !event.defaultPrevented) {
                setFullscreen(false);
            }
        };
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = overflow;
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [fullscreen]);

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
        const cell =
            (element?.closest('td, th') as HTMLTableCellElement) ?? null;
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
            const cell =
                (element?.closest('td, th') as HTMLTableCellElement) ?? null;
            const isInTable = cell !== null;

            // When navigating away from a cell in a negotiation table, ensure price/volume is formatted
            if (lastActiveCell.current && lastActiveCell.current !== cell) {
                const prevCell = lastActiveCell.current;
                const prevTable = prevCell.closest(
                    'table',
                ) as HTMLTableElement | null;

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
            setCellAlign(cell ? cellAlignOf(cell) : null);

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
        const focused = document.activeElement === area.current;
        area.current?.focus();

        if (!focused && savedRange.current) {
            const selection = window.getSelection();
            selection?.removeAllRanges();
            selection?.addRange(savedRange.current);
        }

        // Sizes and colours are written as inline styles, which the PDF
        // renderer reads, rather than as <font> tags.
        document.execCommand(
            'styleWithCSS',
            false,
            String(STYLED_COMMANDS.includes(command)),
        );
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
                    if (!insertBefore) {
                        insertBefore = r;
                    }
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

        // Surat Pesanan table: insert a fresh item row above summary rows (NOTE / PERHATIAN / TOTAL)
        if (isSuratPesananTable(table)) {
            const tbody = table.querySelector('tbody') || table;
            const allRows = Array.from(tbody.querySelectorAll('tr'));

            let insertBefore: HTMLTableRowElement | null = null;
            let itemCount = 0;
            let prevDeadline = '';
            let prevSatuan = 'Lot';

            for (const r of allRows) {
                const txt = r.innerText.toUpperCase();

                if (
                    txt.includes('NOTE') ||
                    txt.includes('PERHATIAN') ||
                    txt.includes('TOTAL') ||
                    txt.includes('TERBILANG')
                ) {
                    if (!insertBefore) {
                        insertBefore = r;
                    }
                } else if (r.cells.length === 7 || r.cells.length === 6) {
                    itemCount++;

                    // Extract deadline date from cell 6 (BATAS WAKTU / PENYERAHAN BARANG / JASA) if present
                    if (r.cells.length >= 7) {
                        const deadlineText =
                            r.cells[6]?.innerText?.trim() || '';

                        if (deadlineText && deadlineText !== '&nbsp;') {
                            prevDeadline = deadlineText;
                        }
                    }

                    const satuanText = r.cells[3]?.innerText?.trim() || '';

                    if (satuanText && satuanText !== '&nbsp;') {
                        prevSatuan = satuanText;
                    }
                }
            }

            // Build a clean item row
            // If itemCount === 0, create 7 cells. Otherwise 6 cells (deadline is merged with row 0).
            const cellCount = itemCount === 0 ? 7 : 6;
            const fresh = document.createElement('tr');

            for (let i = 0; i < cellCount; i++) {
                const td = document.createElement('td');
                td.style.border = '1px solid #000';

                if (i === 0) {
                    td.style.padding = '8px 4px';
                    td.style.textAlign = 'center';
                    td.style.verticalAlign = 'middle';
                    td.textContent = String(itemCount + 1);
                } else if (i === 1) {
                    td.style.padding = '8px 6px';
                    td.style.textAlign = 'left';
                    td.style.verticalAlign = 'middle';
                    td.innerHTML = '&nbsp;';
                } else if (i === 2) {
                    td.style.padding = '8px 4px';
                    td.style.textAlign = 'center';
                    td.style.verticalAlign = 'middle';
                    td.textContent = '1';
                } else if (i === 3) {
                    td.style.padding = '8px 4px';
                    td.style.textAlign = 'center';
                    td.style.verticalAlign = 'middle';
                    td.textContent = prevSatuan;
                } else if (i === 4) {
                    td.style.padding = '8px 6px';
                    td.style.textAlign = 'right';
                    td.style.verticalAlign = 'middle';
                    td.textContent = 'Rp 0';
                } else if (i === 5) {
                    td.style.padding = '8px 6px';
                    td.style.textAlign = 'right';
                    td.style.verticalAlign = 'middle';
                    td.textContent = 'Rp 0';
                } else if (i === 6) {
                    td.style.padding = '8px 6px';
                    td.style.textAlign = 'center';
                    td.style.verticalAlign = 'middle';
                    td.style.fontWeight = 'bold';
                    // Muncul tanggal batas penyerahan yang sama seperti item sebelumnya!
                    td.textContent =
                        prevDeadline || '{{tanggal_selesai_pelaksanaan}}';
                }

                fresh.appendChild(td);
            }

            if (insertBefore) {
                insertBefore.parentNode?.insertBefore(fresh, insertBefore);
            } else {
                tbody.appendChild(fresh);
            }

            recalculateSuratPesananTable(table, null);
            emit();
            setTimeout(updateTableMenuPosition, 10);

            return;
        }

        // Default: clone current row for other non-negotiation tables
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
        const table =
            (row?.closest('table') as HTMLTableElement | null) ?? null;

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
                (r) =>
                    r.cells.length === 8 &&
                    !r.innerText.toUpperCase().includes('TOTAL'),
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

        if (isSuratPesananTable(table)) {
            // Guard: DO NOT allow deleting summary / note rows or header rows!
            const txt = (row.innerText || '').toUpperCase();

            if (
                txt.includes('NOTE') ||
                txt.includes('PERHATIAN') ||
                txt.includes('TOTAL') ||
                txt.includes('TERBILANG') ||
                (row.cells.length !== 7 && row.cells.length !== 6)
            ) {
                return;
            }

            // Don't delete if it's the only item row
            const tbody = table.querySelector('tbody') || table;
            const itemRows = Array.from(tbody.querySelectorAll('tr')).filter(
                (r) =>
                    (r.cells.length === 7 || r.cells.length === 6) &&
                    !r.innerText.toUpperCase().includes('TOTAL') &&
                    !r.innerText.toUpperCase().includes('NOTE') &&
                    !r.innerText.toUpperCase().includes('PERHATIAN') &&
                    !r.innerText.toUpperCase().includes('TERBILANG'),
            );

            if (itemRows.length <= 1) {
                return;
            }

            // If we are deleting the first item row (which holds cell 6 with the merged deadline),
            // we must transfer cell 6 to the next item row before deleting!
            if (row === itemRows[0] && row.cells.length >= 7 && itemRows[1]) {
                const deadlineCell = row.cells[6];
                itemRows[1].appendChild(deadlineCell);
            }

            row.remove();
            recalculateSuratPesananTable(table, null);
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

    /**
     * The cells to act on: every cell the selection touches within the
     * caret's table, or just the caret's cell.
     */
    const selectedCells = (): HTMLTableCellElement[] => {
        const cell = currentCell();
        const table = cell?.closest('table');
        const selection = window.getSelection();

        if (!cell || !table) {
            return [];
        }

        if (!selection || selection.isCollapsed) {
            return [cell];
        }

        const touched = Array.from(
            table.querySelectorAll<HTMLTableCellElement>('td, th'),
        ).filter(
            (candidate) =>
                candidate.closest('table') === table &&
                selection.containsNode(candidate, true),
        );

        return touched.length > 0 ? touched : [cell];
    };

    /** Set the text of the selected cells at the top, middle or bottom. */
    const alignCells = (align: CellAlign) => {
        const cells = selectedCells();

        if (cells.length === 0) {
            return;
        }

        cells.forEach((cell) => {
            cell.style.verticalAlign = align;
        });
        setCellAlign(align);
        emit();
    };

    /** Force-recalculate the table the caret is currently in. */
    const recalculateCurrentTable = () => {
        const cell = currentCell();
        const table = cell?.closest('table') as HTMLTableElement | null;

        if (!table) {
            return;
        }

        if (isNegotiationTable(table)) {
            // Format all price cells as Rupiah first
            const tbody = table.querySelector('tbody') || table;
            const rows = Array.from(tbody.querySelectorAll('tr'));

            for (const r of rows) {
                if (r.cells.length !== 8) {
                    continue;
                }

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
        } else if (isSuratPesananTable(table)) {
            recalculateSuratPesananTable(table, null);
            emit();
        }
    };

    return (
        <div
            className={cn(
                'flex flex-col border border-border bg-card shadow-sm',
                fullscreen
                    ? 'fixed inset-0 z-50 rounded-none border-0'
                    : 'rounded-md',
            )}
        >
            {/* Toolbar header: pinned at the top */}
            <div className="sticky top-0 z-30 flex flex-col gap-1.5 border-b border-border bg-card/95 px-3 py-2 backdrop-blur">
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
                                <SelectItem
                                    key={style.value}
                                    value={style.value}
                                >
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
                    <ToolButton
                        label="Coret"
                        icon={<Strikethrough className="size-4" />}
                        onClick={() => run('strikeThrough')}
                    />
                    <ToolButton
                        label="Superskrip"
                        icon={<Superscript className="size-4" />}
                        onClick={() => run('superscript')}
                    />
                    <ToolButton
                        label="Subskrip"
                        icon={<Subscript className="size-4" />}
                        onClick={() => run('subscript')}
                    />

                    <Select onValueChange={(next) => run('fontSize', next)}>
                        <SelectTrigger
                            className="h-8 w-24 text-xs"
                            title="Ukuran huruf"
                        >
                            <SelectValue placeholder="Ukuran" />
                        </SelectTrigger>
                        <SelectContent>
                            {FONT_SIZES.map((size) => (
                                <SelectItem key={size.value} value={size.value}>
                                    {size.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <label className="relative inline-flex size-8 cursor-pointer items-center justify-center rounded-md hover:bg-accent">
                                <Baseline className="size-4" />
                                <input
                                    type="color"
                                    aria-label="Warna huruf"
                                    className="absolute inset-0 cursor-pointer opacity-0"
                                    onMouseDown={(e) => e.stopPropagation()}
                                    onChange={(event) =>
                                        run('foreColor', event.target.value)
                                    }
                                />
                            </label>
                        </TooltipTrigger>
                        <TooltipContent>Warna huruf</TooltipContent>
                    </Tooltip>
                    <ToolButton
                        label="Stabilo kuning"
                        icon={<Highlighter className="size-4" />}
                        onClick={() => run('hiliteColor', '#fff59d')}
                    />
                    <ToolButton
                        label="Hapus format"
                        icon={<RemoveFormatting className="size-4" />}
                        onClick={() => run('removeFormat')}
                    />

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    <ToolButton
                        label="Rata kiri"
                        icon={<AlignLeft className="size-4" />}
                        onClick={() => run('justifyLeft')}
                    />
                    <ToolButton
                        label="Rata tengah"
                        icon={<AlignCenter className="size-4" />}
                        onClick={() => run('justifyCenter')}
                    />
                    <ToolButton
                        label="Rata kanan"
                        icon={<AlignRight className="size-4" />}
                        onClick={() => run('justifyRight')}
                    />
                    <ToolButton
                        label="Rata kiri-kanan"
                        icon={<AlignJustify className="size-4" />}
                        onClick={() => run('justifyFull')}
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
                    <ToolButton
                        label="Kurangi indentasi (Shift+Tab)"
                        icon={<IndentDecrease className="size-4" />}
                        onClick={() => run('outdent')}
                    />
                    <ToolButton
                        label="Tambah indentasi (Tab)"
                        icon={<IndentIncrease className="size-4" />}
                        onClick={() => run('indent')}
                    />

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    {tableTools && (
                        <ToolButton
                            label="Sisipkan tabel 3 kolom"
                            icon={<TableIcon className="size-4" />}
                            onClick={() => insertTable(3, 3)}
                        />
                    )}
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

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    {!disabled && (
                        <ToolButton
                            label="Pengaturan halaman (kertas & margin)"
                            icon={<FileCog className="size-4" />}
                            onClick={() => setSetupOpen(true)}
                        />
                    )}
                    <ToolButton
                        label={
                            fullscreen
                                ? 'Keluar layar penuh (Esc)'
                                : 'Layar penuh'
                        }
                        icon={
                            fullscreen ? (
                                <Minimize2 className="size-4" />
                            ) : (
                                <Maximize2 className="size-4" />
                            )
                        }
                        onClick={() => setFullscreen((current) => !current)}
                    />
                </div>

                {tableTools && inTable && (
                    <div className="flex animate-in flex-wrap items-center gap-1.5 rounded-md border border-primary/40 bg-primary/10 px-2.5 py-1.5 shadow-sm fade-in slide-in-from-top-1">
                        <span className="mr-1 flex items-center gap-1 text-xs font-semibold text-primary">
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
                            <Rows3 className="mr-1 size-3.5 text-primary" />
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
                            <Columns3 className="mr-1 size-3.5 text-primary" />
                            Tambah Kolom
                        </Button>
                        <Separator
                            orientation="vertical"
                            className="mx-1 h-4"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-7 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={removeRow}
                        >
                            <Trash2 className="mr-1 size-3.5" />
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
                            <Trash2 className="mr-1 size-3.5" />
                            Hapus Kolom
                        </Button>
                        <Separator
                            orientation="vertical"
                            className="mx-1 h-4"
                        />
                        <CellAlignButtons
                            current={cellAlign}
                            onAlign={alignCells}
                        />
                        <Separator
                            orientation="vertical"
                            className="mx-1 h-4"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-7 border border-emerald-300 bg-emerald-50 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={recalculateCurrentTable}
                            title="Format angka ke Rupiah dan hitung ulang seluruh rumus tabel otomatis"
                        >
                            <Calculator className="mr-1 size-3.5" />
                            Hitung Ulang &amp; Format
                        </Button>
                    </div>
                )}
            </div>

            {/* Scrollable Document Area: ONLY this area scrolls */}
            <div
                ref={scrollContainer}
                onScroll={updateTableMenuPosition}
                className={cn(
                    'relative overflow-auto bg-muted/40 p-4 sm:p-6',
                    fullscreen
                        ? 'min-h-0 flex-1'
                        : 'h-[calc(100dvh-10rem)] min-h-[480px]',
                )}
            >
                {/* Floating Quick Action Menu right at the Table/Cell */}
                {tableTools && tableMenuPos !== null && inTable && (
                    <div
                        style={{
                            top: `${tableMenuPos.top}px`,
                            left: `${tableMenuPos.left}px`,
                        }}
                        className="absolute z-20 flex animate-in items-center gap-1 rounded-lg border border-primary/40 bg-card/95 px-2 py-1 text-xs shadow-lg ring-1 ring-black/5 backdrop-blur zoom-in-95 fade-in"
                    >
                        <span className="flex items-center gap-1 px-1 text-xs font-semibold text-primary">
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
                            <Rows3 className="mr-1 size-3 text-primary" />+
                            Baris
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-6 px-2 text-xs font-medium"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={addColumn}
                        >
                            <Columns3 className="mr-1 size-3 text-primary" />+
                            Kolom
                        </Button>
                        <Separator
                            orientation="vertical"
                            className="mx-0.5 h-3.5"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-6 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={removeRow}
                        >
                            <Trash2 className="mr-1 size-3" />- Baris
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-6 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={removeColumn}
                        >
                            <Trash2 className="mr-1 size-3" />- Kolom
                        </Button>
                        <Separator
                            orientation="vertical"
                            className="mx-0.5 h-3.5"
                        />
                        <CellAlignButtons
                            current={cellAlign}
                            onAlign={alignCells}
                            compact
                        />
                        <Separator
                            orientation="vertical"
                            className="mx-0.5 h-3.5"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-6 bg-emerald-50 px-2 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={recalculateCurrentTable}
                            title="Format Rupiah dan hitung ulang rumus"
                        >
                            <Calculator className="mr-1 size-3" />
                            Hitung
                        </Button>
                    </div>
                )}

                {/* Paper canvas: the sheet at its real size, scaled by the zoom */}
                <div
                    className="relative mx-auto"
                    style={{
                        width: paper.width * PX_PER_MM * zoom,
                        height:
                            Math.max(sheetHeight, paper.height * PX_PER_MM) *
                            zoom,
                    }}
                >
                    <div
                        ref={paperContainer}
                        style={sheetStyle}
                        className="page-sheet absolute top-0 left-0 box-border rounded-sm border border-border bg-white shadow-md focus-within:ring-2 focus-within:ring-primary/40"
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
                                    // Inside a table cell, Enter starts a new line
                                    // in the same cell, as in Word. Moving between
                                    // cells is what Tab / Shift+Tab are for.
                                    if (currentCell()) {
                                        event.preventDefault();

                                        if (
                                            !document.execCommand(
                                                'insertLineBreak',
                                            )
                                        ) {
                                            document.execCommand(
                                                'insertHTML',
                                                false,
                                                '<br>',
                                            );
                                        }
                                    }
                                } else if (event.key === 'Tab') {
                                    const cell = currentCell();

                                    if (cell) {
                                        event.preventDefault();
                                        const row = cell.closest('tr');
                                        let targetCell: HTMLTableCellElement | null =
                                            null;

                                        if (event.shiftKey) {
                                            targetCell =
                                                (cell.previousElementSibling as HTMLTableCellElement) ??
                                                null;

                                            if (
                                                !targetCell &&
                                                row?.previousElementSibling
                                            ) {
                                                const prevRowCells = (
                                                    row.previousElementSibling as HTMLTableRowElement
                                                ).cells;
                                                targetCell =
                                                    prevRowCells[
                                                        prevRowCells.length - 1
                                                    ] ?? null;
                                            }
                                        } else {
                                            targetCell =
                                                (cell.nextElementSibling as HTMLTableCellElement) ??
                                                null;

                                            if (
                                                !targetCell &&
                                                row?.nextElementSibling
                                            ) {
                                                targetCell =
                                                    (
                                                        row.nextElementSibling as HTMLTableRowElement
                                                    ).cells[0] ?? null;
                                            }
                                        }

                                        if (targetCell) {
                                            const range =
                                                document.createRange();
                                            range.selectNodeContents(
                                                targetCell,
                                            );
                                            range.collapse(false);
                                            const sel = window.getSelection();
                                            sel?.removeAllRanges();
                                            sel?.addRange(range);
                                        }
                                    } else {
                                        // As in Word: Tab never leaves the page.
                                        // In a list it moves the item in or out;
                                        // in running text it puts in a tab's
                                        // worth of space.
                                        event.preventDefault();
                                        const inList = window
                                            .getSelection()
                                            ?.anchorNode?.parentElement?.closest(
                                                'li',
                                            );

                                        if (inList || event.shiftKey) {
                                            run(
                                                event.shiftKey
                                                    ? 'outdent'
                                                    : 'indent',
                                            );
                                        } else {
                                            run('insertText', TAB_SPACE);
                                        }
                                    }
                                }
                            }}
                            onBlur={() => {
                                if (lastActiveCell.current) {
                                    const prevCell = lastActiveCell.current;
                                    const prevTable = prevCell.closest(
                                        'table',
                                    ) as HTMLTableElement | null;

                                    if (
                                        prevTable &&
                                        isNegotiationTable(prevTable)
                                    ) {
                                        const row = prevCell.closest('tr');

                                        if (row && row.cells.length === 8) {
                                            const idx = prevCell.cellIndex;

                                            if (idx === 4 || idx === 6) {
                                                const raw = parseNegoNumber(
                                                    prevCell.innerText,
                                                );

                                                if (raw > 0) {
                                                    prevCell.innerText =
                                                        formatRupiahDisplay(
                                                            raw,
                                                        );
                                                }
                                            }
                                        }

                                        recalculateNegotiationTable(
                                            prevTable,
                                            null,
                                        );
                                    } else if (
                                        prevTable &&
                                        isSuratPesananTable(prevTable)
                                    ) {
                                        const row = prevCell.closest('tr');

                                        if (
                                            row &&
                                            (row.cells.length === 7 ||
                                                row.cells.length === 6)
                                        ) {
                                            const idx = prevCell.cellIndex;

                                            if (idx === 4) {
                                                const raw = parseNegoNumber(
                                                    prevCell.innerText,
                                                );

                                                if (raw > 0) {
                                                    prevCell.innerText =
                                                        'Rp ' +
                                                        formatRupiahDisplay(
                                                            raw,
                                                        );
                                                }
                                            }
                                        }

                                        recalculateSuratPesananTable(
                                            prevTable,
                                            null,
                                        );
                                    }

                                    lastActiveCell.current = null;
                                }

                                emit();
                            }}
                            onPaste={(event) => {
                                event.preventDefault();
                                const text =
                                    event.clipboardData.getData('text/plain');
                                document.execCommand('insertText', false, text);
                                emit();
                            }}
                            style={{
                                minHeight: `${paper.height - setup.margins.top - setup.margins.bottom}mm`,
                            }}
                            className="document-preview outline-none"
                        />
                        <PageBoundaries
                            area={area}
                            endpoint={pagesEndpoint}
                            title={title}
                            content={value}
                            scale={zoom}
                            gap={sheetGap}
                            bandOffset={setup.margins.bottom * PX_PER_MM}
                            prepareBody={(body) =>
                                withPageSetup(body, chosenSetup.current)
                            }
                        />
                    </div>
                </div>
            </div>

            {/* Status bar: the page setup and the zoom, as in Word */}
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border bg-card px-3 py-1 text-xs text-muted-foreground">
                <button
                    type="button"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => setSetupOpen(true)}
                    disabled={disabled}
                    title="Ubah ukuran kertas, orientasi, dan margin"
                    className="inline-flex items-center gap-1.5 rounded px-1.5 py-1 hover:bg-muted hover:text-foreground disabled:pointer-events-none"
                >
                    <FileCog className="size-3.5" />
                    <span className="tabular">{describePageSetup(setup)}</span>
                    {!isCustomSetup && (
                        <span className="text-muted-foreground/80">
                            (bawaan template)
                        </span>
                    )}
                </button>

                <ZoomControls
                    zoom={zoom}
                    fit={fit}
                    onZoom={setZoom}
                    onFitWidth={fitWidth}
                    onZoomIn={zoomIn}
                    onZoomOut={zoomOut}
                />
            </div>

            <PageSetupDialog
                open={setupOpen}
                onOpenChange={setSetupOpen}
                setup={setup}
                isCustom={isCustomSetup}
                onApply={applySetup}
                onReset={() => applySetup(null)}
            />
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

/** Top, middle and bottom: where the text of the selected cells sits. */
function CellAlignButtons({
    current,
    onAlign,
    compact = false,
}: {
    current: CellAlign | null;
    onAlign: (align: CellAlign) => void;
    compact?: boolean;
}) {
    return (
        <div
            role="group"
            aria-label="Posisi teks di dalam sel"
            className="flex items-center gap-0.5"
        >
            {CELL_ALIGNS.map(({ value, label, icon }) => (
                <Tooltip key={value}>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            size="sm"
                            variant={current === value ? 'default' : 'ghost'}
                            className={cn('p-0', compact ? 'size-6' : 'size-7')}
                            // Keep the selection: the button must not steal focus.
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => onAlign(value)}
                            aria-label={label}
                            aria-pressed={current === value}
                        >
                            {icon}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{label}</TooltipContent>
                </Tooltip>
            ))}
        </div>
    );
}
