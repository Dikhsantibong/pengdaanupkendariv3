<?php

namespace App\Services;

use App\Models\ProcurementContractItem;
use App\Support\IndonesianNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Draws the item rows of a document's item table from the procurement's
 * Rincian Item, in the very markup and style the template gives them, so
 * the printed document looks as it did when the rows were typed by hand.
 *
 * A group heading prints as its own row (A, B, …, in bold) and its items
 * are numbered from 1 under it; only items count towards the totals.
 * A table whose price column has nothing to show yet is left as it is.
 */
class ItemTableRenderer
{
    /**
     * Document types and the layout of their item table.
     */
    public const LAYOUTS = [
        'ba-negosiasi-sppl' => 'nego',
        'ba-negosiasi' => 'nego',
        'spk' => 'spk',
        'hpe' => 'hpe',
        'penyusunan-hps' => 'standard',
        'penawaran' => 'standard',
        'lampiran-spk' => 'standard',
    ];

    /**
     * The heading that tells each layout's item table apart.
     */
    protected const SIGNATURES = [
        'nego' => 'HARGA SEBELUM NEGO',
        'spk' => 'BATAS WAKTU/ PENYERAHAN',
        'hpe' => 'Harga HPE',
        'standard' => 'Harga Satuan (Rp)',
        'surat-pesanan' => 'PERHATIAN',
    ];

