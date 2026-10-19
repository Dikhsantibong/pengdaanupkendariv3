<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan tor:perbaiki` puts the TOR outline in place and repairs the
 * TORs already drawn; the seeder always ships the outline.
 */
class RepairTorDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentType $tor;

    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $this->tor = DocumentType::query()->where('code', 'tor')->firstOrFail();
        $this->planner = User::factory()->planner()->create();
    }

    /**
     * A TOR drawn with the old narrative and kop.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function oldTor(array $attributes = []): ProcurementDocument
    {
        $document = app(DocumentGenerator::class)->generate(Procurement::factory()->plannedBy($this->planner)->create(), $this->tor, $this->planner);

        ProcurementDocument::query()->whereKey($document->id)->update([
            'rendered_body' => '<style>.x{}</style><table class="header"><tr><td class="header-left">logo</td><td class="header-right"><table>'
                .'<tr><td class="label">Nomor Dokumen / Pengadaan</td><td class="colon">:</td><td>PGD/1</td></tr></table></td></tr></table>'
                .'<p class="para">Pekerjaan ini dilaksanakan sebagai bagian dari kebutuhan.</p>',
            'generated_at' => '2026-09-07 09:00:00',
            ...$attributes,
        ]);

        return $document->refresh();
    }

    /**
     * Give a document a signed scan.
     */
    protected function sign(ProcurementDocument $document): void
    {
        $document->signedUploads()->create(['path' => 'x.pdf', 'file_name' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 1, 'uploaded_by' => $this->planner->id]);
    }

    public function test_the_seeder_puts_back_an_outline_an_administrator_changed(): void
    {
        $template = DocumentTemplate::query()->where('library_key', 'umum.tor')->firstOrFail();
        $template->update(['body' => '<p>Narasi lama yang diubah admin</p>']);

        $this->seed(DocumentTemplateLibrarySeeder::class);

        $body = $template->refresh()->body;
        $this->assertStringNotContainsString('Narasi lama', $body);
        $this->assertStringContainsString('1.1. Latar Belakang', $body);
    }

    public function test_the_command_repairs_the_template_and_the_tors_drawn(): void
    {
        DocumentTemplate::query()->where('library_key', 'umum.tor')->update(['body' => '<p>Narasi lama</p>']);

        $untouched = $this->oldTor();
        $typed = $this->oldTor(['revision' => 2]);
        $signed = $this->oldTor();
        $this->sign($signed);

        $this->artisan('tor:perbaiki')->assertSuccessful();

        // The template is the outline again.
        $this->assertStringContainsString('1.1. Latar Belakang', DocumentTemplate::query()->where('library_key', 'umum.tor')->value('body'));

        // Nobody typed into it: drawn again as the outline, still dated the
        // day it was generated.
        $body = $untouched->refresh()->rendered_body;
        $this->assertStringNotContainsString('dilaksanakan sebagai bagian', $body);
        $this->assertStringContainsString('1.1. Latar Belakang', $body);
        $this->assertStringContainsString('07 - 09 - 2026', $body);

        // Typed into: what was typed stays under the form's kop.
        $body = $typed->refresh()->rendered_body;
        $this->assertStringContainsString('dilaksanakan sebagai bagian', $body);
        $this->assertStringContainsString('FMKP – 167 – 9.1.1.2.e.a.c - 001', $body);
        $this->assertStringNotContainsString('PGD/1', $body);

        // Signed: left as signed.
        $this->assertStringContainsString('PGD/1', $signed->refresh()->rendered_body);
    }

    public function test_the_options_reach_signed_and_typed_tors(): void
    {
        $typed = $this->oldTor(['revision' => 2]);
        $signed = $this->oldTor();
        $this->sign($signed);

        $this->artisan('tor:perbaiki', ['--termasuk-ttd' => true, '--timpa-isian' => true])->assertSuccessful();

        foreach ([$typed, $signed] as $document) {
            $body = $document->refresh()->rendered_body;
            $this->assertStringNotContainsString('dilaksanakan sebagai bagian', $body);
            $this->assertStringContainsString('1.1. Latar Belakang', $body);
            $this->assertSame(0, (int) $document->revision);
        }

        // The scan itself is kept.
        $this->assertTrue($signed->signedUploads()->exists());
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $untouched = $this->oldTor();

        $this->artisan('tor:perbaiki', ['--dry-run' => true])->assertSuccessful();

        $this->assertStringContainsString('dilaksanakan sebagai bagian', $untouched->refresh()->rendered_body);
    }
}
