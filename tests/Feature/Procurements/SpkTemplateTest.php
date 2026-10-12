<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\DocumentPdfRenderer;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpkTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_spk_follows_the_agreement_layout_with_procurement_data(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(KontrakTemplateSeeder::class);

        $procurement = Procurement::factory()->create([
            'number' => 'KDD334.SPK/612/UPKD/2026',
            'name' => 'Perbaikan Body CVP PLTGU Blok 2',
            'partner_name' => 'PT MORINDO ENGINEERING',
            'partner_director_name' => 'YUSTINUS PRAMU DEWASA',
            'partner_address' => 'Jalan Kelapa Dua No.201, Kota Bekasi',
            'value_after_negotiation' => 62230000,
        ]);
        $procurement->forceFill([
            'bank_name' => 'Bank Permata',
            'bank_account_number' => '4000544766',
            'bank_account_holder' => 'PT MORINDO ENGINEERING',
            'execution_duration_days' => 14,
            'warranty_months' => 6,
        ])->save();

        $type = DocumentType::query()->where('code', 'spk')->firstOrFail();
        $document = app(DocumentGenerator::class)
            ->generate($procurement, $type, User::factory()->administrator()->create());
        $text = preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($document->rendered_body), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        foreach ([
            'SURAT PERINTAH KERJA',
            'Nomor : KDD334.SPK/612/UPKD/2026',
            'PT MORINDO ENGINEERING, suatu perseroan terbatas',
            'berkantor di Jalan Kelapa Dua No.201, Kota Bekasi',
            'diwakili oleh YUSTINUS PRAMU DEWASA',
            'Berdasarkan :',
            'LINGKUP PEKERJAAN',
            'sebesar Rp 62.230.000,00',
            '14 (empat belas) Hari Kalender',
            ': Bank Permata',
            ': 4000544766',
            'selama 6 (enam) bulan',
            'Pasal 20',
            'PERUBAHAN-PERUBAHAN',
            'ditandatangani di Kendari',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }

        // A full agreement: several pages, each stamped with the contract footer.
        $pdf = app(DocumentPdfRenderer::class)->render($document);
        preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);
        $this->assertGreaterThanOrEqual(6, count($pages[0]));
    }
}
