/**
 * Conversion between a stored document template (HTML with {{placeholder}}
 * markers) and the page the visual template editor works on.
 *
 * The administrator never sees markup: placeholders become labelled chips,
 * the template's own <style> blocks are kept out of the editable area, and
 * everything is turned back into the stored format on save.
 */

import {
    pageSetupCss,
    paperDimensions,
    readPageSetup,
    withoutPageSetup,
} from '@/lib/page-setup';
import type { PageSetup } from '@/lib/page-setup';

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
    return readPageSetup(body).landscape;
}

/** Marks the page setup chosen in an editor (see lib/page-setup). */
const PAGE_SETUP_ATTRIBUTE = 'data-page-setup';

/** The sheet's margins as CSS padding. */
function sheetPadding(setup: PageSetup): string {
    const { top, right, bottom, left } = setup.margins;

    return `${top}mm ${right}mm ${bottom}mm ${left}mm`;
}

/**
 * Editor-only styling: the grey desk, the sheet of paper and the chips.
 *
 * The sheet takes the paper and margins of the page setup. Its @page only
 * reaches the PDF, so on screen the sheet is given the same size itself.
 */
function editorStylesheet(setup: PageSetup, fullDocument: boolean): string {
    const { width, height } = paperDimensions(setup);
    const paper = `width: ${width}mm; min-height: ${height}mm;`;
    const { right, left } = setup.margins;

    const page = fullDocument
        ? `html { background: #e8ebf0; }
           body { ${paper} box-sizing: border-box; margin: 24px auto !important; padding: ${sheetPadding(setup)} !important; background: #fff;
                  box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`
        : `html { background: #e8ebf0; }
           body { ${paper} box-sizing: border-box; margin: 28px auto; padding: ${sheetPadding(setup)}; background: #fff;
                  box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`;

    return `${page}
        body { outline: none; caret-color: #0b5cad; }
        body:empty::before, body.is-empty::before { content: 'Mulai ketik isi dokumen di sini…'; color: #9ca3af; }
        ::selection { background: #cfe3fb; }
        .ph { display: inline-block; padding: 0 4px; margin: 0 1px; border-radius: 3px; background: #e3eefc;
              color: #0b4a8f; border: 1px solid #b9d3f5; font-family: system-ui, sans-serif; font-size: .85em;
              line-height: 1.35; white-space: nowrap; cursor: default; user-select: all; text-indent: 0; }
        .ph.is-unknown { background: #fdecec; color: #9b1c1c; border-color: #f5c2c2; }
        .page-break { height: 0; border-top: 2px dashed #94a3b8; margin: 18px -${right}mm 18px -${left}mm; position: relative; }
        .page-break::after { content: 'Halaman baru'; position: absolute; left: 50%; top: -9px; transform: translateX(-50%);
              background: #e8ebf0; color: #475569; font: 600 10px system-ui, sans-serif; padding: 1px 8px; border-radius: 8px; }
        .signature td, .lampiran-head td, table.plain td, table.plain th { outline: 1px dashed #cbd5e1; outline-offset: -1px; }
        td:focus-within, th:focus-within { background-color: #f5f9ff; }
        img { max-width: 100%; }
        /* Off-screen chapters of long documents are not laid out until seen. */
        .cover, .bab, .lampiran { content-visibility: auto; contain-intrinsic-size: auto 1100px; }
        /* The RKS cover's artwork runs past the cover to the edge of the
           paper, but never over the page after it. */
        .cover:has(> .rks-cover) { content-visibility: visible; }
        .rks-cover { clip-path: inset(-${setup.margins.top}mm -${right}mm 0 -${left}mm); }`;
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
    const editorStyles = `<style ${EDITOR_ATTRIBUTE} id="editor-page">${editorStylesheet(readPageSetup(body), fullDocument)}</style>`;

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
        .map((style) =>
            style.hasAttribute(PAGE_SETUP_ATTRIBUTE)
                ? `<style ${PAGE_SETUP_ATTRIBUTE}>${style.textContent ?? ''}</style>`
                : `<style>${style.textContent ?? ''}</style>`,
        )
        .join('\n');

    const content = body.innerHTML.trim();

    return styles === '' ? content : `${styles}\n${content}`;
}

/**
 * The page setup of a template as it stands without one chosen in the
 * editor: its own @page rules, or the house default.
 */
export function templatePageSetup(body: string): PageSetup {
    return readPageSetup(withoutPageSetup(body));
}

/**
 * Lay the template in the editor frame on another paper or margins, kept as
 * the template's page setup block; `null` takes the block out, back to the
 * template's own setup (`fallback`).
 */
