<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Support\TenderDocuments;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tender's Berita Acara follow the unit's forms and are filled from the
 * calon mitra, their offers and the officials who sign.
 */
class TenderBeritaAcaraTest extends TestCase
{
    use RefreshDatabase;

    protected Procurement $procurement;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);
        // Kamis, 17 September 2026.
        $this->travelTo('2026-09-17 09:00:00');

        User::factory()->teamLeaderPengadaan()->create(['name' => 'Muhammad Amrullah']);
        User::factory()->teamLeaderIcc()->create(['name' => 'Bastial']);
        $planner = User::factory()->planner()->create(['name' => 'Iklan Nano Saputra']);
        $executor = User::factory()->executor()->create(['name' => 'Ahmad Bukhori']);
        $this->author = $executor;

        $this->procurement = Procurement::factory()->plannedBy($planner)->create([
            'name' => 'Pengadaan Lemari Rak Tools',
            'executor_id' => $executor->id,
            'hpe_value' => 100000000,
        ]);

        $this->procurement->partnerCandidates()->createMany([
            ['name' => 'Kilat Berkah. PT', 'quotation_number' => '07/KB/IX/2026', 'quotation_value' => 105000000, 'sort_order' => 1],
            ['name' => 'CV. Zafran Alfarizi', 'quotation_number' => '106/SPB/ZA-MKS/IX/2026', 'quotation_value' => 98205446, 'sort_order' => 2],
            ['name' => 'PT. Andalan Pilar Harmoni', 'quotation_number' => null, 'quotation_value' => null, 'sort_order' => 3],
        ]);
    }

    /**
     * The body of a document of the type, drawn now.
     */
    protected function draw(string $code): string
    {
        $type = DocumentType::query()->where('code', $code)->firstOrFail();

        return app(DocumentGenerator::class)->generate($this->procurement, $type, $this->author)->rendered_body;
    }

    /**
     * The words of a body, one space wherever a tag stood.
     */
    protected function words(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $html))));
    }

    public function test_the_aanwijzing_lists_the_participants_and_the_officials(): void
    {
        $text = $this->words($this->draw('ba-aanwijzing'));

        $this->assertStringContainsString('BERITA ACARA PEMBERIAN PENJELASAN (AANWIJZING)', $text);
        $this->assertStringContainsString('Pada hari ini Kamis, tanggal Tujuh Belas bulan September tahun Dua Ribu Dua Puluh Enam', $text);
        $this->assertStringContainsString('1 KILAT BERKAH. PT NAMA', $text);
        $this->assertStringContainsString('2 CV. ZAFRAN ALFARIZI', $text);
        $this->assertStringContainsString('3 PT. ANDALAN PILAR HARMONI', $text);
        $this->assertStringContainsString('Muhammad Amrullah TEAM LEADER PENGADAAN', $text);
        $this->assertStringContainsString('Ahmad Bukhori JUNIOR OFFICER PENGADAAN', $text);
    }

    public function test_the_opening_and_evaluation_tables_have_a_column_per_participant(): void
    {
        foreach (['lampiran-bapp', 'ba-evaluasi-teknis', 'ba-evaluasi-harga'] as $code) {
            $text = $this->words($this->draw($code));

            $this->assertStringContainsString('KILAT BERKAH. PT CV. ZAFRAN ALFARIZI PT. ANDALAN PILAR HARMONI', $text, $code);
        }

        $bapp = $this->words($this->draw('lampiran-bapp'));
        $this->assertStringContainsString('a. Spesifikasi Barang/Jasa yang ditawarkan (Lampiran-1) A A A', $bapp);
        $this->assertStringContainsString('2 KESIMPULAN AKHIR L L L', $bapp);

        $technical = $this->words($this->draw('ba-evaluasi-teknis'));
        $this->assertStringContainsString('l. Daftar Rincian Harga (lampiran-2). Yang dapat dipertanggungjawabkan kerahasiannya. M M M', $technical);
        $this->assertStringContainsString('Kesimpulan Evaluasi Teknis MEMENUHI MEMENUHI MEMENUHI', $technical);

        // By price: Zafran first, Kilat second; no offer, no place.
        $price = $this->words($this->draw('ba-evaluasi-harga'));
        $this->assertStringContainsString('Urutan Harga Penawaran 2 1', $price);
    }

    public function test_the_hasil_evaluasi_counts_and_ranks_the_offers(): void
    {
        $text = $this->words($this->draw('ba-hasil-evaluasi'));

        $this->assertStringContainsString('yang memasukkan penawaran ada 3 (Tiga) Perusahaan', $text);
        $this->assertStringContainsString('1 KILAT BERKAH. PT Rp 105.000.000 LENGKAP', $text);
        // The cheapest first, against the HPS.
        $this->assertStringContainsString('1 CV. ZAFRAN ALFARIZI Rp 98.205.446 98,21%', $text);
        $this->assertStringContainsString('2 KILAT BERKAH. PT Rp 105.000.000 105,00%', $text);
        $this->assertStringContainsString('Perhitungan HPS : Rp 100.000.000,00', $text);
        $this->assertStringContainsString('Muhammad Amrullah PEJABAT PELAKSANA PENGADAAN', $text);
        $this->assertStringContainsString('Bastial PEJABAT PERENCANA PENGADAAN', $text);
        $this->assertStringContainsString('Iklan Nano Saputra PIC PERENCANA PENGADAAN', $text);
        $this->assertStringContainsString('Ahmad Bukhori PIC PELAKSANA PENGADAAN', $text);
    }

    public function test_the_klarifikasi_lists_the_offer_letters(): void
    {
        $text = $this->words($this->draw('ba-klarifikasi'));

        $this->assertStringContainsString('BERITA ACARA KLARIFIKASI TEKNIK', $text);
        $this->assertStringContainsString('2 CV. ZAFRAN ALFARIZI 106/SPB/ZA-MKS/IX/2026', $text);
        $this->assertStringContainsString('TIM TEKNIS PT PLN Nusantara Power', $text);
    }

    public function test_without_calon_mitra_the_tables_leave_lines_to_write_on(): void
    {
        $this->procurement->partnerCandidates()->delete();

        $values = TenderDocuments::values($this->procurement->refresh(), now());

        $this->assertSame('0 (Nol)', $values['jumlah_peserta']);
        $this->assertSame(3, substr_count($values['tabel_peserta_penawaran'], '.........................'));
    }
}
