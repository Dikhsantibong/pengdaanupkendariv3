<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentTemplate;
use Database\Seeders\BeritaAcaraTemplateSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RksSpkTemplateSeeder;
use Database\Seeders\RksTenderTemplateSeeder;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Database\Seeders\StandardDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_active_document_templates_use_sidebar_logo(): void
    {
        $this->seed([
            MasterDataSeeder::class,
            DocumentTemplateSeeder::class,
            RksSpkTemplateSeeder::class,
            RksTenderTemplateSeeder::class,
            BeritaAcaraTemplateSeeder::class,
            KontrakTemplateSeeder::class,
            StandardDocumentTemplateSeeder::class,
            SpplDocumentTemplateSeeder::class,
        ]);

        $templates = DocumentTemplate::query()->where('is_active', true)->get();

        $this->assertNotEmpty($templates, 'No active templates found.');

        foreach ($templates as $template) {
            $this->assertStringContainsString(
                '/logo/sidebar-logo.png',
                $template->body,
                "Template [{$template->name}] (ID: {$template->id}) does not contain '/logo/sidebar-logo.png'.",
            );
        }
    }
}
