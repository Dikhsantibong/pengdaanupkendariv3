<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
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
     * The words of a body, one space wherever a tag or line break stood.
     */
    protected function words(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/<[^>]+>/', ' ', $html)));
    }

    /**
     * The value cell of one header row.
     */
    protected function headerValue(string $body, string $label): ?string
    {
        // The kop of the form ("Label | : value"), or the old one ("Label | : | value").
        if (preg_match('#<td class="label">'.preg_quote($label, '#').'</td>\s*<td class="value">: ?(.*?)</td>#s', $body, $match)) {
            return $match[1];
        }

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

        // The kop of the form.
        $text = $this->words($document->rendered_body);
        $this->assertStringContainsString('PT PLN NUSANTARA POWER Nomor Dokumen : FMKP – 167 – 9.1.1.2.e.a.c - 001 INTEGRATED MANAGEMENT SYSTEM Revisi : 00 TERM OF REFERENCE (TOR) Tanggal Terbit : 07 - 09 - 2026 Halaman :', $text);
        // The fixed number of the form, the same on every TOR.
        $this->assertSame('FMKP – 167 – 9.1.1.2.e.a.c - 001', $this->headerValue($document->rendered_body, 'Nomor Dokumen'));
        $this->assertSame('', $this->headerValue($document->rendered_body, 'Halaman'));
        $this->assertSame('07 - 09 - 2026', $this->headerValue($document->rendered_body, 'Tanggal Terbit'));

        // Opened again later, the issue date stays the day it was generated.
        $this->travel(40)->days();
        app(DocumentGenerator::class)->refreshDocument($document->refresh());
        $this->assertSame('07 - 09 - 2026', $this->headerValue($document->refresh()->rendered_body, 'Tanggal Terbit'));
    }

    public function test_the_kop_carries_the_issue_date_the_user_gave(): void
    {
        $administrator = User::factory()->administrator()->create();
        $procurement = Procurement::factory()->make();

        $this->actingAs($administrator)
            ->post(route('procurements.store'), [
                'name' => 'Pengadaan TOR Bertanggal',
                'work_director_id' => $procurement->work_director_id,
                'target_unit_ids' => [$procurement->target_unit_id],
                'budget_source_id' => $procurement->budget_source_id,
                'hpe_value' => 1000000,
                'tor_issue_date' => '2023-02-06',
            ])
            ->assertSessionHasNoErrors();

        $created = Procurement::query()->where('name', 'Pengadaan TOR Bertanggal')->firstOrFail();
        $this->assertSame('2023-02-06', $created->tor_issue_date?->toDateString());

        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();
        $body = app(DocumentGenerator::class)->generate($created, $tor, $administrator)->rendered_body;

        $this->assertSame('06 - 02 - 2023', $this->headerValue($body, 'Tanggal Terbit'));

        $this->actingAs($administrator)
            ->get(route('procurements.edit', $created))
            ->assertInertia(fn ($page) => $page->where('procurement.tor_issue_date', '2023-02-06'));
    }

    public function test_tors_drawn_before_get_the_form_number(): void
    {
        $planner = User::factory()->planner()->create();
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();
        $document = app(DocumentGenerator::class)->generate(Procurement::factory()->plannedBy($planner)->create(), $tor, $planner);
        ProcurementDocument::query()->whereKey($document->id)->update([
            'rendered_body' => '<table class="header"><tr><td class="label">Nomor Dokumen</td><td class="value">: </td></tr></table><p>Diketik</p>',
            'revision' => 2,
        ]);

        (require database_path('migrations/2026_10_19_035247_put_the_form_number_on_the_tor_kop.php'))->up();

        $body = $document->refresh()->rendered_body;
        $this->assertSame('FMKP – 167 – 9.1.1.2.e.a.c - 001', $this->headerValue($body, 'Nomor Dokumen'));
        $this->assertStringContainsString('<p>Diketik</p>', $body);
    }

    public function test_the_tor_holds_only_its_points_and_sub_points(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create(['name' => 'Pengadaan Pompa Air']);
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();

        $body = app(DocumentGenerator::class)->generate($procurement, $tor, $planner)->rendered_body;
        $text = $this->words($body);

        foreach (['1. PENDAHULUAN', '1.1. Latar Belakang', '1.2.1. Maksud', '2.1. Standart Nasional', '8.1. Identifikasi Bahaya', '9.2. Dokumen Penagihan', '10. BARANG SISA', '14. LAIN-LAIN'] as $heading) {
            $this->assertStringContainsString($heading, $text);
        }

        // No narrative: the body between the first point and the signature
        // page carries no sentence of its own.
        $outline = substr($text, (int) strpos($text, '1. PENDAHULUAN'), (int) strpos($text, 'LEMBAR PENGESAHAN') - (int) strpos($text, '1. PENDAHULUAN'));
        $this->assertStringNotContainsString('Pengadaan Pompa Air', $outline);
        $this->assertStringNotContainsString('dilaksanakan', $outline);
    }

    public function test_the_tors_drawn_before_follow_the_outline_or_keep_their_text_with_the_new_kop(): void
    {
        $planner = User::factory()->planner()->create();
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();
        $generator = app(DocumentGenerator::class);
        $oldKop = '<table class="header"><tr><td class="header-left">logo</td><td class="header-middle">INTEGRATED MANAGEMENT SYSTEM</td><td class="header-right"><table>'
            .'<tr><td class="label">Nomor Dokumen / Pengadaan</td><td class="colon">:</td><td>PGD/1</td></tr></table></td></tr></table>';

        // Nobody touched this one: it is drawn again as the outline.
        $untouched = $generator->generate(Procurement::factory()->plannedBy($planner)->create(), $tor, $planner);
        ProcurementDocument::query()->whereKey($untouched->id)->update(['rendered_body' => '<style>.x{}</style>'.$oldKop.'<p>Narasi lama</p>']);

        // This one was typed in: its text stays, the kop is replaced.
        $edited = $generator->generate(Procurement::factory()->plannedBy($planner)->create(), $tor, $planner);
        ProcurementDocument::query()->whereKey($edited->id)->update([
            'rendered_body' => '<style>.x{}</style>'.$oldKop.'<p>Narasi yang diketik</p>',
            'revision' => 3,
            'generated_at' => '2026-09-07 09:00:00',
        ]);

        (require database_path('migrations/2026_10_19_034141_outline_the_tor_and_use_the_form_kop.php'))->up();

        $this->assertStringNotContainsString('Narasi lama', $untouched->refresh()->rendered_body);
        $this->assertStringContainsString('1.1. Latar Belakang', $untouched->rendered_body);

        $body = $edited->refresh()->rendered_body;
        $this->assertStringContainsString('<p>Narasi yang diketik</p>', $body);
        $this->assertStringNotContainsString('PGD/1', $body);
        $this->assertStringContainsString('PT PLN NUSANTARA POWER', $body);
        $this->assertSame('07 - 09 - 2026', $this->headerValue($body, 'Tanggal Terbit'));
        $this->assertStringContainsString('.header .company', $body);
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

        $text = $this->words(app(DocumentGenerator::class)->generate($procurement, $tor, $proposer)->rendered_body);

        // Drawn up by the user; checked by their Asman (left) and the Asman
        // Engineering (right); approved by the Manager.
        $this->assertStringContainsString('Disusun oleh: User ( Galih Prima )', $text);
        $this->assertStringContainsString('Diperiksa/Diketahui oleh: Asman Pemeliharaan Asman Engineering ( Budi Pemeliharaan ) ( Eka Engineering )', $text);
        $this->assertStringContainsString('Disetujui oleh: Manager', $text);
    }

    public function test_an_sppl_tor_is_signed_by_three(): void
    {
        $maintenance = WorkDirector::factory()->create(['name' => 'Asman Pemeliharaan']);
        $engineering = WorkDirector::query()->where('name', 'Asman Engineering')->firstOrFail();
        $proposer = User::factory()->proposer($maintenance)->create(['name' => 'Galih Prima']);
        User::factory()->workDirector($maintenance)->create(['name' => 'Budi Pemeliharaan']);
        User::factory()->workDirector($engineering)->create(['name' => 'Eka Engineering']);
        $procurement = Procurement::factory()->create([
            'work_director_id' => $maintenance->id,
            'created_by' => $proposer->id,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPPL')->value('id'),
        ]);
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();

        $body = app(DocumentGenerator::class)->generate($procurement, $tor, $proposer)->rendered_body;
        $text = $this->words($body);

        // The Asman on the left, the user on the right, the Manager below.
        $this->assertStringContainsString('Diperiksa/Diketahui oleh: Disusun oleh: Asman Pemeliharaan User ( Budi Pemeliharaan ) ( Galih Prima )', $text);
        $this->assertStringContainsString('Disetujui oleh: Manager', $text);
        $this->assertStringNotContainsString('Eka Engineering', $text);
        // Names print in capitals.
        $this->assertStringContainsString('<span style="text-transform: uppercase;">Galih Prima</span>', $body);
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
        $this->assertSame(3, substr_count($body, '( <span style="text-transform: uppercase;">.........................................</span> )'));
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
