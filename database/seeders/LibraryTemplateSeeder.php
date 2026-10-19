<?php

namespace Database\Seeders;

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Installs default document templates from the template library.
 *
 * The wording itself lives in resources/document-templates (HTML files plus
 * manifest.php), never in seeder code. A seeder only names the groups it
 * installs; templates already installed are left as they are.
 */
abstract class LibraryTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The library groups this seeder installs; empty means every group.
     *
     * @var array<int, string>
     */
    protected array $groups = [];

    /**
     * Templates always put back to the shipped default, even when an
     * administrator changed them.
     *
     * @var array<int, string>
     */
    protected array $forced = [];

    /**
     * Seed the templates.
     */
    public function run(): void
    {
        $library = app(DocumentTemplateLibrary::class);

        // Templates whose document type is not set up yet are skipped; run
        // `php artisan templates:sync` for a per-template report.
        $library->sync(groups: $this->groups);

        if ($this->forced !== []) {
            $library->sync(keys: $this->forced, force: true);
        }
    }
}