    /**
     * Whether a document body holds the item table of its type: only then
     * does the editor offer the Rincian Item (a contract made only of
     * articles, a Surat Perjanjian say, keeps its editor as it is).
     */
    public function hasTable(string $html, ?string $typeCode): bool
    {
        $layout = in_array($typeCode, ['purchase-order', 'surat-pesanan'], true)
            ? 'surat-pesanan'
            : (self::LAYOUTS[$typeCode] ?? null);

        if ($layout === null || ! preg_match_all('/<table\b[^>]*>(?:(?!<table\b).)*?<\/table>/is', $html, $matches)) {
            return false;
        }

        foreach ($matches[0] as $table) {
            if (stripos($table, self::SIGNATURES[$layout]) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redraw the item table of a document body, if it has one.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     */
    public function apply(string $html, ?string $typeCode, Collection $items): string
    {
        $layout = self::LAYOUTS[$typeCode] ?? null;

        if ($layout === null || $items->isEmpty()) {
            return $html;
        }

        return match ($layout) {
            'nego' => $this->replaceTable($html, self::SIGNATURES['nego'], fn (string $table): string => $this->nego($table, $items)),
            'spk' => $this->replaceTable($html, self::SIGNATURES['spk'], fn (string $table): string => $this->spk($table, $items)),
            'hpe' => $this->replaceTable($html, self::SIGNATURES['hpe'], fn (string $table): string => $this->hpe($table, $items)),
            'standard' => $this->replaceTable($html, self::SIGNATURES['standard'], fn (string $table): string => $this->standard($table, $items, $typeCode === 'lampiran-spk')),
        };
    }

    /**
     * Swap the innermost table holding the given heading for its redrawn
     * version.
     *
     * @param  callable(string): string  $redraw
     */
    protected function replaceTable(string $html, string $signature, callable $redraw): string
    {
        if (! preg_match_all('/<table\b[^>]*>(?:(?!<table\b).)*?<\/table>/is', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        foreach ($matches[0] as [$table, $offset]) {
            if (stripos($table, $signature) === false) {
                continue;
            }

            return substr($html, 0, $offset).$redraw($table).substr($html, $offset + strlen($table));
        }

        return $html;
    }

    /**
     * The BA Negosiasi table: its tbody becomes the items, before and after
     * negotiation, closed by the TOTAL HARGA row.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     */
    protected function nego(string $table, Collection $items): string
    {
        if (! $this->hasPrice($items, 'price_before') && ! $this->hasPrice($items, 'unit_price')) {
            return $table;
        }

        $cell = 'border: 1px solid #000; padding: 5px 2px; text-align: center;';
        $text = 'border: 1px solid #000; padding: 5px 4px;';
        $money = 'border: 1px solid #000; padding: 5px 3px; text-align: right;';
        $rows = '';

        foreach ($this->rows($items) as $row) {
            if ($row['item'] === null) {
                $rows .= '<tr><td style="'.$cell.' font-weight: bold;">'.$row['label'].'</td>'
                    .'<td style="'.$text.' font-weight: bold;">'.e($row['name']).'</td>'
                    .str_repeat('<td style="'.$cell.'"></td>', 2)
                    .str_repeat('<td style="'.$money.'"></td>', 4).'</tr>';

                continue;
            }

            $item = $row['item'];
            $rows .= '<tr><td style="'.$cell.'">'.$row['label'].'</td>'
                .'<td style="'.$text.'">'.e($item->description).'</td>'
                .'<td style="'.$cell.'">'.$this->quantity($item).'</td>'
                .'<td style="'.$cell.'">'.e($item->unit).'</td>'
                .'<td style="'.$money.'">'.$this->money($item->price_before).'</td>'
                .'<td style="'.$money.'">'.$this->money($item->price_before === null ? null : $item->totalBefore()).'</td>'
                .'<td style="'.$money.'">'.$this->money($item->unit_price).'</td>'
                .'<td style="'.$money.'">'.$this->money($item->unit_price === null ? null : $item->totalAfter()).'</td></tr>';
        }

        $total = 'border: 1px solid #000; padding: 4px 4px; text-align: center;';
        $rows .= '<tr><td colspan="4" style="'.$total.'">TOTAL HARGA</td>'
            .'<td colspan="2" style="'.$total.'">'.$this->money($items->sum(fn (ProcurementContractItem $item): float => $item->totalBefore())).'</td>'
            .'<td colspan="2" style="'.$total.'">'.$this->money($items->sum(fn (ProcurementContractItem $item): float => $item->totalAfter())).'</td></tr>';

        if (preg_match('/<tbody\b[^>]*>.*<\/tbody>/is', $table)) {
            return (string) preg_replace('/(<tbody\b[^>]*>).*(<\/tbody>)/is', '$1'.$this->escapeReplacement($rows).'$2', $table, 1);
        }

        return (string) preg_replace('/(<\/thead>).*(<\/table>)/is', '$1<tbody>'.$this->escapeReplacement($rows).'</tbody>$2', $table, 1);
    }

    /**
     * The SPK's goods table: the work's own first row stays (its deadline
     * now spans the redrawn rows), then a lettered row per group and a
     * "- item" row per item, up to the JUMLAH/PPN/TOTAL rows.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     */
    protected function spk(string $table, Collection $items): string
    {
        if (! $this->hasPrice($items, 'unit_price')) {
            return $table;
        }

        $rows = $this->tableRows($table);
        // The heading row (with its own JUMLAH column) and the work's own row
        // come first; the JUMLAH total row follows the items.
        $totalAt = $this->firstRowContaining($rows, '>JUMLAH<', from: 2);

        if ($totalAt === null) {
            return $table;
        }

        $cell = 'border: 1px solid #000; padding: 3px 4px;';
        $drawn = [];

        foreach ($this->rows($items, letters: true) as $row) {
            if ($row['item'] === null) {
                $drawn[] = '<tr><td style="'.$cell.'"></td>'
                    .'<td style="'.$cell.' font-weight: bold;">'.$row['label'].'. '.e($row['name']).'</td>'
                    .str_repeat('<td style="'.$cell.'"></td>', 4).'</tr>';

                continue;
            }

            $item = $row['item'];
            $drawn[] = '<tr><td style="'.$cell.'"></td>'
                .'<td style="'.$cell.'">- '.e($item->description).'</td>'
                .'<td style="'.$cell.' text-align: center;">'.$this->quantity($item).'</td>'
                .'<td style="'.$cell.' text-align: center;">'.e($item->unit).'</td>'
                .'<td style="'.$cell.' text-align: right;">Rp '.$this->money($item->unit_price).'</td>'
                .'<td style="'.$cell.' text-align: right;">Rp '.$this->money($item->unit_price === null ? null : $item->totalAfter()).'</td></tr>';
        }

        // The deadline cell of the work's row spans it and every item row.
        $first = (string) preg_replace('/rowspan="\d+"/', 'rowspan="'.(count($drawn) + 1).'"', $rows[1], 1);

        return $this->rebuild($table, [$rows[0], $first, ...$drawn, ...array_slice($rows, $totalAt)]);
    }

    /**
     * The HPE's table: a row per item at its amount before negotiation,
     * then the Jumlah row.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     */
    protected function hpe(string $table, Collection $items): string
    {
        if (! $this->hasPrice($items, 'price_before')) {
            return $table;
        }

        $rows = $this->tableRows($table);
        $totalAt = $this->firstRowContaining($rows, 'class="total"');

        if ($rows === [] || $totalAt === null) {
            return $table;
        }

        $drawn = [];

        foreach ($this->rows($items) as $row) {
            if ($row['item'] === null) {
                $drawn[] = '<tr><td class="center"><b>'.$row['label'].'</b></td><td></td>'
                    .'<td><b>'.e($row['name']).'</b></td><td></td><td></td><td></td><td></td></tr>';

                continue;
            }

            $item = $row['item'];
            $drawn[] = '<tr><td class="center">'.$row['label'].'</td>'
                .'<td>'.e($item->part_number ?? '').'</td>'
                .'<td>'.e($item->description).'</td>'
                .'<td class="center">'.$this->quantity($item).'</td>'
                .'<td class="center">'.e(Str::upper($item->unit)).'</td>'
                .'<td class="right">'.$this->money($item->totalBefore()).',00</td>'
                .'<td></td></tr>';
        }

        $total = $items->sum(fn (ProcurementContractItem $item): float => $item->totalBefore());
        $drawn[] = '<tr class="total"><td colspan="5" class="right">Jumlah</td>'
            .'<td class="right">'.$this->money($total).',00</td><td></td></tr>';

        return $this->rebuild($table, [$rows[0], ...$drawn, ...array_slice($rows, $totalAt + 1)]);
    }

    /**
     * The six-column table of the HPS, the offer and the SPK appendix: the
     * items, then Sub Total, PPN, the total under its own label, and the
     * amount in words.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     */
    protected function standard(string $table, Collection $items, bool $afterNegotiation): string
    {
        $price = $afterNegotiation ? 'unit_price' : 'price_before';

        if (! $this->hasPrice($items, $price)) {
            return $table;
        }

        $rows = $this->tableRows($table);

        if ($rows === []) {
            return $table;
        }

        $label = preg_match('/<b>\s*(Total[^<]*)<\/b>/i', $table, $match) ? trim($match[1]) : 'Total';
        $amount = fn (ProcurementContractItem $item): float => $afterNegotiation ? $item->totalAfter() : $item->totalBefore();
        $drawn = [];

        foreach ($this->rows($items) as $row) {
            if ($row['item'] === null) {
                $drawn[] = '<tr><td><b>'.$row['label'].'</b></td><td colspan="5"><b>'.e($row['name']).'</b></td></tr>';

                continue;
            }

            $item = $row['item'];
            $drawn[] = '<tr><td>'.$row['label'].'</td>'
                .'<td>'.e($item->description).'</td>'
                .'<td>'.$this->quantity($item).'</td>'
                .'<td>'.e($item->unit).'</td>'
                .'<td>'.$this->money($item->getAttribute($price)).'</td>'
                .'<td>'.$this->money($amount($item)).'</td></tr>';
        }

        $subtotal = $items->sum($amount);
        // As the documents' own PPN figures: 12% of the amount.
        $ppn = round($subtotal * 0.12);
        $total = $subtotal + $ppn;

        $drawn[] = '<tr><td colspan="5"><b>Sub Total</b></td><td>'.$this->money($subtotal).'</td></tr>';
        $drawn[] = '<tr><td colspan="5"><b>PPN</b></td><td>'.$this->money($ppn).'</td></tr>';
        $drawn[] = '<tr><td colspan="5"><b>'.e($label).'</b></td><td>'.$this->money($total).'</td></tr>';
        $drawn[] = '<tr><td colspan="6"><b>Terbilang:</b> '.e(Str::ucfirst(IndonesianNumber::spellRupiah($total))).'</td></tr>';

        return $this->rebuild($table, [$rows[0], ...$drawn]);
    }

    /**
     * The printed rows in order: a heading per group (lettered) and the
     * items under it, numbered from 1 again in every group.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     * @return list<array{label: string, name: string, item: ProcurementContractItem|null}>
     */
    protected function rows(Collection $items, bool $letters = true): array
    {
        $rows = [];
        $group = null;
        $letter = 'A';
        $number = 0;

        foreach ($items->sortBy('sort_order')->values() as $item) {
            $name = filled($item->group_name) ? trim((string) $item->group_name) : null;

            if ($name !== null && $name !== $group) {
                $rows[] = ['label' => $letters ? (string) $letter++ : '', 'name' => $name, 'item' => null];
                $number = 0;
            }

            $group = $name;
            $rows[] = ['label' => (string) ++$number, 'name' => '', 'item' => $item];
        }

        return $rows;
    }

    /**
     * The table's rows, as written.
     *
     * @return list<string>
     */
    protected function tableRows(string $table): array
    {
        preg_match_all('/<tr\b[^>]*>.*?<\/tr>/is', $table, $matches);

        return $matches[0];
    }

    /**
     * @param  list<string>  $rows
     */
    protected function firstRowContaining(array $rows, string $needle, int $from = 0): ?int
    {
        foreach ($rows as $index => $row) {
            if ($index >= $from && stripos($row, $needle) !== false) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The table with its rows replaced, its opening tag kept.
     *
     * @param  list<string>  $rows
     */
    protected function rebuild(string $table, array $rows): string
    {
        preg_match('/^<table\b[^>]*>/i', $table, $open);

        return ($open[0] ?? '<table>')."\n        ".implode("\n        ", $rows)."\n    </table>";
    }

    /**
     * Whether any item has the given price.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     */
    protected function hasPrice(Collection $items, string $price): bool
    {
        return $items->contains(fn (ProcurementContractItem $item): bool => $item->getAttribute($price) !== null);
    }

    /**
     * A volume without trailing zeros: 2, 2,5, 0,125.
     */
    protected function quantity(ProcurementContractItem $item): string
    {
        return rtrim(rtrim(number_format((float) $item->volume, 3, ',', '.'), '0'), ',');
    }

    /**
     * An amount as the documents print it (25.000), blank when unknown.
     */
    protected function money(float|int|string|null $value): string
    {
        return $value === null ? '' : number_format((float) $value, 0, ',', '.');
    }

    /**
     * Rows put through preg_replace keep their "$" and "\" literal.
     */
    protected function escapeReplacement(string $value): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
    }
}
