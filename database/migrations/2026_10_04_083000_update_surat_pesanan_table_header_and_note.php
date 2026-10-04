<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementDocument;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Re-seed the Surat Pesanan / Purchase Order templates with clean headers and NOTE row
        $spplSeeder = new class extends SpplDocumentTemplateSeeder
        {
            public function runTemplate(): void
            {
                $poTypes = DocumentType::query()->whereIn('code', ['purchase-order', 'surat-pesanan'])->get();
                foreach ($poTypes as $poType) {
                    $poBody = $this->suratPesanan();
                    DocumentTemplate::query()->updateOrCreate(
                        [
                            'document_type_id' => $poType->id,
                            'procurement_method_id' => null,
                        ],
                        [
                            'version' => 1,
                            'name' => 'Surat Pesanan - Template Standar UP Kendari',
                            'body' => $poBody,
                            'placeholders' => $this->placeholdersIn($poBody),
                            'is_active' => true,
                        ],
                    );
                }
            }
        };
        $spplSeeder->runTemplate();

        // 2. Update existing generated documents for purchase-order and surat-pesanan
        $poTypeIds = DocumentType::query()->whereIn('code', ['purchase-order', 'surat-pesanan'])->pluck('id');

        if ($poTypeIds->isNotEmpty()) {
            $existingDocs = ProcurementDocument::query()
                ->whereIn('document_type_id', $poTypeIds)
                ->with('procurement')
                ->get();

            foreach ($existingDocs as $doc) {
                $body = $doc->rendered_body;
                $originalBody = $body;

                // 2a. Update table thead to ensure headers are tidy without word breaks or cut letters
                $headerPattern = '/<thead>.*?<\/thead>/is';
                $colItemHeader = str_contains($body, 'URAIAN PEKERJAAN JASA')
                    ? 'URAIAN PEKERJAAN JASA /<br>SPESIFIKASI'
                    : 'NAMA BARANG SPESIFIKASI/<br>PART NUMBER';
                $colDeadlineHeader = str_contains($body, 'PENYELESAIAN')
                    ? 'BATAS WAKTU /<br>PENYELESAIAN<br>JASA'
                    : 'BATAS WAKTU /<br>PENYERAHAN<br>BARANG / JASA';

                $cleanThead = <<<HTML
<thead>
                    <tr style="text-align: center; font-weight: bold; background-color: #ffffff;">
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 6%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">NOMOR</th>
                        <th style="border: 1px solid #000; padding: 6px 6px; width: 33%; vertical-align: middle;">{$colItemHeader}</th>
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 8%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">VOLUME</th>
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 8%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 14%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">HARGA SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 15%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">JUMLAH HARGA</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 16%; vertical-align: middle; font-size: 7.5pt; line-height: 1.25;">{$colDeadlineHeader}</th>
                    </tr>
                </thead>
HTML;

                if (preg_match($headerPattern, $body)) {
                    $body = preg_replace($headerPattern, $cleanThead, $body);
                }

                // 2b. Add NOTE row with PRK number if not present
                if (! str_contains($body, 'NOTE :') && ! str_contains($body, 'NOTE:')) {
                    $prkDisplay = ! empty(trim((string) $doc->procurement?->prk_number))
                        ? trim((string) $doc->procurement->prk_number)
                        : '-';

                    $noteRow = <<<HTML

                    <tr>
                        <td colspan="7" style="border: 1px solid #000; padding: 6px 8px; font-size: 8.5pt;">
                            <b>NOTE :</b> {$prkDisplay}
                        </td>
                    </tr>
HTML;

                    $perhatianPattern = '/(<tr>\s*<td[^>]*colspan="4"[^>]*>[\s\S]*?<b>PERHATIAN\s*:<\/b>)/i';
                    if (preg_match($perhatianPattern, $body)) {
                        $body = preg_replace($perhatianPattern, $noteRow.'$1', $body, 1);
                    }
                }

                if ($body !== $originalBody) {
                    $doc->rendered_body = $body;
                    $doc->save();
                }

                // 2c. Clear cached PDF
                $prefix = 'documents/pdf/'.$doc->id.'-';
                foreach (Storage::disk('local')->files('documents/pdf') as $file) {
                    if (str_starts_with($file, $prefix)) {
                        Storage::disk('local')->delete($file);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive rollback
    }
};
