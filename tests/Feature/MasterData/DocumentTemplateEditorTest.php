<?php

namespace Tests\Feature\MasterData;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentTemplateEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_opens_as_its_own_page_for_a_new_template(): void
    {
        $administrator = User::factory()->administrator()->create();
        $documentType = DocumentType::factory()->create();

        $this->actingAs($administrator)
            ->get(route('master-data.document-templates.create', ['document_type_id' => $documentType->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/document-template-editor')
                ->where('template', null)
                ->where('initialDocumentTypeId', $documentType->id)
                ->has('placeholderCatalog')
                ->where('documentStylesheet', fn (string $css): bool => str_contains($css, '.signature')));
    }

    public function test_the_editor_loads_an_existing_template(): void
    {
        $administrator = User::factory()->administrator()->create();
        $template = DocumentTemplate::factory()->create(['body' => '<p>{{nama_pengadaan}}</p>']);

        $this->actingAs($administrator)
            ->get(route('master-data.document-templates.edit', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/document-template-editor')
                ->where('template.id', $template->id)
                ->where('template.body', '<p>{{nama_pengadaan}}</p>'));
    }

    public function test_a_new_template_continues_in_its_editor_after_saving(): void
    {
        $administrator = User::factory()->administrator()->create();
        $documentType = DocumentType::factory()->create();

        $response = $this->actingAs($administrator)
            ->post(route('master-data.document-templates.store'), [
                'document_type_id' => $documentType->id,
                'name' => 'Template Word',
                'body' => '<h1 style="text-align: center;">Judul</h1><p>{{nomor_pengadaan}}</p>',
                'is_active' => true,
            ]);

        $template = DocumentTemplate::query()->where('name', 'Template Word')->firstOrFail();

        $response->assertRedirect(route('master-data.document-templates.edit', $template));
        $this->assertSame(['nomor_pengadaan'], $template->placeholders);
    }

    public function test_the_editor_is_limited_to_master_data_managers(): void
    {
        $user = User::factory()->create();
        $template = DocumentTemplate::factory()->create();

        $this->actingAs($user)
            ->get(route('master-data.document-templates.edit', $template))
            ->assertForbidden();
    }
}
