<?php

namespace Tests\Feature\MasterData;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Services\DocumentTemplateLibrary;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DocumentTemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/template-library-'.uniqid());
        File::ensureDirectoryExists($this->root.'/demo');
        File::put($this->root.'/manifest.php', "<?php\n\nreturn ['demo.memo' => ['group' => 'demo', 'file' => 'demo/memo.html', 'document_type' => 'memo', 'procurement_method' => null, 'contract_format' => null, 'work_category' => null, 'name' => 'Memo Bawaan']];\n");
        File::put($this->root.'/demo/memo.html', "<p>Versi satu {{nama_pengadaan}}</p>\n");

        DocumentType::factory()->create(['code' => 'memo']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    protected function library(): DocumentTemplateLibrary
    {
        return new DocumentTemplateLibrary($this->root);
    }

    public function test_the_hpe_and_upb_defaults_print_in_landscape(): void
    {
        $root = resource_path('document-templates');
        $entries = array_filter(
            require $root.'/manifest.php',
            fn (array $entry): bool => in_array($entry['document_type'], ['hpe', 'upb'], true),
        );

        $this->assertNotEmpty($entries);

        foreach ($entries as $key => $entry) {
            $this->assertStringContainsString(
                'size: A4 landscape',
                File::get($root.'/'.$entry['file']),
                "Template [{$key}] harus lanskap.",
            );
        }
    }

    public function test_a_default_is_installed_once_and_then_reported_up_to_date(): void
    {
        $this->assertSame(['demo.memo' => DocumentTemplateLibrary::INSTALLED], $this->library()->sync());
        $this->assertSame(['demo.memo' => DocumentTemplateLibrary::UP_TO_DATE], $this->library()->sync());

        $template = DocumentTemplate::query()->where('library_key', 'demo.memo')->sole();
        $this->assertTrue($template->is_active);
        $this->assertSame(['nama_pengadaan'], $template->placeholders);
    }

    public function test_a_changed_file_becomes_a_new_version_of_an_untouched_default(): void
    {
        $this->library()->sync();
        File::put($this->root.'/demo/memo.html', "<p>Versi dua</p>\n");

        $this->assertSame(['demo.memo' => DocumentTemplateLibrary::UPDATED], $this->library()->sync());

        $versions = DocumentTemplate::query()->where('library_key', 'demo.memo')->orderBy('version')->get();
        $this->assertSame([1, 2], $versions->pluck('version')->all());
        $this->assertSame([false, true], $versions->pluck('is_active')->all());
        $this->assertStringContainsString('Versi satu', $versions[0]->body);
    }

    public function test_a_template_edited_by_an_administrator_is_never_overwritten(): void
    {
        $this->library()->sync();
        DocumentTemplate::query()->where('library_key', 'demo.memo')->update(['body' => '<p>Redaksi resmi unit</p>']);
        File::put($this->root.'/demo/memo.html', "<p>Versi dua</p>\n");

        $this->assertSame(['demo.memo' => DocumentTemplateLibrary::CUSTOMISED], $this->library()->sync());
        $this->assertSame('<p>Redaksi resmi unit</p>', DocumentTemplate::query()->where('is_active', true)->sole()->body);

        // Forcing installs the default beside it, as a new version.
        $this->assertSame(['demo.memo' => DocumentTemplateLibrary::UPDATED], $this->library()->sync(force: true));
        $this->assertSame(2, DocumentTemplate::query()->where('library_key', 'demo.memo')->count());
    }

    public function test_an_administrators_own_template_keeps_its_scope(): void
    {
        DocumentTemplate::factory()->create([
            'document_type_id' => DocumentType::query()->where('code', 'memo')->value('id'),
            'name' => 'Memo buatan admin',
        ]);

        $this->assertSame(['demo.memo' => DocumentTemplateLibrary::CUSTOMISED], $this->library()->sync());
        $this->assertSame(0, DocumentTemplate::query()->where('library_key', 'demo.memo')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->assertSame(['demo.memo' => DocumentTemplateLibrary::INSTALLED], $this->library()->sync(dryRun: true));
        $this->assertSame(0, DocumentTemplate::query()->count());
    }

    public function test_every_shipped_entry_points_at_a_real_file_and_installs(): void
    {
        $library = new DocumentTemplateLibrary;

        foreach ($library->entries() as $key => $entry) {
            $this->assertNotSame('', trim($library->body($key)), $key);
        }

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $this->assertSame(
            collect($library->entries())->keys()->sort()->values()->all(),
            DocumentTemplate::query()->whereNotNull('library_key')->distinct()->pluck('library_key')->sort()->values()->all(),
        );
    }

    public function test_the_sync_command_reports_each_template(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->artisan('templates:sync', ['--group' => ['pj'], '--dry-run' => true])
            ->expectsOutputToContain('pj.rks-jasa')
            ->assertSuccessful();
    }
}
