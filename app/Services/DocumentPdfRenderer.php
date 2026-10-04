<?php

namespace App\Services;

use App\Models\ProcurementDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\Filesystem\Filesystem;
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
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Serif');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        // Inline PHP inside the document body must stay off: bodies are
        // administrator authored templates, never trusted code.
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());

        $dompdf = new Dompdf($options);
        $isLandscape = str_contains(strtolower($document->rendered_body), 'landscape');
        $dompdf->setPaper('A4', $isLandscape ? 'landscape' : 'portrait');
        $dompdf->loadHtml($this->generator->printableHtml($document, forPdf: true), 'UTF-8');
        $dompdf->render();

        $this->stampFooter($dompdf, $document);

        return (string) $dompdf->output();
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
        $isLandscape = str_contains(strtolower($body), 'landscape');
        $footerY = $isLandscape ? 560 : 800;
        $pageTextX = $isLandscape ? 700 : 500;

        $isLampiranSp = in_array($docTypeCode, [
            'lampiran-sp-barang',
            'lampiran-sp-jasa',
            'surat-pesanan-barang',
            'surat-pesanan-jasa',
        ]) || str_contains(strtolower($docTypeCode), 'lampiran-sp')
           || str_contains(strtolower($docTitle), 'lampiran sp')
           || str_contains(strtolower($document?->title ?? ''), 'lampiran sp')
           || str_contains(strtolower($body), 'syarat umum');

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
    protected function path(ProcurementDocument $document): string
    {
        $fingerprint = substr(sha1($document->rendered_body), 0, 12);

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
