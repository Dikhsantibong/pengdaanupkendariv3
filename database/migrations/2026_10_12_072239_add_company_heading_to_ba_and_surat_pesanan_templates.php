<?php

use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put the company name and address at the top right of the SPPL BA
 * Negosiasi and Surat Pesanan letterheads. The attachments (Lampiran SP)
 * keep their centred logo. Documents already generated are left as issued.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $heading = SpplDocumentTemplateSeeder::COMPANY_HEADING;

        foreach ($this->templates(['surat-pesanan', 'purchase-order']) as $template) {
            $body = preg_replace(
                '#<table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 6px;">\s*<tr>\s*'
                .'<td style="border: none; padding: 0; vertical-align: top;">\s*(<img src="/logo/sidebar-logo.png"[^>]*>)\s*</td>\s*</tr>#',
                '<table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 10px;">'."\n"
                ."                <tr>\n"
                .'                    <td style="width: 35%; border: none; padding: 0; vertical-align: middle;">'."\n"
                ."                        $1\n"
                ."                    </td>\n"
                .'                    <td style="width: 65%; border: none; padding: 0; vertical-align: middle; text-align: right;">'."\n"
                ."                        {$heading}\n"
                ."                    </td>\n"
                .'                </tr>',
                $template->body,
                1,
            ) ?? $template->body;

            $this->save($template->id, $template->body, $body);
        }

        foreach ($this->templates(['ba-negosiasi-sppl']) as $template) {
            $rightCell = '<td style="width: 50%; border: none; padding: 0; vertical-align: top; text-align: right;">';
            $position = strpos($template->body, $rightCell);

            if ($position === false) {
                continue;
            }

            $position += strlen($rightCell);
            $body = substr($template->body, 0, $position)
                ."\n                        {$heading}"
                .substr($template->body, $position);
            $body = preg_replace(
                '#(\{\{nama_mitra\}\}|'.preg_quote($heading, '#').')(\s*)<table style="display: inline-table;#',
                '$1$2<table style="display: inline-table; margin-top: 6px;',
                $body,
                1,
            ) ?? $body;

            $this->save($template->id, $template->body, $body);
        }
    }

    /**
     * The live general templates of the given document types still without the heading.
     *
     * @param  array<int, string>  $codes
     * @return array<int, object{id: int, body: string}>
     */
    private function templates(array $codes): array
    {
        return DB::table('document_templates')
            ->join('document_types', 'document_types.id', '=', 'document_templates.document_type_id')
            ->whereIn('document_types.code', $codes)
            ->whereNull('document_templates.deleted_at')
            ->where('document_templates.body', 'not like', '%Jl. Chairil Anwar No.2%')
            ->select('document_templates.id', 'document_templates.body')
            ->get()
            ->all();
    }

    /**
     * Store a changed body.
     */
    private function save(int $id, string $original, string $body): void
    {
        if ($body === $original) {
            return;
        }

        DB::table('document_templates')->where('id', $id)->update([
            'body' => $body,
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
