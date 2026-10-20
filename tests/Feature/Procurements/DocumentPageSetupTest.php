<?php

namespace Tests\Feature\Procurements;

use App\Models\ProcurementDocument;
use App\Services\DocumentPdfRenderer;
use Tests\TestCase;

/**
 * The page setup chosen in the visual editors (paper, orientation and
 * margins) travels in the document as one `<style data-page-setup>` block of
 * `@page`, and the PDF is laid out on that paper.
 */
class DocumentPageSetupTest extends TestCase
{
    public function test_a_document_without_a_setup_prints_on_a4(): void
    {
        $this->assertSame([595.28, 841.89], $this->sheet('<p>Isi dokumen.</p>'));
    }

    public function test_the_chosen_paper_and_orientation_reach_the_pdf(): void
    {
        $f4 = '<p>Isi dokumen.</p>'.$this->pageSetup('215mm 330mm', '20mm 15mm 20mm 25mm');
        $this->assertSame([609.45, 935.43], $this->sheet($f4));

        $legalLandscape = '<p>Isi dokumen.</p>'.$this->pageSetup('355.6mm 215.9mm', '15mm');
        $this->assertSame([1008.0, 612.0], $this->sheet($legalLandscape));
    }

    public function test_the_setup_wins_over_the_templates_own_page_rule(): void
    {
        $body = '<style>@page { size: A4 landscape; margin: 10mm; }</style><p>Isi dokumen.</p>'
            .$this->pageSetup('210mm 297mm', '20mm 15mm');

        $this->assertSame([595.28, 841.89], $this->sheet($body));
    }

    public function test_the_margins_change_where_the_sheets_break(): void
    {
        $paragraphs = str_repeat('<p>Paragraf isi dokumen yang cukup panjang untuk mengisi satu baris penuh di kertas, diulang terus sampai beberapa halaman.</p>', 160);

        $narrow = $this->pages($paragraphs.$this->pageSetup('210mm 297mm', '10mm'));
        $wide = $this->pages($paragraphs.$this->pageSetup('210mm 297mm', '40mm'));
        $taller = $this->pages($paragraphs.$this->pageSetup('215.9mm 355.6mm', '10mm'));

        $this->assertGreaterThan($narrow, $wide);
        $this->assertLessThan($narrow, $taller);
    }

    public function test_the_footer_keeps_its_place_on_a_taller_sheet(): void
    {
        // PDF coordinates run up from the foot: the footer sits as high above
        // the foot of an F4 sheet as of an A4 one, and as far from the right.
        $a4 = $this->textPositions('<p>Isi dokumen.</p>');
        $f4 = $this->textPositions('<p>Isi dokumen.</p>'.$this->pageSetup('215mm 330mm', '20mm 15mm'));

        $this->assertContains('56.000 32.578', $a4);
        $this->assertContains('500.000 32.578', $a4);
        $this->assertContains('56.000 32.578', $f4);
        $this->assertContains('514.169 32.578', $f4);
    }

    protected function pageSetup(string $size, string $margin): string
    {
        return "<style data-page-setup>@page { size: {$size}; margin: {$margin}; }</style>";
    }

    /**
     * The width and height of the PDF's sheets, in points.
     *
     * @return array{0: float, 1: float}
     */
    protected function sheet(string $body): array
    {
        $pdf = app(DocumentPdfRenderer::class)->render(new ProcurementDocument([
            'title' => 'Uji',
            'rendered_body' => $body,
        ]));

        if (preg_match('#/MediaBox\s*\[0(?:\.0+)? 0(?:\.0+)? ([\d.]+) ([\d.]+)\]#', $pdf, $match) !== 1) {
            $this->fail('The PDF names no sheet size.');
        }

        return [round((float) $match[1], 2), round((float) $match[2], 2)];
    }

    /**
     * Where each run of text starts on the first sheet, "x y" in points.
     *
     * @return list<string>
     */
    protected function textPositions(string $body): array
    {
        $pdf = app(DocumentPdfRenderer::class)->render(new ProcurementDocument([
            'title' => 'Uji',
            'rendered_body' => $body,
        ]));
        $positions = [];

        preg_match_all('#stream?
(.*?)?
endstream#s', $pdf, $streams);

        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);

            if ($content !== false && preg_match_all('#BT ([\d.]+ [\d.]+) Td#', $content, $matches) > 0) {
                $positions = [...$positions, ...$matches[1]];
            }
        }

        return $positions;
    }

    protected function pages(string $body): int
    {
        return app(DocumentPdfRenderer::class)->sheetStarts(new ProcurementDocument([
            'title' => 'Uji',
            'rendered_body' => $body,
        ]))['page_count'];
    }
}
