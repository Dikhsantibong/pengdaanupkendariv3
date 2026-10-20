/**
 * The page setup of a document: its paper, orientation and margins.
 *
 * A document carries it the way the PDF engine reads it, as an `@page` rule.
 * What the author sets in an editor is written into one marked block,
 * `<style data-page-setup>`, placed after every other style so it wins over
 * the stylesheet and the template's own `@page`. A document without that
 * block keeps the setup its template (or the house stylesheet) gives it.
 */

export type PaperSize = 'A4' | 'F4' | 'Letter' | 'Legal';

export type PageMargins = {
    top: number;
    right: number;
    bottom: number;
    left: number;
};

export type PageSetup = {
    paper: PaperSize;
    landscape: boolean;
    /** In millimetres. */
    margins: PageMargins;
};

/** The papers offered, portrait, in millimetres. */
export const PAPER_SIZES: Record<
    PaperSize,
    { label: string; width: number; height: number }
> = {
    A4: { label: 'A4 (210 × 297 mm)', width: 210, height: 297 },
    F4: { label: 'F4 / Folio (215 × 330 mm)', width: 215, height: 330 },
    Letter: { label: 'Letter (216 × 279 mm)', width: 215.9, height: 279.4 },
    Legal: { label: 'Legal (216 × 356 mm)', width: 215.9, height: 355.6 },
};

/** The margins of the house stylesheet (DocumentGenerator::documentStylesheet). */
export const DEFAULT_MARGINS: PageMargins = {
    top: 20,
    right: 15,
    bottom: 20,
    left: 15,
};

/** The margin presets, as Word offers them, after the house standard. */
export const MARGIN_PRESETS: { label: string; margins: PageMargins }[] = [
    { label: 'Standar dokumen', margins: DEFAULT_MARGINS },
    {
        label: 'Normal (2,54 cm)',
        margins: { top: 25.4, right: 25.4, bottom: 25.4, left: 25.4 },
    },
    {
        label: 'Sempit (1,27 cm)',
        margins: { top: 12.7, right: 12.7, bottom: 12.7, left: 12.7 },
    },
    {
        label: 'Sedang',
        margins: { top: 25.4, right: 19.1, bottom: 25.4, left: 19.1 },
    },
    {
        label: 'Lebar',
        margins: { top: 25.4, right: 50.8, bottom: 25.4, left: 50.8 },
    },
];

/** CSS pixels in a millimetre. */
export const PX_PER_MM = 96 / 25.4;

const SETUP_BLOCK =
    /<style\b[^>]*\bdata-page-setup\b[^>]*>[\s\S]*?<\/style>\s*/gi;
const STYLE_BLOCK = /<style\b[^>]*>([\s\S]*?)<\/style>/gi;
const PAGE_RULE = /@page\s*\{([^}]*)\}/gi;

/** Whether the document carries a page setup chosen in an editor. */
export function hasPageSetup(body: string): boolean {
    SETUP_BLOCK.lastIndex = 0;

    return SETUP_BLOCK.test(body);
}

/** The document without the page setup chosen in an editor. */
export function withoutPageSetup(body: string): string {
    return body.replace(SETUP_BLOCK, '');
}

/** The `@page` rule of a setup. */
export function pageSetupCss(setup: PageSetup): string {
    const { width, height } = paperDimensions(setup);
    const { top, right, bottom, left } = setup.margins;

    return `@page { size: ${round(width)}mm ${round(height)}mm; margin: ${round(top)}mm ${round(right)}mm ${round(bottom)}mm ${round(left)}mm; }`;
}

/**
 * The document with the setup written in, replacing any earlier one; `null`
 * takes it out, back to the template's own.
 */
export function withPageSetup(body: string, setup: PageSetup | null): string {
    const bare = withoutPageSetup(body);

    if (setup === null) {
        return bare;
    }

    const block = `<style data-page-setup>${pageSetupCss(setup)}</style>`;

    // A full document keeps its styles in the head: the setup goes last.
    if (/<\/head>/i.test(bare)) {
        return bare.replace(/<\/head>/i, `${block}</head>`);
    }

    return `${bare}\n${block}`;
}

/** The paper's width and height as it lies, in millimetres. */
export function paperDimensions(setup: PageSetup): {
    width: number;
    height: number;
} {
    const paper = PAPER_SIZES[setup.paper];

    return setup.landscape
        ? { width: paper.height, height: paper.width }
        : { width: paper.width, height: paper.height };
}

export function samePageSetup(a: PageSetup, b: PageSetup): boolean {
    return (
        a.paper === b.paper &&
        a.landscape === b.landscape &&
        (['top', 'right', 'bottom', 'left'] as const).every(
            (side) => Math.abs(a.margins[side] - b.margins[side]) < 0.05,
        )
    );
}

