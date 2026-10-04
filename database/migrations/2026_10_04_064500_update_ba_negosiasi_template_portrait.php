<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementDocument;
use Database\Seeders\BeritaAcaraTemplateSeeder;
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
        // 1. Re-seed the BA negotiation templates with portrait formatting
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

        $baSeeder = new class extends BeritaAcaraTemplateSeeder
        {
            public function runTemplate(): void
            {
                $type = DocumentType::query()->where('code', 'ba-klarifikasi')->first();
                if ($type !== null) {
                    $body = $this->klarifikasi();
                    DocumentTemplate::query()->updateOrCreate(
                        [
                            'document_type_id' => $type->id,
                            'procurement_method_id' => null,
                        ],
                        [
                            'version' => 1,
                            'name' => 'Berita Acara Klarifikasi dan Negosiasi - UP Kendari',
                            'body' => $body,
                            'placeholders' => $this->placeholdersIn($body),
                            'is_active' => true,
                        ],
                    );
                }
            }
        };
        $baSeeder->runTemplate();

        // 2. Update existing generated documents for ba-negosiasi-sppl to portrait table layout
        $spplType = DocumentType::query()->where('code', 'ba-negosiasi-sppl')->first();
        if ($spplType !== null) {
            $existingDocs = ProcurementDocument::query()
                ->where('document_type_id', $spplType->id)
                ->get();

            foreach ($existingDocs as $doc) {
                $body = $doc->rendered_body;
                // Add @page portrait style if not present
                if (! str_contains($body, '@page')) {
                    $body = preg_replace(
                        '/<section([^>]*)>/',
                        '<section$1><style>@page { size: A4 portrait; margin: 18mm 15mm; }</style>',
                        $body,
                        1,
                    );
                }
                // Ensure negotiation table has table-layout: fixed and max-width: 100%
                $body = str_replace(
                    '<table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 9pt; border: 1px solid #000;">',
                    '<table style="width: 100%; max-width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 20px; font-size: 8pt; border: 1px solid #000; word-break: break-word;">',
                    $body,
                );
                // Adjust cell widths
                $body = str_replace('width: 4%', 'width: 5%', $body);
                $body = str_replace('width: 8%', 'width: 6%', $body);
                $body = str_replace('width: 26%', 'width: 28%', $body);
                $body = str_replace('width: 13%', 'width: 14%', $body);

                $doc->update(['rendered_body' => $body]);
            }
        }

        // 3. Clear cached PDFs so fresh portrait versions are generated
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
