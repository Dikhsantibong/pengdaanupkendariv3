<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PjTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Kontrak" step draws a Surat Perjanjian for the PJ format and keeps
 * drawing the Surat Perintah Kerja for the SPK format.
 */
class PjContractTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(KontrakTemplateSeeder::class);
        $this->seed(PjTemplateSeeder::class);
    }

    public function test_a_pj_procurement_gets_the_surat_perjanjian(): void
    {
        $procurement = $this->procurement('PJ', [
            'number' => 'KDD325.PJ/612/UPKD/2026',
            'name' => 'Penggantian Pompa Brine Blowdown',
        ]);

        $document = $this->generate($procurement);
        $text = $this->text($document->rendered_body);

        $this->assertSame('Surat Perjanjian (PJ) - Penggantian Pompa Brine Blowdown', $document->title);

        foreach ([
            'SURAT PERJANJIAN',
            // Printed in capitals by the template's styling.
            'Penggantian Pompa Brine Blowdown',
            'UNIT PEMBANGKITAN KENDARI',
            'NOMOR : KDD325.PJ/612/UPKD/2026',
            'PT TORISHIMA SERVICES INDONESIA, suatu perseroan terbatas',
            'diwakili oleh HIDEKI DEI',
            'sebesar Rp 305.467.500,00',
            '180 (seratus delapan puluh) hari kalender',
            ': Bank Mandiri',
            ': 1250005594189',
            '18 (delapan belas) bulan',
            'Pasal 1 DEFINISI',
            'Pasal 20 KEPATUHAN TERHADAP PERATURAN DAN KEBIJAKAN ANTI PENYUAPAN',
            'Pasal 22 ADMINISTRASI DAN SURAT MENYURAT',
            'ditandatangani di Kendari',
        ] as $expected) {
            // Headings and table cells sit in separate tags, so spacing is
            // left out of the comparison.
            $this->assertStringContainsString(
                str_replace(' ', '', $expected),
                str_replace(' ', '', $text),
            );
        }

        $this->assertStringNotContainsString('SURAT PERINTAH KERJA', $text);
        $this->assertStringNotContainsString('{{', $document->rendered_body);
    }

    public function test_an_spk_procurement_still_gets_the_surat_perintah_kerja(): void
    {
        $document = $this->generate($this->procurement('SPK', ['name' => 'Perbaikan Body CVP']));
        $text = $this->text($document->rendered_body);

        $this->assertSame('SPK (Surat Perintah Kerja) - Perbaikan Body CVP', $document->title);
        $this->assertStringContainsString('SURAT PERINTAH KERJA', $text);
        $this->assertStringNotContainsString('SURAT PERJANJIAN', $text);
    }

    public function test_the_contract_is_named_after_the_format(): void
    {
        $type = DocumentType::query()->where('code', 'spk')->firstOrFail();

        $this->assertSame('Surat Perjanjian (PJ)', $type->nameFor($this->procurement('PJ')));
        $this->assertSame('SPK (Surat Perintah Kerja)', $type->nameFor($this->procurement('SPK')));
        $this->assertSame('SPK (Surat Perintah Kerja)', $type->nameFor(null));
    }

    /**
     * A procurement drawn up under one contract format, ready for its contract.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function procurement(string $format, array $attributes = []): Procurement
    {
        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', $format)->value('id'),
            'partner_name' => 'PT TORISHIMA SERVICES INDONESIA',
            'partner_director_name' => 'HIDEKI DEI',
            'partner_address' => 'Jl. Selayar II Blok H-2 Kawasan Industri MM2100 Cikarang Barat, Bekasi 17844',
            'value_after_negotiation' => 305467500,
            ...$attributes,
        ]);
        $procurement->forceFill([
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1250005594189',
            'bank_account_holder' => 'PT TORISHIMA SERVICES INDONESIA',
            'execution_duration_days' => 180,
            'warranty_months' => 18,
        ])->save();

        return $procurement->refresh();
    }

    protected function generate(Procurement $procurement): ProcurementDocument
    {
        return app(DocumentGenerator::class)->generate(
            $procurement,
            DocumentType::query()->where('code', 'spk')->firstOrFail(),
            User::factory()->administrator()->create(),
        );
    }

    protected function text(string $html): string
    {
        return (string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
