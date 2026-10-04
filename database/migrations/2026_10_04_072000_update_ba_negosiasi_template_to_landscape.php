<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementDocument;
use Database\Seeders\KontrakTemplateSeeder;
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
        // 1. Re-seed the BA negotiation templates with landscape formatting & Surat Pesanan with alamat_mitra
        $spplSeeder = new class extends SpplDocumentTemplateSeeder
        {
            public function runTemplate(): void
            {
                $type = DocumentType::query()->where('code', 'ba-negosiasi-sppl')->first();
                if ($type !== null) {
                    $body = $this->baNegosiasi();
                    DocumentTemplate::query()->updateOrCreate(
                        [
                            'document_type_id' => $type->id,
                            'procurement_method_id' => null,
                        ],
                        [
                            'version' => 1,
                            'name' => 'Berita Acara Negosiasi (SPPL) - Template Standar UP Kendari',
                            'body' => $body,
                            'placeholders' => $this->placeholdersIn($body),
                            'is_active' => true,
                        ],
                    );
                }

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

        $kontrakSeeder = new class extends KontrakTemplateSeeder
        {
            public function runTemplate(): void
            {
                $type = DocumentType::query()->where('code', 'ba-negosiasi')->first();
                if ($type !== null) {
                    $body = $this->beritaAcaraNegosiasi();
                    DocumentTemplate::query()->updateOrCreate(
                        [
                            'document_type_id' => $type->id,
                            'procurement_method_id' => null,
                        ],
                        [
                            'version' => 1,
                            'name' => 'Berita Acara Negosiasi dan Lampiran - Template Standar UP Kendari',
                            'body' => $body,
                            'placeholders' => $this->placeholdersIn($body),
                            'is_active' => true,
                        ],
                    );
                }
            }
        };
        $kontrakSeeder->runTemplate();

        // 2. Update existing generated documents for ba-negosiasi-sppl and ba-negosiasi to landscape layout
        $codes = ['ba-negosiasi-sppl', 'ba-negosiasi'];
        $typeIds = DocumentType::query()->whereIn('code', $codes)->pluck('id');

        if ($typeIds->isNotEmpty()) {
            $existingDocs = ProcurementDocument::query()
                ->whereIn('document_type_id', $typeIds)
                ->get();

            foreach ($existingDocs as $doc) {
                $body = $doc->rendered_body;

                // Replace portrait page size with landscape
                if (str_contains($body, 'size: A4 portrait')) {
                    $body = preg_replace(
                        '/@page\s*\{\s*size:\s*A4\s+portrait[^}]*\}/i',
                        '@page { size: A4 landscape; margin: 15mm 20mm; }',
                        $body,
                    );
                } elseif (! str_contains(strtolower($body), 'landscape')) {
                    $body = preg_replace(
                        '/<section([^>]*)>/i',
                        '<section$1><style>@page { size: A4 landscape; margin: 15mm 20mm; }</style>',
                        $body,
                        1,
                    );
                }

                $doc->update(['rendered_body' => $body]);
            }
        }

        // 3. Update existing Surat Pesanan documents to reflect partner_address if set
        $poIds = DocumentType::query()->whereIn('code', ['purchase-order', 'surat-pesanan'])->pluck('id');
        if ($poIds->isNotEmpty()) {
            $poDocs = ProcurementDocument::query()
                ->whereIn('document_type_id', $poIds)
                ->with('procurement')
                ->get();

            foreach ($poDocs as $doc) {
                $address = trim((string) $doc->procurement?->partner_address);
                if ($address === '') {
                    continue;
                }

                $body = $doc->rendered_body;
                if (str_contains($body, 'DI TEMPAT')) {
                    $body = preg_replace(
                        '/(<div style="font-weight:\s*bold;">KEPADA<\/div>\s*<div[^>]*>.*?<\/div>\s*<div[^>]*>)DI TEMPAT(<\/div>)/is',
                        '$1'.e($address).'$2',
                        $body,
                    );
                }
                if (str_contains($body, '{{alamat_mitra}}') || str_contains($body, '{{alamat_perusahaan}}')) {
                    $body = str_replace(
                        ['{{alamat_mitra}}', '{{alamat_perusahaan}}'],
                        e($address),
                        $body,
                    );
                }

                if ($body !== $doc->rendered_body) {
                    $doc->update(['rendered_body' => $body]);
                }
            }
        }

        // 4. Clear cached PDFs so fresh landscape and updated address versions are generated
        try {
            Storage::disk('local')->deleteDirectory('documents/pdf');
        } catch (Throwable) {
            // Ignore if directory doesn't exist or isn't accessible
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};