/** "A4 · Potret · 20/15/20/15 mm" */
export function describePageSetup(setup: PageSetup): string {
    const { top, right, bottom, left } = setup.margins;
    const margins = [top, right, bottom, left]
        .map((value) => String(round(value)).replace('.', ','))
        .join('/');

    return `${setup.paper} · ${setup.landscape ? 'Lanskap' : 'Potret'} · Margin ${margins} mm`;
}

/**
 * The setup a document prints with: the house default, then every `@page`
 * rule of its styles in order, the last one winning as in the PDF engine.
 */
export function readPageSetup(
    body: string,
    defaultMargins: PageMargins = DEFAULT_MARGINS,
): PageSetup {
    const setup: PageSetup = {
        paper: 'A4',
        landscape: false,
        margins: { ...defaultMargins },
    };
    let sized = false;

    for (const style of body.matchAll(STYLE_BLOCK)) {
        for (const rule of style[1].matchAll(PAGE_RULE)) {
            sized = applyDeclarations(setup, rule[1]) || sized;
        }
    }

    // Older documents only say "landscape" somewhere, which is also what
    // the PDF engine falls back to.
    if (!sized && body.toLowerCase().includes('landscape')) {
        setup.landscape = true;
    }

    return setup;
}

/** Apply one `@page` body; true when it named the paper size. */
function applyDeclarations(setup: PageSetup, declarations: string): boolean {
    let sized = false;

    for (const declaration of declarations.split(';')) {
        const [rawName, ...rest] = declaration.split(':');
        const name = rawName?.trim().toLowerCase();
        const value = rest.join(':').trim().toLowerCase();

        if (!name || value === '') {
            continue;
        }

        if (name === 'size') {
            sized = applySize(setup, value) || sized;
        } else if (name === 'margin') {
            const lengths = value.split(/\s+/).map(toMillimetres);

            if (lengths.every((length) => length !== null)) {
                const [top, right = top, bottom = top, left = right] =
                    lengths as number[];
                setup.margins = { top, right, bottom, left };
            }
        } else if (name.startsWith('margin-')) {
            const side = name.slice('margin-'.length);
            const length = toMillimetres(value);

            if (
                length !== null &&
                (side === 'top' ||
                    side === 'right' ||
                    side === 'bottom' ||
                    side === 'left')
            ) {
                setup.margins = { ...setup.margins, [side]: length };
            }
        }
    }

    return sized;
}

function applySize(setup: PageSetup, value: string): boolean {
    const words = value.split(/\s+/);
    const lengths = words.map(toMillimetres);

    if (lengths.length >= 2 && lengths[0] !== null && lengths[1] !== null) {
        const [width, height] = lengths as [number, number];
        setup.paper = paperOfDimensions(width, height);
        setup.landscape = width > height;

        return true;
    }

    let named = false;

    for (const word of words) {
        if (word === 'landscape') {
            setup.landscape = true;
            named = true;
        } else if (word === 'portrait') {
            setup.landscape = false;
            named = true;
        } else if (word === 'a4') {
            setup.paper = 'A4';
            named = true;
        } else if (word === 'f4' || word === 'folio') {
            setup.paper = 'F4';
            named = true;
        } else if (word === 'letter') {
            setup.paper = 'Letter';
            named = true;
        } else if (word === 'legal') {
            setup.paper = 'Legal';
            named = true;
        }
    }

    return named;
}

/** The offered paper closest to the given size, either way round. */
function paperOfDimensions(width: number, height: number): PaperSize {
    const short = Math.min(width, height);
    const long = Math.max(width, height);
    let best: PaperSize = 'A4';
    let distance = Number.POSITIVE_INFINITY;

    (Object.keys(PAPER_SIZES) as PaperSize[]).forEach((paper) => {
        const size = PAPER_SIZES[paper];
        const gap = Math.abs(size.width - short) + Math.abs(size.height - long);

        if (gap < distance) {
            best = paper;
            distance = gap;
        }
    });

    return best;
}

function toMillimetres(value: string): number | null {
    const match = value.trim().match(/^(-?\d*\.?\d+)\s*(mm|cm|in|pt|px)?$/);

    if (match === null) {
        return null;
    }

    const number = parseFloat(match[1]);

    switch (match[2]) {
        case 'cm':
            return number * 10;
        case 'in':
            return number * 25.4;
        case 'pt':
            return (number * 25.4) / 72;
        case 'px':
            return number / PX_PER_MM;
        default:
            // A bare 0, or millimetres.
            return number;
    }
}

function round(value: number): number {
    return Math.round(value * 10) / 10;
}
