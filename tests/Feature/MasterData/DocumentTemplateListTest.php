<?php

namespace Tests\Feature\MasterData;

use App\Enums\WorkCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The list of document templates is searched, filtered and paged.
 */
class DocumentTemplateListTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_the_list_is_paged_twenty_at_a_time(): void
    {
        DocumentTemplate::factory()->count(23)->create();

        $this->actingAs($this->administrator)
            ->get(route('master-data.document-templates.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('templates.data', 20)
                ->where('templates.total', 23)
                ->has('filterOptions.documentTypes')
                ->where('filters.q', null));

        $this->actingAs($this->administrator)
            ->get(route('master-data.document-templates.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('templates.data', 3));
    }

    public function test_a_search_finds_a_template_by_its_name_or_document_type(): void
    {
        $rks = DocumentType::factory()->create(['name' => 'RKS Khusus']);
        DocumentTemplate::factory()->create(['name' => 'Template Nota Dinas', 'document_type_id' => DocumentType::factory()]);
        DocumentTemplate::factory()->create(['name' => 'Template Lain', 'document_type_id' => $rks->id]);

        $names = fn (array $query): array => $this->actingAs($this->administrator)
            ->get(route('master-data.document-templates.index', $query))
            ->viewData('page')['props']['templates']['data'];

        $this->assertSame(['Template Nota Dinas'], array_column($names(['q' => 'nota dinas']), 'name'));
        $this->assertSame(['Template Lain'], array_column($names(['q' => 'rks khusus']), 'name'));
    }

    public function test_the_filters_narrow_the_list(): void
    {
        $type = DocumentType::factory()->create();
        $tender = ProcurementMethod::factory()->create();

        DocumentTemplate::factory()->create(['name' => 'Umum', 'document_type_id' => $type->id]);
        DocumentTemplate::factory()->create(['name' => 'Tender', 'document_type_id' => $type->id, 'procurement_method_id' => $tender->id]);
        DocumentTemplate::factory()->create(['name' => 'Jasa', 'document_type_id' => $type->id, 'work_category' => WorkCategory::cases()[0]->value]);
        DocumentTemplate::factory()->inactive()->create(['name' => 'Lama', 'document_type_id' => $type->id]);
        DocumentTemplate::factory()->create(['name' => 'Lain']);

        $names = fn (array $query): array => collect($this->actingAs($this->administrator)
            ->get(route('master-data.document-templates.index', $query))
            ->viewData('page')['props']['templates']['data'])->pluck('name')->sort()->values()->all();

        $this->assertSame(['Jasa', 'Lama', 'Tender', 'Umum'], $names(['jenis' => $type->id]));
        $this->assertSame(['Tender'], $names(['metode' => $tender->id]));
        $this->assertNotContains('Tender', $names(['jenis' => $type->id, 'metode' => 'semua']));
        $this->assertSame(['Jasa'], $names(['jenis' => $type->id, 'kategori' => WorkCategory::cases()[0]->value]));
        $this->assertSame(['Lama'], $names(['status' => 'nonaktif']));
        $this->assertNotContains('Lama', $names(['jenis' => $type->id, 'status' => 'aktif']));
    }

    public function test_the_origin_filter_tells_shipped_templates_from_ones_written_here(): void
    {
        DocumentTemplate::factory()->create(['name' => 'Bawaan', 'library_key' => 'umum/contoh']);
        DocumentTemplate::factory()->create(['name' => 'Buatan', 'library_key' => null]);

        $this->actingAs($this->administrator)
            ->get(route('master-data.document-templates.index', ['asal' => 'bawaan']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates.data', fn (Collection $rows) => $rows->pluck('name')->all() === ['Bawaan']));

        $this->actingAs($this->administrator)
            ->get(route('master-data.document-templates.index', ['asal' => 'buatan']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates.data', fn (Collection $rows) => $rows->pluck('name')->all() === ['Buatan']));
    }
}
