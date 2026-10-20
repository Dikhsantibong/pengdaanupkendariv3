<?php

namespace Tests\Feature\Procurements;

use App\Models\ProcurementDocument;
use App\Services\DocumentPdfRenderer;
use Tests\TestCase;

/**
 * A cell merged over many rows (the Batas Waktu of a Surat Pesanan) loses
 * its borders where the table runs onto the next sheet: the printed PDF
 * cuts it into one merged cell per sheet.
 */
class MergedCellPageBreakTest extends TestCase
{
    public function test_a_merged_cell_running_over_sheets_is_split_per_sheet(): void
    {
        $html = $this->html($this->table(60));
        $split = app(DocumentPdfRenderer::class)->withRowSpansPerSheet($html, false);

        preg_match_all('/<td rowspan="(\d+)"[^>]*>30 Oktober 2026<\/td>/', $split, $cells);

        $this->assertGreaterThanOrEqual(2, count($cells[1]));
        $this->assertSame(60, array_sum(array_map('intval', $cells[1])));
        // Every row still has its seven columns: the date sits last.
        $this->assertSame(count($cells[1]), substr_count($split, '30 Oktober 2026'));
    }

    public function test_a_table_on_one_sheet_is_left_as_it_is(): void
    {
        $html = $this->html($this->table(5));

        $this->assertSame($html, app(DocumentPdfRenderer::class)->withRowSpansPerSheet($html, false));
    }

    public function test_the_long_surat_pesanan_still_prints(): void
    {
        $pdf = app(DocumentPdfRenderer::class)->render(new ProcurementDocument([
            'title' => 'Surat Pesanan',
            'rendered_body' => $this->table(60),
        ]));

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    protected function table(int $rows): string
    {
        $body = '';

        for ($row = 0; $row < $rows; $row++) {
            $date = $row === 0 ? '<td rowspan="'.$rows.'" style="border: 1px solid #000;">30 Oktober 2026</td>' : '';
            $body .= '<tr><td>'.($row + 1).'</td><td>Barang '.($row + 1).'</td><td>1</td><td>Bh</td><td>Rp 1.000</td><td>Rp 1.000</td>'.$date.'</tr>';
        }

        return '<table><thead><tr><th>No</th><th>Nama</th><th>Vol</th><th>Sat</th><th>Harga</th><th>Jumlah</th><th>Batas Waktu</th></tr></thead><tbody>'.$body.'</tbody></table>';
    }

    protected function html(string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head><body>'.$body.'</body></html>';
    }
}
