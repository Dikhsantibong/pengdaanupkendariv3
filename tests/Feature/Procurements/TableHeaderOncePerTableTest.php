<?php

namespace Tests\Feature\Procurements;

use App\Models\ProcurementDocument;
use App\Services\DocumentGenerator;
use DOMElement;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;
use Tests\TestCase;

/**
 * A long table (the items of a Surat Pesanan) prints its heading once,
 * where it starts, rather than again at the top of every sheet.
 */
class TableHeaderOncePerTableTest extends TestCase
{
    public function test_the_heading_rows_become_ordinary_rows_when_printed(): void
    {
        $html = app(DocumentGenerator::class)->printableHtml(new ProcurementDocument([
            'title' => 'Surat Pesanan',
            'rendered_body' => $this->table(60),
        ]), forPdf: true);

        $this->assertStringNotContainsStringIgnoringCase('<thead', $html);
        $this->assertSame(1, substr_count($html, '>Harga Satuan</th>'));
        $this->assertStringContainsString('<tbody class="kepala">', $html);
    }

    public function test_the_pdf_draws_the_heading_once_over_many_sheets(): void
    {
        $original = $this->html($this->table(60));
        $printed = $this->html(DocumentGenerator::withoutRepeatedTableHeaders($this->table(60)));

        [$headingsBefore, $sheets] = $this->drawnHeadings($original);
        [$headingsAfter] = $this->drawnHeadings($printed);

        $this->assertGreaterThan(1, $sheets);
        // Before: the heading came back on every sheet.
        $this->assertSame($sheets, $headingsBefore);
        $this->assertSame(1, $headingsAfter);
    }

    public function test_a_table_without_a_heading_group_is_left_alone(): void
    {
        $html = '<table><tbody><tr><td>Thead bukan tag</td></tr></tbody></table>';

        $this->assertSame($html, DocumentGenerator::withoutRepeatedTableHeaders($html));
    }

    /**
     * How many times the "Harga Satuan" heading cell is drawn, and on how
     * many sheets the table lies.
     *
     * @return array{int, int}
     */
    protected function drawnHeadings(string $html): array
    {
        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html, 'UTF-8');

        $headings = 0;
        $sheets = 0;

        $dompdf->setCallbacks([[
            'event' => 'begin_frame',
            'f' => function (Frame $frame, Canvas $canvas) use (&$headings, &$sheets): void {
                $node = $frame->get_node();
                $sheets = max($sheets, $canvas->get_page_number());

                if ($node instanceof DOMElement && $node->nodeName === 'th' && trim($node->textContent) === 'Harga Satuan') {
                    $headings++;
                }
            },
        ]]);

        $dompdf->render();

        return [$headings, $sheets];
    }

    protected function table(int $rows): string
    {
        $body = '';

        for ($row = 1; $row <= $rows; $row++) {
            $body .= "<tr><td>{$row}</td><td>Barang {$row}</td><td>Rp 1.000</td></tr>";
        }

        return '<table><thead class="kepala"><tr><th>No</th><th>Nama</th><th>Harga Satuan</th></tr></thead><tbody>'.$body.'</tbody></table>';
    }

    protected function html(string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head><body>'.$body.'</body></html>';
    }
}