export function setEditorPageSetup(
    doc: Document,
    setup: PageSetup | null,
    fallback: PageSetup,
    fullDocument: boolean,
): void {
    doc.querySelectorAll(`style[${PAGE_SETUP_ATTRIBUTE}]`).forEach((style) =>
        style.remove(),
    );

    if (setup !== null) {
        const style = doc.createElement('style');
        style.setAttribute(TEMPLATE_STYLE_ATTRIBUTE, '');
        style.setAttribute(PAGE_SETUP_ATTRIBUTE, '');
        style.textContent = pageSetupCss(setup);
        doc.head.appendChild(style);
    }

    const editorStyle = doc.getElementById('editor-page');

    if (editorStyle !== null) {
        // Kept last in the head, so the sheet's size wins on screen.
        editorStyle.textContent = editorStylesheet(
            setup ?? fallback,
            fullDocument,
        );
        doc.head.appendChild(editorStyle);
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
    tanggal_terbit: '06 - 02 - 2023',
    tanggal_terbit_tor: '06 Februari 2023',
    tahun_terbit_tor: '2023',
    nama_penyusun: 'Galih Prima Yoga',
    nama_asman_pengguna: 'Asman Pemeliharaan',
    nama_asman_engineering: 'Asman Engineering',
    // The SPK/PJ layout; an SPPL signs with three.
    pengesahan_tor:
        '<table class="approval-table"><tr><td><b>DISUSUN OLEH:</b></td></tr><tr><td>PENYUSUN</td></tr><tr><td><div class="gap"></div><span class="name">( GALIH PRIMA YOGA )</span></td></tr></table>' +
        '<table class="approval-table two" style="margin-top:20px;"><tr><td colspan="2"><b>DIPERIKSA/DIKETAHUI OLEH:</b></td></tr><tr><td>ASMAN PEMELIHARAAN</td><td>ASMAN ENGINEERING</td></tr><tr><td><div class="gap"></div><span class="name">( ASMAN PEMELIHARAAN )</span></td><td><div class="gap"></div><span class="name">( ASMAN ENGINEERING )</span></td></tr></table>' +
        '<table class="approval-table" style="margin-top:20px;"><tr><td><b>DISETUJUI OLEH:</b></td></tr><tr><td>MANAGER</td></tr><tr><td><div class="gap"></div><span class="name">( MUHAMMAD RUSLI )</span></td></tr></table>',
    // The partner's letters for Keuangan.
    jenis_dokumen_kontrak_judul: 'Surat Perintah Kerja (SPK)',
    nomor_kontrak: 'KDD066.SPK/612/UPKD/2026',
    tanggal_kontrak: '30 Juli 2026',
    nilai_setelah_nego_total_angka: '289.266.000',
    nilai_setelah_nego_total_terbilang:
        'Dua Ratus Delapan Puluh Sembilan Juta Dua Ratus Enam Puluh Enam Ribu Rupiah',
    nama_bank: 'BANK BRI',
    nomor_rekening: '0587.01.000012.30.6',
    nama_pemilik_rekening: 'CV. BINTANG MAS',
    masa_garansi_bulan: '6',
    masa_garansi_bulan_terbilang: 'enam',
    unit_penerima: 'PLTD WUA WUA',
    up_penerima: 'MANAGER ULPLTD WUA WUA',
    nama_penerima: 'SURYADI PRATAMA',
    nama_manager_penerima: 'ABDUL RAHMAN KADIR',
    judul_berita_acara: 'BERITA ACARA PENYELESAIAN PEKERJAAN',
    nama_berita_acara: 'Berita Acara Pemeriksaan Barang',
    tabel_surat_jalan:
        '<table class="surat-items"><tr><th>No.</th><th>Nama Barang</th><th>Part Number</th><th>Vol.</th><th>Sat.</th><th>Keterangan</th></tr>' +
        '<tr><td class="c">1.</td><td>Packing Oil Cooler</td><td class="c">37739-10200</td><td class="c">1</td><td class="c">Bh</td><td></td></tr>' +
        '<tr><td class="c">2.</td><td>O Ring</td><td class="c">05507-10140</td><td class="c">16</td><td class="c">Bh</td><td></td></tr></table>',
};

/** Render a template body with sample values as a standalone page. */
export function renderPreviewHtml(
    body: string,
    documentStylesheet?: string,
): string {
    const rendered = body.replace(PLACEHOLDER_PATTERN, (match, key: string) => {
        return SAMPLE_VALUES[key.toLowerCase()] ?? match;
    });

    const setup = readPageSetup(body);
    const { width } = paperDimensions(setup);

    if (isFullDocument(rendered)) {
        // Its @page margins only apply in the PDF; on screen the sheet gets
        // the same margin, for screens only.
        const sheet = `<style>@media screen { html { background: #e8ebf0; } body { max-width: ${width}mm; box-sizing: border-box; margin: 16px auto !important; padding: ${sheetPadding(setup)} !important; background: #fff; } }</style>`;

        return /<\/head>/i.test(rendered)
            ? rendered.replace(/<\/head>/i, `${sheet}</head>`)
            : sheet + rendered;
    }

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
    body { max-width: ${width}mm; margin: 0 auto; padding: ${sheetPadding(setup)}; box-sizing: border-box; }
    .page-break { border-top: 1px dashed #cbd5e1; margin: 16px 0; }
</style>
</head>
<body>${rendered}</body>
</html>`;
}
