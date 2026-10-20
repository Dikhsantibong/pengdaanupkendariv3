import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';

/**
 * The attribute that marks the first element of a printed sheet in the
 * editable area. It only ever lives on screen: it is stripped from every
 * body the editor hands on (see {@link withoutSheetMarks}).
 */
export const SHEET_ATTRIBUTE = 'data-sheet-start';

/**
 * The height of the space between two sheets when the editor does not say,
 * matching the margin the `[data-sheet-start]` rules in app.css open up
 * before a new sheet.
 */
const SHEET_GAP = 112;

/** The height of the desk band drawn in the gap, in px. */
const DESK_BAND = 32;

type SheetStart = { page: number; index: number };

type SheetLayout = {
    page_count: number;
    sheet_starts: SheetStart[];
};

type Mark = {
    page: number;
    top: number;
    mode: 'gap' | 'row' | 'line';
};

/**
 * Remove the sheet markers from a body before it is saved or sent anywhere.
 */
export function withoutSheetMarks(html: string): string {
    return html.replace(/\s+data-sheet-(?:start|page)="[^"]*"/g, '');
}

function xsrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match === null
        ? {}
        : { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
}

/**
 * The body with every element numbered in document order, so the PDF engine
 * can say which element each sheet starts with.
 */
function numberedBody(area: HTMLElement): string {
    const clone = area.cloneNode(true) as HTMLElement;

    clone.querySelectorAll(`[${SHEET_ATTRIBUTE}]`).forEach((element) => {
        element.removeAttribute(SHEET_ATTRIBUTE);
        element.removeAttribute('data-sheet-page');
    });
    clone.querySelectorAll('*').forEach((element, index) => {
        element.setAttribute('data-pg', String(index));
    });

    return clone.innerHTML;
}

/**
 * Whether the sheet starting at this element already follows one of the
 * editor's own "Batas Halaman" markers, which draw their own divider.
 */
function followsPageBreakMarker(elements: Element[], index: number): boolean {
    const target = elements[index];

    for (let previous = index - 1; previous >= 0; previous--) {
        const candidate = elements[previous];

        if (candidate.contains(target)) {
            continue;
        }

        return candidate.closest('.page-break') !== null;
    }

    return false;
}

/**
 * How the start of a sheet can be pulled apart from the sheet before it.
 *
 * A block opens a gap above itself; a table row opens it inside its cells,
 * so the table carries on onto the next sheet; anything else (text that
 * continues inside a cell or a paragraph) is only marked with a line.
 */
function modeFor(element: Element): Mark['mode'] {
    if (element.tagName === 'TR') {
        return 'row';
    }

    if (element.closest('td, th, tr, thead, tbody, tfoot') !== null) {
        return 'line';
    }

    const display = window.getComputedStyle(element).display;

    return [
        'block',
        'list-item',
        'table',
        'flex',
        'grid',
        'flow-root',
    ].includes(display)
        ? 'gap'
        : 'line';
}

/**
 * Pull the editable document apart into the sheets the PDF will print.
 *
 * Where a sheet begins is not guessed on screen, where fonts and margins
 * differ from the PDF: the draft is laid out by the PDF engine itself, which
 * names the element every sheet starts with. That element is then marked so
 * the editor opens a gap between the sheets, and a band is drawn over the
 * gap. The band sits outside the editable area and the marker is stripped
 * before saving, so nothing of it reaches the document. A document that fits
 * on one sheet is left exactly as it was.
 */
