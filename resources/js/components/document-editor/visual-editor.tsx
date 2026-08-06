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
            lastEmitted.current = value;
            seeded.current = true;
        }
    }, [value]);

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

    // Track what the caret is sitting in so the toolbar can reflect it.
    useEffect(() => {
        const onSelectionChange = () => {
            const element = caretElement();

            setInTable(element?.closest('td, th') !== null && element !== null);

            const block = element?.closest('h1, h2, h3, h4, p');
            setBlockStyle(block ? block.tagName.toLowerCase() : 'p');
        };

        document.addEventListener('selectionchange', onSelectionChange);

        return () =>
            document.removeEventListener('selectionchange', onSelectionChange);
    }, [caretElement]);

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
        } else {
            row.remove();
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
            emit();

            return;
        }

        Array.from(table.rows).forEach((row) => row.cells[index]?.remove());
        emit();
    };

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center gap-1 rounded-md border border-border bg-card px-2 py-1.5">
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
                <div className="flex flex-wrap items-center gap-1 rounded-md border border-primary/30 bg-primary/5 px-2 py-1.5">
                    <span className="mr-1 text-xs font-medium text-muted-foreground">
                        Tabel:
                    </span>
                    <Button size="sm" variant="ghost" onClick={addRow}>
                        <Rows3 className="size-3.5" />
                        Tambah Baris
                    </Button>
                    <Button size="sm" variant="ghost" onClick={addColumn}>
                        <Columns3 className="size-3.5" />
                        Tambah Kolom
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive hover:text-destructive"
                        onClick={removeRow}
                    >
                        <Trash2 className="size-3.5" />
                        Hapus Baris
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive hover:text-destructive"
                        onClick={removeColumn}
                    >
                        <Trash2 className="size-3.5" />
                        Hapus Kolom
                    </Button>
                </div>
            )}

            {/*
             * The page frame and the document are separate elements on
             * purpose. `.document-preview` centres itself with `margin: 0
             * auto`, and an auto cross-axis margin on a flex child collapses
             * it to its content width; inside this plain block wrapper it
             * lays out at full page width the way the print preview does.
             */}
            <div className="min-h-[70vh] overflow-auto rounded-md border border-border bg-white p-6 focus-within:ring-2 focus-within:ring-primary/40">
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
                    // Pasting from Word drags in a mountain of inline styling
                    // that would fight the print stylesheet, so only text comes.
                    onPaste={(event) => {
                        event.preventDefault();
                        const text = event.clipboardData.getData('text/plain');
                        document.execCommand('insertText', false, text);
                        emit();
                    }}
                    className="document-preview min-h-[65vh] outline-none"
                />
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
