import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    ChevronDown,
    Columns3,
    Eraser,
    FileSignature,
    Grid2x2Plus,
    ImageIcon,
    IndentDecrease,
    IndentIncrease,
    Italic,
    List,
    ListOrdered,
    Minus,
    Redo2,
    Rows3,
    Scissors,
    Strikethrough,
    Table2,
    Trash2,
    Underline,
    Undo2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useImperativeHandle, useRef, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    buildEditorDocument,
    isFullDocument,
    isLandscape,
    placeholderChipHtml,
    prepareEditorDocument,
    serializeEditorDocument,
    setEditorOrientation,
} from '@/lib/template-document';
import type { PlaceholderOption } from '@/lib/template-document';
import { cn } from '@/lib/utils';

export type TemplateEditorHandle = {
    /** The template body in its stored format. */
    getBody: () => string;
    /** Put a placeholder chip at the caret. */
    insertPlaceholder: (key: string) => void;
};

type ToolbarState = {
    block: string;
    bold: boolean;
    italic: boolean;
    underline: boolean;
    strikeThrough: boolean;
    justifyLeft: boolean;
    justifyCenter: boolean;
    justifyRight: boolean;
    justifyFull: boolean;
    insertUnorderedList: boolean;
    insertOrderedList: boolean;
    inTable: boolean;
};

const EMPTY_STATE: ToolbarState = {
    block: 'p',
    bold: false,
    italic: false,
    underline: false,
    strikeThrough: false,
    justifyLeft: false,
    justifyCenter: false,
    justifyRight: false,
    justifyFull: false,
    insertUnorderedList: false,
    insertOrderedList: false,
    inTable: false,
};

const BLOCK_STYLES = [
    { value: 'p', label: 'Normal', className: 'text-sm' },
    {
        value: 'h1',
        label: 'Judul 1',
        className: 'text-base font-bold uppercase',
    },
    { value: 'h2', label: 'Judul 2', className: 'text-sm font-bold uppercase' },
    { value: 'h3', label: 'Judul 3', className: 'text-sm font-semibold' },
    { value: 'h4', label: 'Judul 4', className: 'text-xs font-semibold' },
];

const FONT_SIZES = ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24'];

const TEXT_COLORS = [
    '#111111',
    '#475569',
    '#0b4a8f',
    '#1d4ed8',
    '#047857',
    '#b45309',
    '#b91c1c',
    '#7c3aed',
];

const TABLE_GRID = 8;

const SIGNATURE_BLOCK = `<table class="signature"><tbody><tr>
<td><p>Pihak Pertama,</p><p class="role">Jabatan</p><p class="space">&nbsp;</p><p class="name">Nama Lengkap</p></td>
<td><p>Pihak Kedua,</p><p class="role">Jabatan</p><p class="space">&nbsp;</p><p class="name">Nama Lengkap</p></td>
</tr></tbody></table><p><br></p>`;

const LOGO_HTML =
    '<img src="/logo/sidebar-logo.png" alt="PT PLN Nusantara Power" style="height: 38px; width: auto;">';

function ToolbarButton({
    label,
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            aria-pressed={active}
            disabled={disabled}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
            className={cn(
                'inline-flex size-8 items-center justify-center rounded-md text-foreground/80 transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-40',
                active && 'bg-primary/10 text-primary hover:bg-primary/15',
            )}
        >
            {children}
        </button>
    );
}

function ToolbarDivider() {
    return <span className="mx-1 h-6 w-px shrink-0 bg-border" aria-hidden />;
}

function ToolbarMenuTrigger({
    label,
    className,
    children,
}: {
    label: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <DropdownMenuTrigger asChild>
            <button
                type="button"
                title={label}
                onMouseDown={(event) => event.preventDefault()}
                className={cn(
                    'inline-flex h-8 items-center gap-1 rounded-md px-2 text-sm text-foreground/80 transition-colors hover:bg-muted hover:text-foreground data-[state=open]:bg-muted',
                    className,
                )}
            >
                {children}
                <ChevronDown className="size-3.5 opacity-60" />
            </button>
        </DropdownMenuTrigger>
    );
}