export function PageBoundaries({
    area,
    endpoint,
    title,
    content,
    scale = 1,
    gap = SHEET_GAP,
    bandOffset = (SHEET_GAP - DESK_BAND) / 2,
    prepareBody,
}: {
    area: RefObject<HTMLDivElement | null>;
    endpoint?: string;
    title: string;
    content: string;
    /** The zoom the sheet is drawn at; positions are measured on screen. */
    scale?: number;
    /** The space opened before a new sheet (the `--sheet-gap` of the sheet). */
    gap?: number;
    /** Where the desk band starts in that space: the bottom margin. */
    bandOffset?: number;
    /**
     * Puts back what the editable area leaves out (the page setup), so the
     * PDF engine lays the draft out on the same paper.
     */
    prepareBody?: (body: string) => string;
}) {
    const [marks, setMarks] = useState<Mark[]>([]);
    const layout = useRef<{ body: string; sheets: SheetLayout } | null>(null);
    const prepare = useRef(prepareBody);

    useEffect(() => {
        prepare.current = prepareBody;
    }, [prepareBody]);

    // Where the bands go depends on the marked elements' positions, which
    // shift as the author types, as images load, as long chapters are laid
    // out on scroll, and as the zoom or the margins change.
    useEffect(() => {
        const element = area.current;

        if (!element) {
            return;
        }

        let frame = 0;
        const place = () => {
            const paper = element.parentElement ?? element;
            const origin = paper.getBoundingClientRect().top;
            const found: Mark[] = [];

            element
                .querySelectorAll<HTMLElement>(`[${SHEET_ATTRIBUTE}]`)
                .forEach((marked) => {
                    // Measured on screen, at the zoom; the bands are drawn
                    // inside the sheet, at its own size.
                    const top =
                        (marked.getBoundingClientRect().top - origin) / scale;
                    const mode = marked.getAttribute(
                        SHEET_ATTRIBUTE,
                    ) as Mark['mode'];

                    found.push({
                        page: Number(marked.dataset.sheetPage ?? 0),
                        mode,
                        top: mode === 'gap' ? top - gap : top,
                    });
                });

            setMarks(found);
        };
        const schedule = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(place);
        };

        const observer = new ResizeObserver(schedule);
        observer.observe(element);
        element.addEventListener('input', schedule);
        element.addEventListener('sheetschange', schedule);
        schedule();

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            element.removeEventListener('input', schedule);
            element.removeEventListener('sheetschange', schedule);
        };
    }, [area, scale, gap]);

    // Ask the PDF engine for the sheets once the author pauses typing.
    useEffect(() => {
        const element = area.current;

        if (!element || !endpoint) {
            return;
        }

        const apply = (sheets: SheetLayout) => {
            element
                .querySelectorAll(`[${SHEET_ATTRIBUTE}]`)
                .forEach((marked) => {
                    marked.removeAttribute(SHEET_ATTRIBUTE);
                    marked.removeAttribute('data-sheet-page');
                });

            const elements = Array.from(element.querySelectorAll('*'));

            sheets.sheet_starts.forEach(({ page, index }) => {
                const start = elements[index];

                if (!start || followsPageBreakMarker(elements, index)) {
                    return;
                }

                start.setAttribute(SHEET_ATTRIBUTE, modeFor(start));
                start.setAttribute('data-sheet-page', String(page));
            });

            element.dispatchEvent(new Event('sheetschange'));
        };

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            const draft = () =>
                prepare.current
                    ? prepare.current(numberedBody(element))
                    : numberedBody(element);
            const body = draft();

            if (layout.current?.body === body) {
                apply(layout.current.sheets);

                return;
            }

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...xsrfHeader(),
                },
                body: JSON.stringify({ title: title || 'Dokumen', body }),
                signal: controller.signal,
            })
                .then((response) =>
                    response.ok
                        ? (response.json() as Promise<SheetLayout>)
                        : null,
                )
                .then((sheets) => {
                    // Only mark the sheets of the body that was laid out: if
                    // the author typed on meanwhile, the next round will.
                    if (sheets === null || draft() !== body) {
                        return;
                    }

                    layout.current = { body, sheets };
                    apply(sheets);
                })
                .catch(() => {
                    // The sheets are a guide only; editing goes on without.
                });
        }, 800);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [area, endpoint, title, content]);

    if (marks.length === 0) {
        return null;
    }

    return (
        <div aria-hidden="true" className="pointer-events-none">
            {marks.map((mark) =>
                mark.mode === 'line' ? (
                    <div
                        key={`${mark.page}-line`}
                        className="absolute inset-x-0 z-10 border-t-2 border-dashed border-sky-500/70"
                        style={{ top: mark.top }}
                    >
                        <SheetLabel page={mark.page} />
                    </div>
                ) : (
                    <div
                        key={`${mark.page}-gap`}
                        className="absolute -inset-x-px z-10"
                        style={{
                            top: mark.top,
                            height: gap,
                            paddingTop: Math.max(0, bandOffset),
                        }}
                    >
                        <div className="relative h-8 w-full border-y border-slate-300 bg-slate-100 shadow-[inset_0_4px_6px_-2px_rgba(0,0,0,0.08),inset_0_-4px_6px_-2px_rgba(0,0,0,0.08)] dark:border-slate-600 dark:bg-slate-800">
                            <SheetLabel page={mark.page} />
                        </div>
                    </div>
                ),
            )}
        </div>
    );
}

function SheetLabel({ page }: { page: number }) {
    return (
        <span className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full border border-slate-400 bg-white px-3 py-0.5 font-sans text-[11px] font-semibold whitespace-nowrap text-slate-600 shadow-sm">
            Halaman {page}
        </span>
    );
}
