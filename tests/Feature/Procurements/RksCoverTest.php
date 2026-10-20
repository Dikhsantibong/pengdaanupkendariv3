<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMethod;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PjTemplateSeeder;
use Database\Seeders\RksSpkTemplateSeeder;
use Database\Seeders\RksTenderTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every RKS opens with the same cover: the blue stripes, "PT PLN Nusantara
 * Power / Rencana Kerja dan Syarat-Syarat (RKS)", its number and date, the
 * logo, the work, and Unit Pembangkitan Kendari with the year on the band.
 */
class RksCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(RksSpkTemplateSeeder::class);
        $this->seed(RksTenderTemplateSeeder::class);
        $this->seed(PjTemplateSeeder::class);
        $this->travelTo('2026-10-20 09:00:00');
    }

    public function test_every_rks_draws_the_new_cover(): void
    {
        $method = fn (string $code): ?int => ProcurementMethod::query()->where('code', $code)->value('id');

        $procurements = [
            'rks.spk' => ['procurement_method_id' => $method('spk')],
            'rks.tender' => ['procurement_method_id' => $method('tender')],
            'pj.rks-jasa' => [
                'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'PJ')->value('id'),
                'work_category' => 'jasa',
            ],
            'umum.rks' => [],
        ];

        $this->assertFileExists(public_path('image/rks-cover.png'));

        foreach ($procurements as $key => $attributes) {
            $procurement = Procurement::factory()->create([
                'name' => 'Pengadaan Jasa Rewinding Generator',
                'rks_number' => '0123.RKS/DAN.01.03/UPKD/2026',
                ...$attributes,
            ]);
            $document = $this->generateRks($procurement);
            $cover = $this->cover($document->rendered_body);

            $this->assertSame($key, $document->documentTemplate?->library_key, $key);
            $this->assertStringContainsString('/image/rks-cover.png', $cover, $key);
            $this->assertStringContainsString('PT PLN NUSANTARA POWER<br>RENCANA KERJA DAN SYARAT-SYARAT<br>(RKS)', $cover, $key);
            $this->assertStringContainsString('0123.RKS/DAN.01.03/UPKD/2026', $cover, $key);
            $this->assertStringContainsString('20 Oktober 2026', $cover, $key);
            $this->assertStringContainsString('/logo/sidebar-logo.png', $cover, $key);
            $this->assertStringContainsString('Pengadaan Jasa Rewinding Generator', $cover, $key);
            $this->assertStringContainsString('UNIT PEMBANGKITAN KENDARI<br>', $cover, $key);
            $this->assertStringNotContainsString('SUPPLY CHAIN', $cover, $key);
            $this->assertDoesNotMatchRegularExpression('/\{\{[a-z0-9_]+\}\}/', $document->rendered_body, $key);
        }
    }

    public function test_the_artwork_reaches_the_edges_of_each_page(): void
    {
        // The page margins are 20 mm by 15 mm, and 20 mm by 22 mm on the left
        // for the PJ RKS: the artwork is pulled back by as much.
        foreach (['rks/metode-spk.html' => 15, 'rks/metode-tender.html' => 15, 'umum/rks.html' => 15, 'pj/rks-jasa.html' => 22] as $file => $left) {
            $body = (string) file_get_contents(resource_path('document-templates/'.$file));

            $this->assertStringContainsString("top: -20mm; left: -{$left}mm; width: 210mm; height: 297mm;", $body, $file);
            $this->assertSame(1, substr_count($body, 'class="cover"'), $file);
        }
    }

    public function test_a_customised_rks_keeps_its_changes_and_gets_the_new_cover(): void
    {
        $template = DocumentTemplate::query()->where('library_key', 'rks.spk')->firstOrFail();
        $template->update([
            'body' => '<section class="cover"><p class="org">PT PLN NUSANTARA POWER<br>UP KENDARI</p>'
                .'<p class="footer">UP KENDARI<br>{{tahun}}</p></section>'
                .'<section class="bab"><p>Ketentuan tambahan dari administrator.</p></section>',
        ]);

        (require database_path('migrations/2026_10_20_081934_put_the_new_cover_on_every_rks.php'))->up();

        $body = $template->refresh()->body;

        $this->assertStringContainsString('/image/rks-cover.png', $body);
        $this->assertStringContainsString('UNIT PEMBANGKITAN KENDARI', $body);
        $this->assertStringNotContainsString('<p class="org">', $body);
        $this->assertStringContainsString('Ketentuan tambahan dari administrator.', $body);
    }

    protected function generateRks(Procurement $procurement): ProcurementDocument
    {
        $rks = DocumentType::query()->where('code', 'rks')->firstOrFail();

        return app(DocumentGenerator::class)
            ->generate($procurement, $rks, User::factory()->administrator()->create())
            ->load('documentTemplate');
    }

    /**
     * The cover of a drawn RKS.
     */
    protected function cover(string $body): string
    {
        $start = strpos($body, 'class="cover"');
        $this->assertNotFalse($start);

        $end = strpos($body, 'UNIT PEMBANGKITAN KENDARI', $start);
        $this->assertNotFalse($end);

        return substr($body, $start, $end - $start + 200);
    }
}
