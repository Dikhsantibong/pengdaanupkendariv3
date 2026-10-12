<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The SPPL negotiation sheet now shows prices before tax only: the DPP 11/12,
 * PPN 12% and JUMLAH TOTAL rows go, and a "*Harga belum termasuk PPN" note
 * sits under the table. Only the templates change; documents already
 * generated keep the layout they were issued with.
 */
return new class extends Migration
{
    /**
     * The rows removed from the price table.
     */
    private const TAX_ROW = '#\s*<tr>\s*<td[^>]*>\s*(?:DPP 11/12|PPN 12%|JUMLAH TOTAL)\s*</td>.*?</tr>#s';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = DB::table('document_templates')
            ->join('document_types', 'document_types.id', '=', 'document_templates.document_type_id')
            ->where('document_types.code', 'ba-negosiasi-sppl')
            ->whereNull('document_templates.deleted_at')
            ->select('document_templates.id', 'document_templates.body')
            ->get();

        foreach ($templates as $template) {
            $body = (string) $template->body;

            if (! str_contains($body, 'DPP 11/12')) {
                continue;
            }

            $body = preg_replace(self::TAX_ROW, '', $body) ?? $body;

            // The total row reads like the rest of the sheet: plain, centred.
            $body = preg_replace_callback(
                '#<tr>\s*<td[^>]*>\s*TOTAL HARGA\s*</td>.*?</tr>#s',
                fn (array $row): string => str_replace(
                    ['text-align: right; font-weight: bold;', 'text-align: center; font-weight: bold;'],
                    'text-align: center;',
                    $row[0],
                ),
                $body,
            ) ?? $body;

            // The signature block is set in plain type, as on the paper form.
            $body = str_replace(
                [
                    '<b>{{nama_mitra}}</b><br>',
                    '<b>DIREKTUR</b><br>',
                    '<b>{{nama_direktur}}</b>',
                    '<b>PLN NP UP KENDARI</b><br>',
                    '<b>TL PELAKSANA PENGADAAN</b><br>',
                    '<b>{{pic_pelaksana}}</b>',
                ],
                [
                    '{{nama_mitra}}<br>',
                    'DIREKTUR<br>',
                    '{{nama_direktur}}',
                    'PLN NP UP KENDARI<br>',
                    'TL PELAKSANA PENGADAAN<br>',
                    '{{pic_pelaksana}}',
                ],
                $body,
            );

            if (! str_contains($body, 'belum termasuk PPN')) {
                $totalAt = strpos($body, 'TOTAL HARGA');
                $tableEnd = $totalAt === false ? false : strpos($body, '</table>', $totalAt);

                if ($tableEnd !== false) {
                    $tableEnd += strlen('</table>');
                    $body = substr($body, 0, $tableEnd)
                        ."\n\n            <p style=\"margin: -14px 0 0 0; font-size: 8pt;\">*Harga belum termasuk PPN</p>"
                        .substr($body, $tableEnd);
                }
            }

            preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

            DB::table('document_templates')->where('id', $template->id)->update([
                'body' => $body,
                'placeholders' => json_encode(array_values(array_unique(array_map('strtolower', $matches[1])))),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The tax rows are not restored: re-seed the SPPL templates if needed.
     */
    public function down(): void
    {
        //
    }
};
