<?php

namespace Tests\Feature\Procurements;

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
 * The TOR header leaves Nomor Dokumen to be filled in by hand and dates the
 * TOR on the day it was generated.
 */
class TorHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);
    }

    /**
     * The value cell of one header row.
     */
    protected function headerValue(string $body, string $label): ?string
    {
        return preg_match('#<td class="label">'.preg_quote($label, '#').'</td><td class="colon">:</td><td>(.*?)</td>#s', $body, $match)
            ? $match[1]
            : null;
    }

    public function test_a_new_tor_leaves_the_number_empty_and_carries_its_issue_date(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 7));
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();

        $document = app(DocumentGenerator::class)->generate($procurement, $tor, $planner);

        $this->assertSame('', $this->headerValue($document->rendered_body, 'Nomor Dokumen / Pengadaan'));
        $this->assertSame('', $this->headerValue($document->rendered_body, 'Halaman'));
        $this->assertSame('07 September 2026', $this->headerValue($document->rendered_body, 'Tanggal Terbit'));

        // Opened again later, the issue date stays the day it was generated.
        $this->travel(40)->days();
        app(DocumentGenerator::class)->refreshDocument($document->refresh());
        $this->assertSame('07 September 2026', $this->headerValue($document->refresh()->rendered_body, 'Tanggal Terbit'));
    }

    public function test_tors_drawn_before_get_the_same_header(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();
        $document = app(DocumentGenerator::class)->generate($procurement, $tor, $planner);

        $old = '<td class="label">Nomor Dokumen / Pengadaan</td><td class="colon">:</td><td><span data-field="nomor_pengadaan" data-value="PGD/1">PGD/1</span></td></tr>'
            .'<tr><td class="label">Tanggal Terbit</td><td class="colon">:</td><td>18 Oktober 2026</td></tr>'
            .'<tr><td class="label">Halaman</td><td class="colon">:</td><td>1 dari <span data-field="status_progres" data-value="Inisiasi">Inisiasi</span></td>';
        ProcurementDocument::query()->whereKey($document->id)->update([
            'rendered_body' => '<p>Diedit</p>'.$old,
            'revision' => 2,
            'generated_at' => '2026-09-07 09:28:00',
        ]);

        (require database_path('migrations/2026_10_19_014703_clear_the_document_number_of_the_tor_header.php'))->up();

        $body = $document->refresh()->rendered_body;
        $this->assertStringStartsWith('<p>Diedit</p>', $body);
        $this->assertSame('', $this->headerValue($body, 'Nomor Dokumen / Pengadaan'));
        $this->assertSame('', $this->headerValue($body, 'Halaman'));
        $this->assertSame('07 September 2026', $this->headerValue($body, 'Tanggal Terbit'));
    }
}
