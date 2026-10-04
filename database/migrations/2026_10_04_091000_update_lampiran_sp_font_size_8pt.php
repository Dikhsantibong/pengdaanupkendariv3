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
        // 1. Re-seed Lampiran SP templates with 8pt font size
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

        // 2. Re-render existing generated documents for Lampiran SP with 8pt
        $lampiranTypeIds = DocumentType::query()
            ->whereIn('code', ['surat-pesanan-barang', 'surat-pesanan-jasa', 'lampiran-sp-barang', 'lampiran-sp-jasa'])
            ->pluck('id');

        $generator = app(\App\Services\DocumentGenerator::class);

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

                if ($template && $doc->procurement) {
                    $doc->rendered_body = $generator->render($template, $doc->procurement);
                    $doc->document_template_id = $template->id;
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
