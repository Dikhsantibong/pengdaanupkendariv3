<?php

namespace App\Services;

use App\Models\ProcurementAmendment;
use App\Models\ProcurementDocument;
use Dom\Element;
use Dom\HTMLDocument;
use DOMElement;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Turns an archived document into a PDF.
 *
 * The rendered body of a document never changes once it has been generated, so
 * the PDF is written to disk on first request and served from there afterwards.
 */
class DocumentPdfRenderer
{
    /**
     * Where the generated PDFs live on the local disk.
     */
    private const DIRECTORY = 'documents/pdf';

    /**
     * Raised whenever the running headers or footers change, so cached PDFs
     * are drawn again with them.
     */
    private const LAYOUT_VERSION = '5';

    /**
     * Where the PDFs of unsaved drafts are kept between previews.
     */
    private const PREVIEW_DIRECTORY = 'documents/pdf-preview';

    public function __construct(protected DocumentGenerator $generator) {}

    /**
     * Get the PDF bytes for a document, building and caching them if needed.
     */
    public function bytes(ProcurementDocument $document): string
    {
        $disk = $this->disk();
        $path = $this->path($document);

        if ($disk->exists($path)) {
            $cached = $disk->get($path);

            if ($cached !== null && $cached !== '') {
                return $cached;
            }
        }

        $pdf = $this->render($document);

        $disk->put($path, $pdf);
        $this->pruneOtherRevisions($document, $path);

        return $pdf;
    }

    /**
     * Get the PDF of an unsaved draft, reusing it while the draft is unchanged.
     *
     * A long document takes seconds to lay out, so asking for the same
     * preview twice (or previewing the saved version) is served from disk.
     * Only the latest preview of each document is kept.
     */
    public function previewBytes(ProcurementDocument $draft): string
    {
        $original = $draft->getOriginal('rendered_body');

        if ($draft->rendered_body === $original && $draft->title === $draft->getOriginal('title')) {
            return $this->bytes($draft);
        }

        $disk = $this->disk();
        $prefix = self::PREVIEW_DIRECTORY.'/'.$draft->getKey().'-';
        $path = $prefix.sha1($draft->title.'|'.$draft->rendered_body).'.pdf';

        if ($disk->exists($path)) {
            $cached = $disk->get($path);

            if ($cached !== null && $cached !== '') {
                return $cached;
            }
        }

        $pdf = $this->render($draft);

        foreach ($disk->files(self::PREVIEW_DIRECTORY) as $file) {
            if (str_starts_with($file, $prefix)) {
                $disk->delete($file);
            }
        }

        $disk->put($path, $pdf);

        return $pdf;
    }

    /**
     * The download file name of a document's PDF.
     */
    public function fileName(ProcurementDocument $document): string
    {
        return preg_replace('/\.html$/', '', $document->file_name).'.pdf';
    }

    /**
     * Render the PDF without touching the cache.
     */
    public function render(ProcurementDocument $document): string
    {
        $dompdf = $this->laidOut($document);

        $this->stampFooter($dompdf, $document);

        return (string) $dompdf->output();
    }

    /**
     * Where each printed sheet of a draft begins, as the PDF engine lays it out.
     *
     * The editor numbers the elements of the body with a `data-pg` attribute
     * before sending it; the answer names, for every sheet after the first,
     * the lowest numbered element that is first drawn on that sheet. The
     * layout of the same body never changes, so it is remembered for a day.
     *
     * @return array{page_count: int, sheet_starts: list<array{page: int, index: int}>}
     */
    public function sheetStarts(ProcurementDocument $draft): array
    {
        $key = 'document-sheets:'.sha1($draft->title.'|'.$draft->rendered_body);

        return Cache::remember($key, now()->addDay(), function () use ($draft): array {
            /** @var array<int, int> $firstPage */
            $firstPage = [];
            $pageCount = 1;

            $this->laidOut($draft, function (Frame $frame, Canvas $canvas) use (&$firstPage, &$pageCount): void {
                $page = $canvas->get_page_number();
                $pageCount = max($pageCount, $page);
                $node = $frame->get_node();

                if (! $node instanceof DOMElement || ! $node->hasAttribute('data-pg')) {
                    return;
                }

                $index = (int) $node->getAttribute('data-pg');
                $firstPage[$index] = min($firstPage[$index] ?? $page, $page);
            });

            /** @var array<int, int> $starts page => index */
            $starts = [];

            foreach ($firstPage as $index => $page) {
                if ($page > 1 && $index < ($starts[$page] ?? PHP_INT_MAX)) {
                    $starts[$page] = $index;
                }
            }

            ksort($starts);

            return [
                'page_count' => $pageCount,
                'sheet_starts' => array_map(
                    fn (int $page, int $index): array => ['page' => $page, 'index' => $index],
                    array_keys($starts),
                    array_values($starts),
                ),
            ];
        });
    }

