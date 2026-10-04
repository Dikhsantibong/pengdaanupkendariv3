import {
    Bold,
    Columns3,
    Italic,
    List,
    ListOrdered,
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

    const emit = useCallback(() => {
        if (area.current) {
            lastEmitted.current = area.current.innerHTML;
            onChange(lastEmitted.current);
        }
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
            const isInTable =
                element?.closest('td, th') !== null && element !== null;

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
    }, [caretElement, updateTableMenuPosition]);

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

        if (!row) {
            return;
        }

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
        const table = row?.closest('table');

        // Never leave an empty table behind: removing the last row removes it.
        if (!row || !table) {
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
                        onBlur={emit}
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
