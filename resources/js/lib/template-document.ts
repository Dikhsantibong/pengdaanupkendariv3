/**
 * Conversion between a stored document template (HTML with {{placeholder}}
 * markers) and the page the visual template editor works on.
 *
 * The administrator never sees markup: placeholders become labelled chips,
 * the template's own <style> blocks are kept out of the editable area, and
 * everything is turned back into the stored format on save.
 */

export type PlaceholderOption = { key: string; label: string };

const PLACEHOLDER_PATTERN = /\{\{\s*([a-z0-9_]+)\s*\}\}/gi;

/** Marks elements that exist only inside the editor and are never saved. */
const EDITOR_ATTRIBUTE = 'data-editor-only';

/** Marks the template's own <style> blocks while they sit in the editor head. */
const TEMPLATE_STYLE_ATTRIBUTE = 'data-template-style';

export function isFullDocument(body: string): boolean {
    return /<html[\s>]/i.test(body);
}

export function isLandscape(body: string): boolean {
    return body.toLowerCase().includes('landscape');
}

/** Editor-only styling: the grey desk, the sheet of paper and the chips. */
function editorStylesheet(landscape: boolean, fullDocument: boolean): string {
    const paper = landscape
        ? 'width: 297mm; min-height: 210mm;'
        : 'width: 210mm; min-height: 297mm;';

    const page = fullDocument
        ? `html { background: #e8ebf0; } body { background: #fff; margin: 24px auto; box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`
        : `html { background: #e8ebf0; }
           body { ${paper} box-sizing: border-box; margin: 28px auto; padding: 20mm 15mm; background: #fff;
                  box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`;

    return `${page}
        body { outline: none; caret-color: #0b5cad; }
        body:empty::before, body.is-empty::before { content: 'Mulai ketik isi dokumen di sini…'; color: #9ca3af; }
        ::selection { background: #cfe3fb; }
        .ph { display: inline-block; padding: 0 4px; margin: 0 1px; border-radius: 3px; background: #e3eefc;
              color: #0b4a8f; border: 1px solid #b9d3f5; font-family: system-ui, sans-serif; font-size: .85em;
              line-height: 1.35; white-space: nowrap; cursor: default; user-select: all; text-indent: 0; }
        .ph.is-unknown { background: #fdecec; color: #9b1c1c; border-color: #f5c2c2; }
        .page-break { height: 0; border-top: 2px dashed #94a3b8; margin: 18px -15mm; position: relative; }
        .page-break::after { content: 'Halaman baru'; position: absolute; left: 50%; top: -9px; transform: translateX(-50%);
              background: #e8ebf0; color: #475569; font: 600 10px system-ui, sans-serif; padding: 1px 8px; border-radius: 8px; }
        .signature td, .lampiran-head td, table.plain td, table.plain th { outline: 1px dashed #cbd5e1; outline-offset: -1px; }
        td:focus-within, th:focus-within { background-color: #f5f9ff; }
        img { max-width: 100%; }`;
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/** The chip shown in the editor in place of a {{placeholder}}. */
export function placeholderChipHtml(
    key: string,
    labels: Map<string, string>,
): string {
    const label = labels.get(key);
    const className = label === undefined ? 'ph is-unknown' : 'ph';

    return `<span class="${className}" contenteditable="false" data-ph="${escapeHtml(key)}" title="{{${escapeHtml(key)}}}">${escapeHtml(label ?? key)}</span>`;
}

/**
 * Build the srcdoc for the editor frame.
 *
 * Fragments are laid on a simulated A4 sheet that uses the same stylesheet as
 * generated documents; full documents keep their own head and styles.
 */
export function buildEditorDocument(
    body: string,
    documentStylesheet: string,
): string {
    const fullDocument = isFullDocument(body);
    const landscape = isLandscape(body);
    const editorStyles = `<style ${EDITOR_ATTRIBUTE} id="editor-page">${editorStylesheet(landscape, fullDocument)}</style>`;

    if (fullDocument) {
        const withStyles = /<\/head>/i.test(body)
            ? body.replace(/<\/head>/i, `${editorStyles}</head>`)
            : body.replace(
                  /<html([^>]*)>/i,
                  `<html$1><head>${editorStyles}</head>`,
              );

        return withStyles;
    }

    return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style ${EDITOR_ATTRIBUTE}>${documentStylesheet}</style>
${editorStyles}
</head>
<body>${body}</body>
</html>`;
}

/**
 * Prepare the loaded editor frame: move the template's own styles out of the
 * editable area and turn placeholders into chips.
 */
export function prepareEditorDocument(
    doc: Document,
    placeholders: PlaceholderOption[],
): void {
    const labels = new Map(placeholders.map((item) => [item.key, item.label]));

    doc.body.querySelectorAll('style').forEach((style) => {
        style.setAttribute(TEMPLATE_STYLE_ATTRIBUTE, '');
        doc.head.appendChild(style);
    });

    const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
    const textNodes: Text[] = [];

    while (walker.nextNode()) {
        const node = walker.currentNode as Text;

        if (PLACEHOLDER_PATTERN.test(node.data)) {
            textNodes.push(node);
        }

        PLACEHOLDER_PATTERN.lastIndex = 0;
    }

    textNodes.forEach((node) => {
        const html = escapeHtml(node.data).replace(
            PLACEHOLDER_PATTERN,
            (_match, key: string) =>
                placeholderChipHtml(key.toLowerCase(), labels),
        );
        const holder = doc.createElement('span');
        holder.innerHTML = html;
        node.replaceWith(...Array.from(holder.childNodes));
    });

    doc.body.contentEditable = 'true';
    doc.body.spellcheck = false;
}

/** Turn chips in a cloned tree back into {{placeholder}} text. */
function restorePlaceholders(root: ParentNode): void {
    root.querySelectorAll('span.ph[data-ph]').forEach((chip) => {
        const key = chip.getAttribute('data-ph') ?? '';
        chip.replaceWith(chip.ownerDocument.createTextNode(`{{${key}}}`));
    });
}

/** Serialize the editor frame back to the stored template format. */
export function serializeEditorDocument(
    doc: Document,
    fullDocument: boolean,
): string {
    if (fullDocument) {
        const clone = doc.documentElement.cloneNode(true) as HTMLElement;
        clone
            .querySelectorAll(`[${EDITOR_ATTRIBUTE}]`)
            .forEach((node) => node.remove());
        clone
            .querySelectorAll(`[${TEMPLATE_STYLE_ATTRIBUTE}]`)
            .forEach((node) => node.removeAttribute(TEMPLATE_STYLE_ATTRIBUTE));
        const body = clone.querySelector('body');
        body?.removeAttribute('contenteditable');
        body?.removeAttribute('spellcheck');
        body?.classList.remove('is-empty');

        if (body?.getAttribute('class') === '') {
            body.removeAttribute('class');
        }

        restorePlaceholders(clone);

        return `<!DOCTYPE html>\n${clone.outerHTML}`;
    }

    const body = doc.body.cloneNode(true) as HTMLElement;
    restorePlaceholders(body);

    const styles = Array.from(
        doc.head.querySelectorAll(`style[${TEMPLATE_STYLE_ATTRIBUTE}]`),
    )
        .map((style) => `<style>${style.textContent ?? ''}</style>`)
        .join('\n');

    const content = body.innerHTML.trim();

    return styles === '' ? content : `${styles}\n${content}`;
}

/** Switch the paper orientation of the template styles in the editor frame. */
export function setEditorOrientation(doc: Document, landscape: boolean): void {
    const templateStyles = Array.from(
        doc.head.querySelectorAll<HTMLStyleElement>(
            `style[${TEMPLATE_STYLE_ATTRIBUTE}]`,
        ),
    );

    if (landscape) {
        const hasLandscape = templateStyles.some((style) =>
            (style.textContent ?? '').toLowerCase().includes('landscape'),
        );

        if (!hasLandscape) {
            const style = doc.createElement('style');
            style.setAttribute(TEMPLATE_STYLE_ATTRIBUTE, '');
            style.textContent = '@page { size: A4 landscape; }';
            doc.head.appendChild(style);
        }
    } else {
        templateStyles.forEach((style) => {
            const css = style.textContent ?? '';

            if (css.trim() === '@page { size: A4 landscape; }') {
                style.remove();

                return;
            }

            style.textContent = css.replace(/landscape/gi, 'portrait');
        });
    }

    const editorStyle = doc.getElementById('editor-page');

    if (editorStyle !== null) {
        editorStyle.textContent = editorStylesheet(landscape, false);
    }
}

/** Sample values used by the template preview. */
export const SAMPLE_VALUES: Record<string, string> = {
    nomor_pengadaan: '001/PENG/612/UPKD/2026',
    nama_pengadaan: 'JASA PEMBUATAN WEB DIGITALISASI PLN NP UP KENDARI',
    nama_mitra: 'PT KREATIF TEKNOLOGI MAJU BERSAMA',
    nama_direktur: 'Budi Santoso',
    alamat_mitra: 'Jl. Malaka No. 12, Kendari, Sulawesi Tenggara',
    alamat_perusahaan: 'Jl. Malaka No. 12, Kendari, Sulawesi Tenggara',
    direksi_pekerjaan: 'Manager UPDK Kendari',
    unit_tujuan: 'PLN NP UP Kendari',
    metode_pengadaan: 'Pengadaan Langsung',
    sumber_anggaran: 'AO',
    sumber_anggaran_keterangan: 'Anggaran Operasi',
    jenis_kontrak: 'Lumsum',
    nomor_nota_dinas_manager: 'ND-012/UPKD/2026',
    nomor_pr_ro: 'PR-2026-0042',
    nomor_prk: 'KD262O0306',
    nilai_hpe: 'Rp 85.000.000,00',
    nilai_hpe_angka: '85.000.000',
    nilai_hpe_terbilang: 'Delapan puluh lima juta rupiah',
    status_progres: 'Penyusunan TOR',
    pic_perencana: 'Dikhsan Tibong',
    pic_pelaksana: 'Andi Pratama',
    target_penyelesaian: '30 April 2026',
    tanggal_dokumen: '04 Maret 2026',
    tahun: '2026',
    checklist_perencanaan:
        '<ul><li>[✓] TOR</li><li>[✓] RAB</li><li>[✓] HPE</li></ul>',
    checklist_pelaksanaan: '<ul><li>[-] Penawaran</li></ul>',
};

/** Render a template body with sample values as a standalone page. */
export function renderPreviewHtml(
    body: string,
    documentStylesheet?: string,
): string {
    const rendered = body.replace(PLACEHOLDER_PATTERN, (match, key: string) => {
        return SAMPLE_VALUES[key.toLowerCase()] ?? match;
    });

    if (isFullDocument(rendered)) {
        return rendered;
    }

    const landscape = isLandscape(body);
    const baseStyles =
        documentStylesheet ??
        `body { font-family: "Times New Roman", Times, serif; font-size: 10pt; line-height: 1.45; color: #111; }
         table { width: 100%; border-collapse: collapse; margin: 8pt 0; }
         td, th { border: 1px solid #444; padding: 4pt 6pt; vertical-align: top; }
         table.plain td, table.plain th, .signature td, .lampiran-head td { border: none; }
         .signature td { text-align: center; } .signature .space { height: 56pt; }
         .signature .name { font-weight: bold; text-decoration: underline; }`;

    return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    ${baseStyles}
    html { background: #fff; }
    body { max-width: ${landscape ? '297mm' : '210mm'}; margin: 0 auto; padding: 15mm; box-sizing: border-box; }
    .page-break { border-top: 1px dashed #cbd5e1; margin: 16px 0; }
</style>
</head>
<body>${rendered}</body>
</html>`;
}
