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
        // 1. Re-seed the Lampiran SP (Barang & Jasa) templates
        $spplSeeder = new class extends SpplDocumentTemplateSeeder
        {
            public function updateLampiranTemplates(): void
            {
                $templates = [
                    'surat-pesanan-barang' => ['Lampiran SP Barang', $this->syaratUmum('BARANG')],
                    'surat-pesanan-jasa' => ['Lampiran SP Jasa', $this->syaratUmum('JASA')],
                    'lampiran-sp-barang' => ['Lampiran SP Barang', $this->syaratUmum('BARANG')],
                    'lampiran-sp-jasa' => ['Lampiran SP Jasa', $this->syaratUmum('JASA')],
                ];

                foreach ($templates as $code => [$name, $body]) {
                    $type = DocumentType::query()->where('code', $code)->first();
                    if ($type === null) {
                        continue;
                    }

                    DocumentTemplate::query()->updateOrCreate(
                        [
                            'document_type_id' => $type->id,
                            'procurement_method_id' => null,
                        ],
                        [
                            'version' => 1,
                            'name' => $name.' - Template Standar UP Kendari',
                            'body' => $body,
                            'placeholders' => $this->placeholdersIn($body),
                            'is_active' => true,
                        ],
                    );
                }
            }
        };
        $spplSeeder->updateLampiranTemplates();

        // 2. Update existing generated documents for Lampiran SP (Barang & Jasa)
        $lampiranTypeIds = DocumentType::query()
            ->whereIn('code', ['surat-pesanan-barang', 'surat-pesanan-jasa', 'lampiran-sp-barang', 'lampiran-sp-jasa'])
            ->pluck('id');

        if ($lampiranTypeIds->isNotEmpty()) {
            $existingDocs = ProcurementDocument::query()
                ->whereIn('document_type_id', $lampiranTypeIds)
                ->with('procurement')
                ->get();

            $logoTable = <<<'HTML'
<table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 6px;">
                <tr>
                    <td style="border: none; padding: 0; vertical-align: top;">
                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 38px; width: auto; display: block;">
                    </td>
                </tr>
            </table>
HTML;

            foreach ($existingDocs as $doc) {
                $body = $doc->rendered_body;
                $originalBody = $body;

                // 2a. Replace centered logo div or add logo table if missing
                if (preg_match('/<div[^>]*text-align:\s*center[^>]*>\s*<img[^>]*sidebar-logo\.png[^>]*>\s*<\/div>/is', $body)) {
                    $body = preg_replace('/<div[^>]*text-align:\s*center[^>]*>\s*<img[^>]*sidebar-logo\.png[^>]*>\s*<\/div>/is', $logoTable, $body, 1);
                } elseif (! str_contains($body, 'sidebar-logo.png')) {
                    $body = preg_replace('/(<section[^>]*>)/i', '$1'."\n            ".$logoTable, $body, 1);
                }

                // 2b. Add / Update footer table with No. Kontrak and paraf
                $nomorKontrak = ! empty(trim((string) $doc->procurement?->number))
                    ? trim((string) $doc->procurement->number)
                    : '{{nomor_pengadaan}}';

                $footerTable = <<<HTML

            <table style="width: 100%; border: none; border-collapse: collapse; margin-top: 24px; font-size: 8pt; page-break-inside: avoid;">
                <tr>
                    <td style="width: 40%; border: none; padding: 4px 0; vertical-align: bottom; font-weight: bold;">
                        NO. KONTRAK : {$nomorKontrak}
                    </td>
                    <td style="width: 60%; border: none; padding: 4px 0; text-align: right; vertical-align: bottom; font-weight: bold; white-space: nowrap;">
                        <span>PIHAK PERTAMA : ....................</span>
                        <span style="display: inline-block; width: 18px;"></span>
                        <span>PIHAK KEDUA : ....................</span>
                    </td>
                </tr>
            </table>
HTML;

                // Remove existing footer if already present to avoid duplicate
                if (str_contains($body, 'PIHAK PERTAMA :') && str_contains($body, 'PIHAK KEDUA :')) {
                    $body = preg_replace('/<table[^>]*>\s*<tr>\s*<td[^>]*>[\s\S]*?NO\.\s*KONTRAK[\s\S]*?PIHAK\s*KEDUA[\s\S]*?<\/table>/is', '', $body);
                }

                // Insert footer before </section>
                if (str_contains($body, '</section>')) {
                    $body = preg_replace('/(<\/section>)/i', $footerTable."\n        $1", $body, 1);
                } else {
                    $body .= $footerTable;
                }

                if ($body !== $originalBody) {
                    $doc->rendered_body = $body;
                    $doc->save();
                }
            }
        }

        // 3. Clear cached PDF files so refreshed documents render with the new layout
        try {
            $disk = Storage::disk('local');
            if ($disk->exists('documents/pdf')) {
                foreach ($disk->files('documents/pdf') as $file) {
                    $disk->delete($file);
                }
            }
        } catch (\Throwable) {
            // Ignore cache clearing failure
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
