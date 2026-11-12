<?php

namespace App\Support;

use App\Models\BudgetPlan;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes the budget plan as the Anggaran sheet: PROGRAM, KONTRAK, FISIK / BA
 * and PEMBAYARAN side by side, each header group in its own colour, amounts
 * in full rupiah.
 */
class BudgetPlanExport
{
    /**
     * The header groups: title, first and last column (0-based), and the
     * colours of the group band and of its column headers.
     *
     * @var array<int, array{title: string, from: int, to: int, band: non-empty-string, header: non-empty-string}>
     */
    private const GROUPS = [
        ['title' => 'P R O G R A M', 'from' => 0, 'to' => 6, 'band' => 'FFFF00', 'header' => 'FFFFCC'],
        ['title' => 'K O N T R A K', 'from' => 7, 'to' => 17, 'band' => 'A9D08E', 'header' => 'E2EFDA'],
        ['title' => 'F I S I K  /  B A', 'from' => 18, 'to' => 18, 'band' => '9BC2E6', 'header' => 'DDEBF7'],
        ['title' => 'P E M B A Y A R A N', 'from' => 19, 'to' => 23, 'band' => 'F4B084', 'header' => 'FCE4D6'],
        ['title' => 'BIDANG', 'from' => 24, 'to' => 24, 'band' => 'D9D9D9', 'header' => 'F2F2F2'],
    ];

    /**
     * Each column's header and width.
     *
     * @var list<array{string, int}>
     */
    private const COLUMNS = [
        ['NO', 5],
        ['DISTRIK', 9],
        ['PRK INDUK', 14],
        ['DESKRIPSI PRK INDUK', 32],
        ['DESKRIPSI SUB PROGRAM', 28],
        ["RENCANA AI /\nNILAI RAB\n(total PRK ≤ Penetapan AI)", 18],
        ["RENCANA AKI\n\n(total PRK ≤ Penetapan AKI)", 18],
        ['NO WO', 12],
        ['NO PR', 12],
        ["Isikan keterangan PO\nStock / Non Stock", 13],
        ["NO PO Ellipse /\nSurat Penunjukan /\nDO /\nSurkes UMRO", 20],
        ['Alokasi PJB2/ Unit', 11],
        ["Item PO\nEllipse", 9],
        ['Supplier', 24],
        ["Tanggal PO Ellipse /\nSurat Penunjukan /\nDO /\nSurkes UMRO", 15],
        ["Nilai PO Ellipse /\nSurat Penunjukan /\nDO /\nSurkes UMRO", 18],
        ['Tanggal Levering', 13],
        ['Keterangan Progres Kontrak', 36],
        ['Isikan Keterangan Progres Fisik dan/atau Progres BA', 36],
        ['NILAI INVOICE', 18],
        ['NOMOR INVOICE', 22],
        ['TANGGAL BAYAR', 13],
        ['NOMOR BUKTI BAYAR', 14],
        ['Keterangan Progres Bayar', 18],
        ["BIDANG\n(Direksi Pekerjaan)", 22],
    ];

    /** Rows above the data: the note, the group band and the headers. */
    private const HEADER_ROWS = 3;