/**
 * A word-processor style editor for document templates.
 *
 * The page is edited inside an isolated frame so the template looks exactly
 * like the generated document, while the administrator works with a toolbar
 * instead of markup.
 */
export function TemplateEditor({
    ref,
    initialBody,
    documentStylesheet,
    placeholders,
    className,
}: {
    ref?: React.Ref<TemplateEditorHandle>;
    initialBody: string;
    documentStylesheet: string;
    placeholders: PlaceholderOption[];
    className?: string;
}) {
    const frameRef = useRef<HTMLIFrameElement>(null);
    const savedRange = useRef<Range | null>(null);
    const [srcDoc] = useState(() =>
        buildEditorDocument(initialBody, documentStylesheet),
    );
    const [fullDocument] = useState(() => isFullDocument(initialBody));
    const [landscape, setLandscape] = useState(() => isLandscape(initialBody));
    const [state, setState] = useState<ToolbarState>(EMPTY_STATE);
    const [gridHover, setGridHover] = useState<[number, number]>([0, 0]);

    const frameDocument = useCallback(
        (): Document | null => frameRef.current?.contentDocument ?? null,
        [],
    );

    const refreshState = useCallback(() => {
        const doc = frameDocument();

        if (doc === null) {
            return;
        }

        const selection = doc.getSelection();
        const anchor = selection?.anchorNode ?? null;
        const anchorElement =
            anchor === null
                ? null
                : anchor.nodeType === Node.ELEMENT_NODE
                  ? (anchor as Element)
                  : anchor.parentElement;
        const query = (command: string): boolean => {
            try {
                return doc.queryCommandState(command);
            } catch {
                return false;
            }
        };

        let block = 'p';

        try {
            block =
                String(doc.queryCommandValue('formatBlock') || 'p')
                    .toLowerCase()
                    .replace(/[<>]/g, '') || 'p';
        } catch {
            block = 'p';
        }

        setState({
            block,
            bold: query('bold'),
            italic: query('italic'),
            underline: query('underline'),
            strikeThrough: query('strikeThrough'),
            justifyLeft: query('justifyLeft'),
            justifyCenter: query('justifyCenter'),
            justifyRight: query('justifyRight'),
            justifyFull: query('justifyFull'),
            insertUnorderedList: query('insertUnorderedList'),
            insertOrderedList: query('insertOrderedList'),
            inTable:
                anchorElement?.closest('td, th') !== null &&
                anchorElement !== null,
        });
    }, [frameDocument]);

    const handleLoad = useCallback(() => {
        const doc = frameDocument();

        if (doc === null || doc.body === null || doc.body.isContentEditable) {
            return;
        }

        prepareEditorDocument(doc, placeholders);

        try {
            doc.execCommand('styleWithCSS', false, 'true');
            doc.execCommand('defaultParagraphSeparator', false, 'p');
        } catch {
            // Older engines ignore these hints; editing still works.
        }

        doc.addEventListener('selectionchange', () => {
            const selection = doc.getSelection();

            if (
                selection !== null &&
                selection.rangeCount > 0 &&
                doc.body.contains(selection.anchorNode)
            ) {
                savedRange.current = selection.getRangeAt(0).cloneRange();
            }

            refreshState();
        });

        doc.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
                return;
            }

            const selection = doc.getSelection();
            const node = selection?.anchorNode ?? null;
            const element =
                node?.nodeType === Node.ELEMENT_NODE
                    ? (node as Element)
                    : (node?.parentElement ?? null);
            const cell = element?.closest('td, th');

            event.preventDefault();

            if (cell instanceof HTMLTableCellElement) {
                const cells = Array.from(
                    cell.closest('table')?.querySelectorAll('td, th') ?? [],
                );
                const next =
                    cells[cells.indexOf(cell) + (event.shiftKey ? -1 : 1)];

                if (next !== undefined) {
                    const range = doc.createRange();
                    range.selectNodeContents(next);
                    range.collapse(false);
                    selection?.removeAllRanges();
                    selection?.addRange(range);
                }

                return;
            }

            doc.execCommand(event.shiftKey ? 'outdent' : 'indent');
        });
    }, [frameDocument, placeholders, refreshState]);

    /** Give the frame focus back with the caret where the user left it. */
    const restoreSelection = useCallback((): Document | null => {
        const doc = frameDocument();

        if (doc === null) {
            return null;
        }

        frameRef.current?.contentWindow?.focus();

        const selection = doc.getSelection();

        if (savedRange.current !== null && selection !== null) {
            selection.removeAllRanges();
            selection.addRange(savedRange.current);
        } else if (selection !== null && selection.rangeCount === 0) {
            const range = doc.createRange();
            range.selectNodeContents(doc.body);
            range.collapse(false);
            selection.addRange(range);
        }

        return doc;
    }, [frameDocument]);

    const exec = useCallback(
        (command: string, value?: string) => {
            const doc = restoreSelection();

            if (doc === null) {
                return;
            }

            doc.execCommand(command, false, value);
            refreshState();
        },
        [restoreSelection, refreshState],
    );

    const insertHtml = useCallback(
        (html: string) => exec('insertHTML', html),
        [exec],
    );

    const applyFontSize = (points: string) => {
        const doc = restoreSelection();

        if (doc === null) {
            return;
        }

        doc.execCommand('fontSize', false, '7');
        doc.body
            .querySelectorAll<HTMLElement>(
                'font[size="7"], span[style*="xxx-large"]',
            )
            .forEach((element) => {
                if (element.tagName === 'FONT') {
                    const span = doc.createElement('span');
                    span.style.fontSize = `${points}pt`;
                    span.append(...Array.from(element.childNodes));
                    element.replaceWith(span);

                    return;
                }

                element.style.fontSize = `${points}pt`;
            });
        refreshState();
    };

    const currentCell = (): HTMLTableCellElement | null => {
        const doc = frameDocument();
        const node = savedRange.current?.startContainer ?? null;

        if (doc === null || node === null) {
            return null;
        }

        const element =
            node.nodeType === Node.ELEMENT_NODE
                ? (node as Element)
                : node.parentElement;
        const cell = element?.closest('td, th') ?? null;

        return cell instanceof HTMLTableCellElement ? cell : null;
    };

    const placeCaretIn = (cell: HTMLTableCellElement | null) => {
        const doc = frameDocument();

        if (doc === null || cell === null) {
            return;
        }

        const range = doc.createRange();
        range.selectNodeContents(cell);
        range.collapse(true);
        savedRange.current = range;
        restoreSelection();
        refreshState();
    };

    const insertTable = (rows: number, columns: number) => {
        const cells = '<td><br></td>'.repeat(columns);
        const body = `<tr>${cells}</tr>`.repeat(rows);

        insertHtml(`<table><tbody>${body}</tbody></table><p><br></p>`);
    };

    const addRow = (below: boolean) => {
        const cell = currentCell();
        const row = cell?.parentElement;

        if (!(row instanceof HTMLTableRowElement)) {
            return;
        }

        const clone = row.cloneNode(false) as HTMLTableRowElement;

        Array.from(row.cells).forEach((source) => {
            const copy = source.cloneNode(false) as HTMLTableCellElement;
            copy.innerHTML = '<br>';
            clone.appendChild(copy);
        });

        row.parentElement?.insertBefore(clone, below ? row.nextSibling : row);
        placeCaretIn(clone.cells[cell?.cellIndex ?? 0] ?? null);
    };

    const addColumn = (right: boolean) => {
        const cell = currentCell();
        const table = cell?.closest('table');

        if (
            cell === null ||
            cell === undefined ||
            table === null ||
            table === undefined
        ) {
            return;
        }

        const index = cell.cellIndex;

        Array.from(table.rows).forEach((row) => {
            const reference = row.cells[Math.min(index, row.cells.length - 1)];
            const copy = (reference?.cloneNode(false) ??
                row.ownerDocument.createElement('td')) as HTMLTableCellElement;
            copy.innerHTML = '<br>';
            copy.removeAttribute('colspan');
            copy.style.removeProperty('width');
            row.insertBefore(
                copy,
                right ? (reference?.nextSibling ?? null) : (reference ?? null),
            );
        });

        placeCaretIn(cell);
    };

    const deleteRow = () => {
        const cell = currentCell();
        const row = cell?.parentElement;
        const table = cell?.closest('table');

        if (
            !(row instanceof HTMLTableRowElement) ||
            table === null ||
            table === undefined
        ) {
            return;
        }

        if (table.rows.length <= 1) {
            table.remove();
            savedRange.current = null;
            refreshState();

            return;
        }

        const neighbour = (row.nextElementSibling ??
            row.previousElementSibling) as HTMLTableRowElement | null;
        row.remove();
        placeCaretIn(neighbour?.cells[0] ?? null);
    };

    const deleteColumn = () => {
        const cell = currentCell();
        const table = cell?.closest('table');

        if (
            cell === null ||
            cell === undefined ||
            table === null ||
            table === undefined
        ) {
            return;
        }

        const index = cell.cellIndex;

        if (Array.from(table.rows).every((row) => row.cells.length <= 1)) {
            table.remove();
            savedRange.current = null;
            refreshState();

            return;
        }

        Array.from(table.rows).forEach((row) => row.cells[index]?.remove());
        placeCaretIn(table.rows[0]?.cells[Math.max(0, index - 1)] ?? null);
    };

    const deleteTable = () => {
        currentCell()?.closest('table')?.remove();
        savedRange.current = null;
        refreshState();
    };

    const toggleTableBorders = () => {
        const table = currentCell()?.closest('table');

        table?.classList.toggle('plain');
    };

    const changeOrientation = (toLandscape: boolean) => {
        const doc = frameDocument();

        if (doc === null) {
            return;
        }

        setEditorOrientation(doc, toLandscape);
        setLandscape(toLandscape);
    };

    useImperativeHandle(
        ref,
        () => ({
            getBody: () => {
                const doc = frameDocument();

                return doc === null
                    ? initialBody
                    : serializeEditorDocument(doc, fullDocument);
            },
            insertPlaceholder: (key: string) => {
                const labels = new Map(
                    placeholders.map((item) => [item.key, item.label]),
                );

                insertHtml(`${placeholderChipHtml(key, labels)}&nbsp;`);
            },
        }),
        [frameDocument, fullDocument, initialBody, insertHtml, placeholders],
    );

    const blockLabel =
        BLOCK_STYLES.find((style) => style.value === state.block)?.label ??
        'Normal';

    return (
        <div
            className={cn(
                'flex min-h-0 flex-col overflow-hidden rounded-md border border-border bg-card',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-0.5 border-b border-border bg-card px-2 py-1.5">
                <ToolbarButton
                    label="Urungkan (Ctrl+Z)"
                    onClick={() => exec('undo')}
                >
                    <Undo2 className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Ulangi (Ctrl+Y)"
                    onClick={() => exec('redo')}
                >
                    <Redo2 className="size-4" />
                </ToolbarButton>

                <ToolbarDivider />

                <DropdownMenu>
                    <ToolbarMenuTrigger
                        label="Gaya paragraf"
                        className="w-28 justify-between"
                    >
                        <span className="truncate">{blockLabel}</span>
                    </ToolbarMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        onCloseAutoFocus={(event) => event.preventDefault()}
                    >
                        {BLOCK_STYLES.map((style) => (
                            <DropdownMenuItem
                                key={style.value}
                                onSelect={() =>
                                    exec('formatBlock', style.value)
                                }
                                className={style.className}
                            >
                                {style.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <ToolbarMenuTrigger
                        label="Ukuran huruf"
                        className="w-16 justify-between"
                    >
                        <span className="tabular">pt</span>
                    </ToolbarMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        className="min-w-20"
                        onCloseAutoFocus={(event) => event.preventDefault()}
                    >
                        {FONT_SIZES.map((size) => (
                            <DropdownMenuItem
                                key={size}
                                onSelect={() => applyFontSize(size)}
                                className="tabular"
                            >
                                {size} pt
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <ToolbarDivider />

                <ToolbarButton
                    label="Tebal (Ctrl+B)"
                    active={state.bold}
                    onClick={() => exec('bold')}
                >
                    <Bold className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Miring (Ctrl+I)"
                    active={state.italic}
                    onClick={() => exec('italic')}
                >
                    <Italic className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Garis bawah (Ctrl+U)"
                    active={state.underline}
                    onClick={() => exec('underline')}
                >
                    <Underline className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Coret"
                    active={state.strikeThrough}
                    onClick={() => exec('strikeThrough')}
                >
                    <Strikethrough className="size-4" />
                </ToolbarButton>

                <DropdownMenu>
                    <ToolbarMenuTrigger label="Warna teks" className="px-1.5">
                        <span className="flex flex-col items-center leading-none">
                            <span className="text-sm font-semibold">A</span>
                            <span className="mt-0.5 h-1 w-4 rounded-sm bg-primary" />
                        </span>
                    </ToolbarMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        className="p-2"
                        onCloseAutoFocus={(event) => event.preventDefault()}
                    >
                        <div className="grid grid-cols-4 gap-1.5">
                            {TEXT_COLORS.map((color) => (
                                <DropdownMenuItem
                                    key={color}
                                    onSelect={() => exec('foreColor', color)}
                                    className="size-7 rounded-sm p-0"
                                    style={{ backgroundColor: color }}
                                    aria-label={`Warna ${color}`}
                                />
                            ))}
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                <ToolbarButton
                    label="Hapus format"
                    onClick={() => exec('removeFormat')}
                >
                    <Eraser className="size-4" />
                </ToolbarButton>

                <ToolbarDivider />

                <ToolbarButton
                    label="Rata kiri"
                    active={state.justifyLeft}
                    onClick={() => exec('justifyLeft')}
                >
                    <AlignLeft className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Rata tengah"
                    active={state.justifyCenter}
                    onClick={() => exec('justifyCenter')}
                >
                    <AlignCenter className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Rata kanan"
                    active={state.justifyRight}
                    onClick={() => exec('justifyRight')}
                >
                    <AlignRight className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Rata kiri-kanan"
                    active={state.justifyFull}
                    onClick={() => exec('justifyFull')}
                >
                    <AlignJustify className="size-4" />
                </ToolbarButton>

                <ToolbarDivider />

                <ToolbarButton
                    label="Daftar berpoin"
                    active={state.insertUnorderedList}
                    onClick={() => exec('insertUnorderedList')}
                >
                    <List className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Daftar bernomor"
                    active={state.insertOrderedList}
                    onClick={() => exec('insertOrderedList')}
                >
                    <ListOrdered className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Kurangi indentasi"
                    onClick={() => exec('outdent')}
                >
                    <IndentDecrease className="size-4" />
                </ToolbarButton>
                <ToolbarButton
                    label="Tambah indentasi"
                    onClick={() => exec('indent')}
                >
                    <IndentIncrease className="size-4" />
                </ToolbarButton>

                <ToolbarDivider />

                <DropdownMenu onOpenChange={() => setGridHover([0, 0])}>
                    <ToolbarMenuTrigger label="Sisipkan tabel">
                        <Table2 className="size-4" />
                        <span className="hidden text-sm lg:inline">Tabel</span>
                    </ToolbarMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        className="p-2"
                        onCloseAutoFocus={(event) => event.preventDefault()}
                    >
                        <DropdownMenuLabel className="px-0 pt-0 text-xs font-medium text-muted-foreground">
                            {gridHover[0] > 0
                                ? `${gridHover[0]} baris × ${gridHover[1]} kolom`
                                : 'Pilih ukuran tabel'}
                        </DropdownMenuLabel>
                        <div
                            className="grid gap-0.5"
                            style={{
                                gridTemplateColumns: `repeat(${TABLE_GRID}, 1rem)`,
                            }}
                        >
                            {Array.from(
                                { length: TABLE_GRID * TABLE_GRID },
                                (_, index) => {
                                    const row =
                                        Math.floor(index / TABLE_GRID) + 1;
                                    const column = (index % TABLE_GRID) + 1;
                                    const highlighted =
                                        row <= gridHover[0] &&
                                        column <= gridHover[1];

                                    return (
                                        <DropdownMenuItem
                                            key={index}
                                            onMouseEnter={() =>
                                                setGridHover([row, column])
                                            }
                                            onFocus={() =>
                                                setGridHover([row, column])
                                            }
                                            onSelect={() =>
                                                insertTable(row, column)
                                            }
                                            aria-label={`${row} baris × ${column} kolom`}
                                            className={cn(
                                                'size-4 rounded-[2px] border border-border p-0 focus:bg-primary/30',
                                                highlighted &&
                                                    'border-primary bg-primary/30',
                                            )}
                                        />
                                    );
                                },
                            )}
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <ToolbarMenuTrigger
                        label="Atur tabel"
                        className={cn(!state.inTable && 'opacity-50')}
                    >
                        <Grid2x2Plus className="size-4" />
                        <span className="hidden text-sm lg:inline">
                            Atur Tabel
                        </span>
                    </ToolbarMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        onCloseAutoFocus={(event) => event.preventDefault()}
                    >
                        {!state.inTable && (
                            <DropdownMenuLabel className="max-w-56 text-xs font-normal text-muted-foreground">
                                Klik salah satu sel tabel di dokumen terlebih
                                dahulu.
                            </DropdownMenuLabel>
                        )}
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={() => addRow(false)}
                        >
                            <Rows3 className="size-4" /> Sisipkan baris di atas
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={() => addRow(true)}
                        >
                            <Rows3 className="size-4" /> Sisipkan baris di bawah
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={() => addColumn(false)}
                        >
                            <Columns3 className="size-4" /> Sisipkan kolom di
                            kiri
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={() => addColumn(true)}
                        >
                            <Columns3 className="size-4" /> Sisipkan kolom di
                            kanan
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={toggleTableBorders}
                        >
                            <Table2 className="size-4" /> Tampilkan /
                            sembunyikan garis
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={deleteRow}
                            variant="destructive"
                        >
                            <Trash2 className="size-4" /> Hapus baris
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={deleteColumn}
                            variant="destructive"
                        >
                            <Trash2 className="size-4" /> Hapus kolom
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={!state.inTable}
                            onSelect={deleteTable}
                            variant="destructive"
                        >
                            <Trash2 className="size-4" /> Hapus tabel
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <ToolbarMenuTrigger label="Sisipkan elemen">
                        <span className="text-sm">Sisipkan</span>
                    </ToolbarMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        onCloseAutoFocus={(event) => event.preventDefault()}
                    >
                        <DropdownMenuItem
                            onSelect={() => insertHtml(LOGO_HTML)}
                        >
                            <ImageIcon className="size-4" /> Logo perusahaan
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => insertHtml(SIGNATURE_BLOCK)}
                        >
                            <FileSignature className="size-4" /> Blok tanda
                            tangan
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => exec('insertHorizontalRule')}
                        >
                            <Minus className="size-4" /> Garis pemisah
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() =>
                                insertHtml(
                                    '<div class="page-break"></div><p><br></p>',
                                )
                            }
                        >
                            <Scissors className="size-4" /> Pemisah halaman
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                {!fullDocument && (
                    <>
                        <ToolbarDivider />
                        <DropdownMenu>
                            <ToolbarMenuTrigger label="Orientasi kertas">
                                <span className="text-sm">
                                    A4 {landscape ? 'Lanskap' : 'Potret'}
                                </span>
                            </ToolbarMenuTrigger>
                            <DropdownMenuContent
                                align="start"
                                onCloseAutoFocus={(event) =>
                                    event.preventDefault()
                                }
                            >
                                <DropdownMenuItem
                                    onSelect={() => changeOrientation(false)}
                                >
                                    A4 Potret
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => changeOrientation(true)}
                                >
                                    A4 Lanskap
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </>
                )}
            </div>

            <iframe
                ref={frameRef}
                title="Lembar template dokumen"
                srcDoc={srcDoc}
                onLoad={handleLoad}
                className="min-h-0 w-full flex-1 bg-[#e8ebf0]"
            />
        </div>
    );
}