    /**
     * Lay the document out on A4 sheets, ready to be stamped and written.
     *
     * @param  (callable(Frame, Canvas): void)|null  $onFrame  told about every frame as it is drawn
     */
    protected function laidOut(ProcurementDocument $document, ?callable $onFrame = null): Dompdf
    {
        $isLandscape = str_contains(strtolower($document->rendered_body), 'landscape');
        $html = $this->generator->printableHtml($document, forPdf: true);

        // Where the sheets start is read from the document as it is; the
        // printed PDF also splits merged cells at the sheets they run over.
        if ($onFrame === null) {
            $html = $this->withRowSpansPerSheet($html, $isLandscape);
        }

        return $this->layOut($html, $isLandscape, $onFrame);
    }

    /**
     * Lay HTML out on A4 sheets (or the paper its @page names).
     *
     * @param  (callable(Frame, Canvas): void)|null  $onFrame  told about every frame as it is drawn
     */
    protected function layOut(string $html, bool $isLandscape, ?callable $onFrame = null): Dompdf
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Serif');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        // Inline PHP inside the document body must stay off: bodies are
        // administrator authored templates, never trusted code.
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', $isLandscape ? 'landscape' : 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');

        if ($onFrame !== null) {
            $dompdf->setCallbacks([[
                'event' => 'begin_frame',
                'f' => fn (Frame $frame, Canvas $canvas) => $onFrame($frame, $canvas),
            ]]);
        }

        $dompdf->render();

        return $dompdf;
    }

    /**
     * Split each merged (rowspan) cell at the sheets its rows run over.
     *
     * The PDF engine draws a cell merged over a page break without its
     * borders past the break (the Batas Waktu column of a long Surat
     * Pesanan): the cell is cut into one merged cell per sheet instead, each
     * with the same content, so every sheet shows the column whole. A table
     * that fits on one sheet is left as it is.
     */
    public function withRowSpansPerSheet(string $html, bool $isLandscape): string
    {
        if (stripos($html, 'rowspan') === false) {
            return $html;
        }

        $dom = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        /** @var list<array{0: Element, 1: list<Element>}> $spans */
        $spans = [];
        $marker = 0;

        foreach ($dom->querySelectorAll('td[rowspan], th[rowspan]') as $cell) {
            $row = $cell->parentElement;

            if ((int) $cell->getAttribute('rowspan') < 2 || $row === null || $row->tagName !== 'TR') {
                continue;
            }

            $rows = [$row];

            while (count($rows) < (int) $cell->getAttribute('rowspan') && $row->nextElementSibling?->tagName === 'TR') {
                $row = $row->nextElementSibling;
                $rows[] = $row;
            }

            foreach ($rows as $spanned) {
                if (! $spanned->hasAttribute('data-rowspan-row')) {
                    $spanned->setAttribute('data-rowspan-row', (string) $marker++);
                }
            }

            $spans[] = [$cell, $rows];
        }

        if ($spans === []) {
            return $html;
        }

        /** @var array<string, int> $pages */
        $pages = [];

        $this->layOut($dom->saveHtml(), $isLandscape, function (Frame $frame, Canvas $canvas) use (&$pages): void {
            $node = $frame->get_node();

            if ($node instanceof DOMElement && $node->hasAttribute('data-rowspan-row')) {
                $key = $node->getAttribute('data-rowspan-row');
                $pages[$key] = min($pages[$key] ?? PHP_INT_MAX, $canvas->get_page_number());
            }
        });

        $changed = false;

        foreach ($spans as [$cell, $rows]) {
            /** @var list<list<Element>> $segments */
            $segments = [];
            $lastPage = -1;

            foreach ($rows as $row) {
                $page = $pages[$row->getAttribute('data-rowspan-row')] ?? 0;

                if ($page !== $lastPage) {
                    $segments[] = [];
                    $lastPage = $page;
                }

                $segments[count($segments) - 1][] = $row;
            }

            if (count($segments) < 2) {
                continue;
            }

            $changed = true;
            $column = $this->columnOf($cell);
            $cell->setAttribute('rowspan', (string) count($segments[0]));

            foreach (array_slice($segments, 1) as $segment) {
                $copy = $cell->cloneNode(true);

                if ($copy instanceof Element) {
                    $copy->setAttribute('rowspan', (string) count($segment));
                    $this->insertAtColumn($segment[0], $copy, $column);
                }
            }
        }

        return $changed ? $dom->saveHtml() : $html;
    }

    /**
     * The column a cell starts at, counting the spans of the cells before it.
     */
    protected function columnOf(Element $cell): int
    {
        $column = 0;
        $sibling = $cell->previousElementSibling;

        while ($sibling !== null) {
            $column += max(1, (int) ($sibling->getAttribute('colspan') ?: 1));
            $sibling = $sibling->previousElementSibling;
        }

        return $column;
    }

    /**
     * Put a cell into a row where the given column starts.
     */
    protected function insertAtColumn(Element $row, Element $cell, int $column): void
    {
        $start = 0;
        $existing = $row->firstElementChild;

        while ($existing !== null) {
            if ($start >= $column) {
                $row->insertBefore($cell, $existing);

                return;
            }

            $start += max(1, (int) ($existing->getAttribute('colspan') ?: 1));
            $existing = $existing->nextElementSibling;
        }

        $row->appendChild($cell);
    }

    /**
     * Drop the running footer and page numbers onto every page.
     */
    protected function stampFooter(Dompdf $dompdf, ?ProcurementDocument $document = null): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Serif', 'normal');

        if ($font === null) {
            return;
        }

        $docTitle = $document?->documentType?->name ?? 'Dokumen Pengadaan';
        $docTypeCode = $document?->documentType?->code ?? '';
        $body = $document?->rendered_body ?? '';
        $hasCover = str_contains($body, 'cover-page') || str_contains($body, 'class="cover"');
        // The footer is measured from the foot and the right edge of the
        // sheet, so it keeps its place on any paper the page setup chose
        // (the numbers give the A4 positions it always had).
        $isLandscape = $canvas->get_width() > $canvas->get_height();
        $footerY = $canvas->get_height() - ($isLandscape ? 35.28 : 41.89);
        $pageTextX = $canvas->get_width() - ($isLandscape ? 141.89 : 95.28);

        $isLampiranSp = in_array($docTypeCode, [
            'lampiran-sp-barang',
            'lampiran-sp-jasa',
            'surat-pesanan-barang',
            'surat-pesanan-jasa',
        ]) || str_contains(strtolower($docTypeCode), 'lampiran-sp')
           || str_contains(strtolower($docTitle), 'lampiran sp')
           || str_contains(strtolower($document?->title ?? ''), 'lampiran sp')
           || str_contains(strtolower($body), 'syarat umum');

        // Amendment: the logo atop every page, and the running reference
        // (which amendment of which contract, for what work) beside the
        // parties' initials at the foot.
        if ($docTypeCode === 'amandemen-kontrak') {
            $this->stampAmendmentPages($dompdf, $font, $document);

            return;
        }

        if ($isLampiranSp) {
            $contractNumber = ! empty($document?->procurement?->number)
                ? trim($document->procurement->number)
                : (! empty($document?->procurement?->pr_po_number)
                    ? trim($document->procurement->pr_po_number)
                    : '—');
            $leftText = $contractNumber;
            $rightText = 'Pihak Pertama : ....................   Pihak Kedua : ....................';
            $footerFontSize = 7;

            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($font, $leftText, $rightText, $footerY, $footerFontSize): void {
                $canvas->text(56, $footerY, $leftText, $font, $footerFontSize, [0.25, 0.25, 0.25]);
                $pageWidth = $canvas->get_width();
                $textWidth = $fontMetrics->getTextWidth($rightText, $font, $footerFontSize);
                $rightX = $pageWidth - 56 - $textWidth;
                $canvas->text($rightX, $footerY, $rightText, $font, $footerFontSize, [0.25, 0.25, 0.25]);
            });

            return;
        }

        // SPK: contract number left, initials boxes right, page number centred.
        if (in_array($docTypeCode, ['spk', 'spk-pasal'], true)) {
            $contractNumber = trim((string) ($document?->procurement?->number ?? '')) ?: '—';
            $bold = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'bold') ?? $font;

            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($font, $bold, $contractNumber): void {
                $grey = [0.45, 0.45, 0.45];
                $pageWidth = $canvas->get_width();

                $footerY = $canvas->get_height() - 56.89;

                $canvas->text(56, $footerY, $contractNumber, $bold, 6.5, $grey);

                foreach (['PIHAK KESATU : ............', 'PIHAK KEDUA   : ............'] as $line => $text) {
                    $width = $fontMetrics->getTextWidth($text, $font, 7);
                    $canvas->text($pageWidth - 56 - $width, $footerY + ($line * 12), $text, $font, 7, $grey);
                }

                $number = (string) $pageNumber;
                $canvas->text(($pageWidth - $fontMetrics->getTextWidth($number, $font, 8)) / 2, $footerY + 30, $number, $font, 8, $grey);
            });

            return;
        }

        $isSuratPesanan = in_array($docTypeCode, [
            'surat-pesanan',
            'purchase-order',
        ]) || str_contains(strtolower($docTitle), 'surat pesanan')
           || str_contains(strtolower($document?->title ?? ''), 'surat pesanan');

        if ($isSuratPesanan) {
            return;
        }

        if ($hasCover) {
            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($font, $docTitle, $footerY, $pageTextX): void {
                if ($pageNumber > 1) {
                    $canvas->text(56, $footerY, $docTitle, $font, 8, [0.35, 0.35, 0.35]);
                    $text = "Halaman {$pageNumber} dari {$pageCount}";
                    $canvas->text($pageTextX, $footerY, $text, $font, 8, [0.35, 0.35, 0.35]);
                }
            });
        } else {
            $canvas->page_text(56, $footerY, $docTitle, $font, 8, [0.35, 0.35, 0.35]);
            $canvas->page_text($pageTextX, $footerY, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 8, [0.35, 0.35, 0.35]);
        }
    }

    /**
     * The cache path of a document's PDF.
     *
     * The body is fingerprinted into the name so a corrected document never
     * serves the PDF of the version before the correction: a changed body is
     * simply a different, still immutable, cache entry.
     */
    /**
     * The logo atop every later page of an amendment, and its footer: the
     * amendment and the contract it amends, the work, and the initials of
     * both parties.
     */
    protected function stampAmendmentPages(Dompdf $dompdf, mixed $font, ?ProcurementDocument $document): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $italic = $metrics->getFont('DejaVu Sans', 'italic') ?? $font;
        $procurement = $document?->procurement;
        $amendment = $document?->procurement_amendment_id === null
            ? null
            : ProcurementAmendment::query()->find($document->procurement_amendment_id);

        $reference = trim('Amandemen '.($amendment?->roman() ?? '').' '.($procurement->number ?? '')
            .($amendment?->contract_date !== null ? ' tanggal '.$amendment->contract_date->translatedFormat('d F Y') : ''));

        // The work's name, wrapped to the left half of the footer.
        $lines = [$reference];
        $current = '';

        foreach (preg_split('/\s+/u', trim((string) ($procurement->name ?? ''))) ?: [] as $word) {
            $candidate = trim($current.' '.$word);

            if ($current !== '' && $metrics->getTextWidth($candidate, $italic, 6.5) > 250) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        $lines = array_slice($lines, 0, 4);
        $logo = public_path('logo/sidebar-logo.png');
        $size = is_file($logo) ? getimagesize($logo) : false;

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($italic, $lines, $logo, $size): void {
            $pageWidth = $canvas->get_width();

            // The first page carries the template's own logo.
            if ($pageNumber > 1 && $size !== false && $size[1] > 0) {
                $height = 34;
                $width = $height * $size[0] / $size[1];
                $canvas->image($logo, ($pageWidth - $width) / 2, 30, $width, $height);
            }

            $top = $canvas->get_height() - 51.89 - (count($lines) - 1) * 8;

            foreach ($lines as $at => $line) {
                $canvas->text(62, $top + $at * 8, $line, $italic, 6.5, [0, 0, 0]);
            }

            $paraf = 'Paraf Pihak 1: ____________     Pihak 2: ____________';
            $canvas->text($pageWidth - 57 - $fontMetrics->getTextWidth($paraf, $italic, 7), $top + (count($lines) - 1) * 8, $paraf, $italic, 7, [0, 0, 0]);
        });
    }

    protected function path(ProcurementDocument $document): string
    {
        // The layout version is part of the key, so a change to the running
        // headers and footers reaches PDFs already cached.
        $fingerprint = substr(sha1(self::LAYOUT_VERSION.'|'.$document->rendered_body), 0, 12);

        return self::DIRECTORY.'/'.$document->getKey().'-'.$fingerprint.'.pdf';
    }

    /**
     * Drop the cached PDFs built from earlier versions of this document.
     */
    protected function pruneOtherRevisions(ProcurementDocument $document, string $keep): void
    {
        $disk = $this->disk();
        $prefix = self::DIRECTORY.'/'.$document->getKey().'-';

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($file !== $keep && str_starts_with($file, $prefix)) {
                $disk->delete($file);
            }
        }
    }

    /**
     * The disk the PDFs are cached on.
     */
    protected function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
