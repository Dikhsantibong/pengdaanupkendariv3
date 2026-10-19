<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Models\WorkDirector;
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

    public function test_the_tor_is_signed_by_the_user_both_asman_and_the_manager(): void
    {
        $maintenance = WorkDirector::factory()->create(['name' => 'Asman Pemeliharaan']);
        // The Engineering division of the seeded Direksi Pekerjaan.
        $engineering = WorkDirector::query()->where('name', 'Asman Engineering')->firstOrFail();
        $proposer = User::factory()->proposer($maintenance)->create(['name' => 'Galih Prima']);
        User::factory()->workDirector($maintenance)->create(['name' => 'Budi Pemeliharaan']);
        User::factory()->workDirector($engineering)->create(['name' => 'Eka Engineering']);
        $procurement = Procurement::factory()->create(['work_director_id' => $maintenance->id, 'created_by' => $proposer->id]);
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();

        $text = preg_replace('/\s+/', ' ', strip_tags(app(DocumentGenerator::class)->generate($procurement, $tor, $proposer)->rendered_body));

        // Drawn up by the user; checked by their Asman (left) and the Asman
        // Engineering (right); approved by the Manager.
        $this->assertStringContainsString('Disusun oleh: User ( Galih Prima )', $text);
        $this->assertStringContainsString('Diperiksa/Diketahui oleh: Asman Pemeliharaan Asman Engineering ( Budi Pemeliharaan ) ( Eka Engineering )', $text);
        $this->assertStringContainsString('Disetujui oleh: Manager', $text);
    }

    public function test_a_signatory_not_set_up_leaves_a_line_to_write_on(): void
    {
        $division = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
        $procurement = Procurement::factory()->create(['work_director_id' => $division->id, 'created_by' => null]);

        $values = app(DocumentGenerator::class)->placeholderValues($procurement);

        $this->assertSame('.........................................', $values['nama_penyusun']);
        $this->assertSame('.........................................', $values['nama_asman_pengguna']);
        // No Asman Engineering user yet on the seeded division.
        $this->assertSame('.........................................', $values['nama_asman_engineering']);

        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();
        $body = app(DocumentGenerator::class)->generate($procurement, $tor, User::factory()->administrator()->create())->rendered_body;
        $this->assertStringContainsString('( <span style="text-transform: uppercase;"><span data-field="nama_asman_engineering" data-value=".........................................">.........................................</span></span> )', $body);
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
