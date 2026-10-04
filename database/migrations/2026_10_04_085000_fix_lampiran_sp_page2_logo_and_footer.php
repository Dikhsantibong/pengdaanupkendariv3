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
        // 1. Re-seed Lampiran SP templates with page 2 logo and clean body
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

        // 2. Clean up existing generated documents for Lampiran SP (remove inline body paraf table and add page 2 logo)
        $lampiranTypeIds = DocumentType::query()
            ->whereIn('code', ['surat-pesanan-barang', 'surat-pesanan-jasa', 'lampiran-sp-barang', 'lampiran-sp-jasa'])
            ->pluck('id');

        if ($lampiranTypeIds->isNotEmpty()) {
            $existingDocs = ProcurementDocument::query()
                ->whereIn('document_type_id', $lampiranTypeIds)
                ->get();

            $page2LogoBreak = <<<'HTML'
<div style="page-break-before: always; margin-top: 0;">
                <table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 6px;">
                    <tr>
                        <td style="border: none; padding: 0; vertical-align: top;">
                            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 38px; width: auto; display: block;">
                        </td>
                    </tr>
                </table>
            </div>
HTML;

            foreach ($existingDocs as $doc) {
                $body = $doc->rendered_body;
                $originalBody = $body;

                // 2a. Remove body paraf table that was placed after section H
                $body = preg_replace('/<table[^>]*>\s*<tr>\s*<td[^>]*>[\s\S]*?NO\.\s*KONTRAK[\s\S]*?PIHAK\s*KEDUA[\s\S]*?<\/table>/is', '', $body);

                // 2b. Add page 2 logo header before section E if not already present
                if (! str_contains($body, 'page-break-before: always;')) {
                    $sectionEPattern = '/(<div[^>]*>\s*<div[^>]*>\s*E\.\s*PEMUTUSAN)/i';
                    if (preg_match($sectionEPattern, $body)) {
                        $body = preg_replace($sectionEPattern, $page2LogoBreak."\n            $1", $body, 1);
                    }
                }

                if ($body !== $originalBody) {
                    $doc->rendered_body = $body;
                    $doc->save();
                }
            }
        }

        // 3. Clear cached PDF files
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
