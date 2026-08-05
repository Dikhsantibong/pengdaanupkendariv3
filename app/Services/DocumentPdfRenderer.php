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
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->generator->printableHtml($document, forPdf: true), 'UTF-8');
        $dompdf->render();

        $this->stampFooter($dompdf);

        return (string) $dompdf->output();
    }

    /**
     * Drop the running footer and page numbers onto every page.
     */
    protected function stampFooter(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Serif', 'normal');

        if ($font === null) {
            return;
        }

        $canvas->page_text(56, 800, 'Rencana Kerja dan Syarat-syarat', $font, 8, [0.35, 0.35, 0.35]);
        $canvas->page_text(500, 800, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 8, [0.35, 0.35, 0.35]);
    }

    /**
     * The cache path of a document's PDF.
     */
    protected function path(ProcurementDocument $document): string
    {
        return self::DIRECTORY.'/'.$document->getKey().'.pdf';
    }

    /**
     * The disk the PDFs are cached on.
     */
    protected function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
