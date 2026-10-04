<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementDocument;
use App\Services\DocumentGenerator;
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
        // 1. Re-seed the Lampiran SP templates with centered repeating logo and 10pt font
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

        // 2. Clean up existing generated documents for Lampiran SP
        $lampiranTypeIds = DocumentType::query()
            ->whereIn('code', ['surat-pesanan-barang', 'surat-pesanan-jasa', 'lampiran-sp-barang', 'lampiran-sp-jasa'])
            ->pluck('id');

        $generator = app(DocumentGenerator::class);

        if ($lampiranTypeIds->isNotEmpty()) {
            $existingDocs = ProcurementDocument::query()
                ->whereIn('document_type_id', $lampiranTypeIds)
                ->with(['documentTemplate', 'procurement'])
                ->get();

            foreach ($existingDocs as $doc) {
                $template = $doc->documentTemplate ?? DocumentTemplate::query()
                    ->where('document_type_id', $doc->document_type_id)
                    ->where('is_active', true)
                    ->first();

                // If template and procurement exist, re-render cleanly to restore all text
                if ($template && $doc->procurement) {
                    $doc->rendered_body = $generator->render($template, $doc->procurement);
                    $doc->document_template_id = $template->id;
                    $doc->save();

                    continue;
                }

                // Fallback: strip inline footer table after section H and clean up
                $body = $doc->rendered_body;
                $cleaned = preg_replace('/(<div[^>]*>\s*<div[^>]*>\s*H\.\s*LAIN-LAIN[\s\S]*?<\/div>\s*<\/div>)\s*<table[^>]*>[\s\S]*?<\/table>/is', '$1', $body);
                if ($cleaned !== $body) {
                    $doc->rendered_body = $cleaned;
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
        } catch (Throwable) {
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