    /**
     * Write the plans to an .xlsx file at the given path.
     *
     * @param  Collection<int, BudgetPlan>  $plans
     */
    public function write(Collection $plans, string $path): void
    {
        $options = new Options;

        foreach (self::COLUMNS as $index => [, $width]) {
            $options->setColumnWidth($width, $index + 1);
        }

        foreach (self::GROUPS as $group) {
            if ($group['from'] !== $group['to']) {
                $options->mergeCells($group['from'], 2, $group['to'], 2);
            }
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('UPKD');
        $writer->getCurrentSheet()->setSheetView(
            (new SheetView)->withFreezeRow(self::HEADER_ROWS + 1)->withFreezeColumn('D'),
        );

        // The note above the program amounts, as on the sheet.
        $writer->addRow(new Row([
            5 => Cell::fromValue('*) dalam Rupiah penuh', new Style(fontBold: true, fontSize: 9, fontColor: '0000FF')),
        ]));

        $writer->addRow($this->groupRow());
        $writer->addRow($this->headerRow());

        foreach ($plans->values() as $index => $plan) {
            $writer->addRow($this->planRow($index + 1, $plan));
        }

        $writer->addRow($this->totalRow($plans));
        $writer->close();
    }

    /**
     * The coloured band naming each group.
     */
    private function groupRow(): Row
    {
        $cells = [];

        foreach (self::GROUPS as $group) {
            for ($column = $group['from']; $column <= $group['to']; $column++) {
                $cells[$column] = Cell::fromValue(
                    $column === $group['from'] ? $group['title'] : null,
                    new Style(
                        fontBold: true,
                        fontSize: 12,
                        cellAlignment: CellAlignment::CENTER,
                        cellVerticalAlignment: CellVerticalAlignment::CENTER,
                        border: $this->border(),
                        backgroundColor: $group['band'],
                    ),
                );
            }
        }

        return new Row($cells, 20);
    }

    /**
     * The column headers, in their group's colour.
     */
    private function headerRow(): Row
    {
        $cells = [];

        foreach (self::COLUMNS as $index => [$title]) {
            $cells[] = Cell::fromValue($title, new Style(
                fontSize: 9,
                cellAlignment: CellAlignment::CENTER,
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
                shouldWrapText: true,
                border: $this->border(),
                backgroundColor: $this->groupOf($index)['header'],
            ));
        }

        return new Row($cells, 64);
    }

    /**
     * One PRK.
     */
    private function planRow(int $number, BudgetPlan $plan): Row
    {
        $values = [
            $number,
            $plan->district,
            $plan->prk_induk,
            $plan->prk_induk_description,
            $plan->sub_program_description,
            $this->amount($plan->ai_plan_value),
            $this->amount($plan->aki_plan_value),
            $plan->wo_number,
            $plan->pr_number,
            $plan->po_stock_type,
            $plan->po_number,
            $plan->allocation,
            $plan->po_item,
            $plan->supplier,
            $plan->po_date?->format('d/m/Y'),
            $this->amount($plan->po_value),
            $plan->delivery_date?->format('d/m/Y'),
            $plan->contract_progress,
            $plan->physical_progress,
            $this->amount($plan->invoice_value),
            $plan->invoice_number,
            $plan->payment_dates,
            $plan->payment_proof_number,
            $plan->payment_progress,
            $plan->workDirector?->name,
        ];

        $cells = [];

        foreach ($values as $index => $value) {
            $isAmount = is_float($value);

            $cells[] = Cell::fromValue($value, new Style(
                fontSize: 9,
                cellAlignment: $isAmount ? CellAlignment::RIGHT : (in_array($index, [0, 1, 11, 12, 14, 16, 21], true) ? CellAlignment::CENTER : CellAlignment::LEFT),
                cellVerticalAlignment: CellVerticalAlignment::TOP,
                shouldWrapText: true,
                border: $this->border(),
                format: $isAmount ? '#,##0' : null,
            ));
        }

        return new Row($cells);
    }

    /**
     * The totals of the amounts.
     *
     * @param  Collection<int, BudgetPlan>  $plans
     */
    private function totalRow(Collection $plans): Row
    {
        $sum = fn (string $field): float => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->getAttribute($field));
        $totals = [5 => $sum('ai_plan_value'), 6 => $sum('aki_plan_value'), 15 => $sum('po_value'), 19 => $sum('invoice_value')];
        $cells = [];

        foreach (array_keys(self::COLUMNS) as $index) {
            $cells[] = Cell::fromValue($index === 0 ? 'TOTAL' : ($totals[$index] ?? null), new Style(
                fontBold: true,
                fontSize: 9,
                cellAlignment: isset($totals[$index]) ? CellAlignment::RIGHT : CellAlignment::LEFT,
                border: $this->border(),
                backgroundColor: 'F2F2F2',
                format: isset($totals[$index]) ? '#,##0' : null,
            ));
        }

        return new Row($cells);
    }

    /**
     * The group a column belongs to.
     *
     * @return array{title: string, from: int, to: int, band: non-empty-string, header: non-empty-string}
     */
    private function groupOf(int $column): array
    {
        foreach (self::GROUPS as $group) {
            if ($column >= $group['from'] && $column <= $group['to']) {
                return $group;
            }
        }

        return self::GROUPS[0];
    }

    /**
     * An amount as a number, or empty.
     */
    private function amount(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * A thin border all round.
     */
    private function border(): Border
    {
        return new Border(
            new BorderPart(BorderName::LEFT, '808080', BorderWidth::THIN),
            new BorderPart(BorderName::RIGHT, '808080', BorderWidth::THIN),
            new BorderPart(BorderName::TOP, '808080', BorderWidth::THIN),
            new BorderPart(BorderName::BOTTOM, '808080', BorderWidth::THIN),
        );
    }
}
