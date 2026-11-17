<?php

namespace Tests\Feature\MasterData;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Data Master · Dokumen & Template: the document types, their templates and
 * the steps asking for them on one screen; a template opens in the visual
 * editor beside its PDF.
 */
class DocumentsAndTemplatesPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_each_document_shows_its_templates_and_steps(): void
    {
        $type = DocumentType::factory()->create(['name' => 'Penyusunan HPS Uji', 'stage' => ProcurementStage::Pelaksanaan]);
        $template = DocumentTemplate::factory()->create(['document_type_id' => $type->id, 'name' => 'HPS Satu Lembar', 'is_active' => true]);
        $step = ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->create(['name' => 'Penyusunan HPS']);
        $step->documentTypes()->attach($type->id, ['sort_order' => 1, 'is_alternative' => false]);

        $this->actingAs($this->administrator)
            ->get(route('master-data.document-types.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/documents')
                ->where('records', fn ($records) => collect($records)->contains(fn (array $record): bool => $record['id'] === $type->id
                    && $record['templates'][0]['id'] === $template->id
                    && $record['templates'][0]['name'] === 'HPS Satu Lembar'
                    && $record['steps'][0]['name'] === 'Penyusunan HPS'
                    && $record['steps'][0]['stage_label'] === ProcurementStage::Pelaksanaan->label()))
                ->missing('workbench')
                ->has('stages')
                ->has('selectionMethods')
                ->has('contractNumberFormats'));
    }

    public function test_a_template_opens_with_what_the_visual_editor_needs(): void
    {
        $template = DocumentTemplate::factory()->create(['body' => '<p>Halo {{nama_pengadaan}}</p>']);

        $version = $this->actingAs($this->administrator)
            ->get(route('master-data.document-types.index'))
            ->viewData('page')['version'];

        $this->actingAs($this->administrator)
            ->get(route('master-data.document-types.index', ['template' => $template->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'master-data/documents',
                'X-Inertia-Partial-Data' => 'workbench',
            ])
            ->assertOk()
            ->assertJsonPath('props.workbench.template.id', $template->id)
            ->assertJsonPath('props.workbench.template.body', '<p>Halo {{nama_pengadaan}}</p>')
            ->assertJsonPath('props.workbench.template.document_type_id', $template->document_type_id)
            ->assertJsonStructure(['props' => ['workbench' => ['placeholderCatalog', 'documentStylesheet']]]);
    }

    public function test_the_pdf_preview_renders_a_template_without_saving_anything(): void
    {
        $type = DocumentType::factory()->create();
        $documents = DocumentTemplate::query()->count();

        $response = $this->actingAs($this->administrator)
            ->postJson(route('master-data.document-templates.pdf-preview'), [
                'title' => 'HPS',
                'body' => '<style>@page { size: A4 portrait; }</style><h1>Harga Perhitungan Sendiri</h1><p>Contoh data</p>',
                'document_type_id' => $type->id,
            ])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertSame($documents, DocumentTemplate::query()->count());

        $this->actingAs($this->administrator)
            ->postJson(route('master-data.document-templates.pdf-preview'), ['body' => ''])
            ->assertJsonValidationErrors('body');
    }

    public function test_only_who_manages_the_master_data_previews_a_template(): void
    {
        $this->actingAs(User::factory()->planner()->create())
            ->postJson(route('master-data.document-templates.pdf-preview'), ['body' => '<p>x</p>'])
            ->assertForbidden();
    }
}
