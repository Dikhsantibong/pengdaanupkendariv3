<?php

namespace Tests\Feature\Procurements;

use Database\Seeders\FinanceRequirementSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Penyerahan and Pembayaran requirements are the lists written in the
 * contract templates. Should a template's delivery or payment article change,
 * this fails until the requirements are brought in line with it.
 */
class FinanceRequirementSourceTest extends TestCase
{
    /**
     * Requirement names deliberately worded apart from the template, with
     * the template wording they stand for.
     *
     * @var array<string, string>
     */
    private const REWORDED = [
        // The months are the procurement's own, filled into the contract.
        'Surat Garansi Barang bermaterai selama masa garansi (TTE tersertifikasi dan e-meterai)' => 'Surat Garansi Barang bermaterai selama {{masa_garansi_bulan}} ({{masa_garansi_bulan_terbilang}}) bulan (TTE tersertifikasi dan e-meterai)',
        // "tanda tangan elektronik" shortened to TTE to fit a checklist name.
        'Berita Acara Pemeriksaan Barang yang telah disetujui oleh PIHAK KESATU, termasuk rekomendasi jumlah hari keterlambatan/pengurang pembayaran (jika ada) yang ter-create dari Sistem Pemeriksaan Pekerjaan Secara Digital dan sudah dilengkapi TTE tersertifikasi' => 'Berita Acara Pemeriksaan Barang yang telah disetujui oleh PIHAK KESATU, termasuk rekomendasi jumlah hari keterlambatan/pengurang pembayaran (jika ada) yang ter-create dari Sistem Pemeriksaan Pekerjaan Secara Digital dan sudah dilengkapi dengan tanda tangan elektronik yang telah tersertifikasi',
    ];

    /**
     * Each list: the requirement source and section, the template file, and
     * the text the list follows.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function lists(): array
    {
        return [
            'SPK Barang — penyerahan (Pasal 6 ayat 7)' => ['SPK Barang', 'penyerahan', 'kontrak/spk-barang.html', 'wajib dilengkapi dokumen sebagai berikut'],
            'SPK Barang — pembayaran (Pasal 5 ayat 1)' => ['SPK Barang', 'pembayaran', 'kontrak/spk-barang.html', 'CARA PEMBAYARAN'],
            'SPK Jasa — penyerahan (Pasal 6 ayat 8)' => ['SPK Jasa', 'penyerahan', 'kontrak/spk-jasa.html', 'dengan dilengkapi dokumen sebagai berikut'],
            'SPK Jasa — pembayaran (Pasal 5 ayat 1)' => ['SPK Jasa', 'pembayaran', 'kontrak/spk-jasa.html', 'CARA PEMBAYARAN'],
            'Surat Perjanjian — penyerahan (Pasal 5 ayat 5)' => ['Surat Perjanjian', 'penyerahan', 'pj/surat-perjanjian.html', 'Penyerahan barang dimaksud harus dilengkapi dengan'],
            'Surat Perjanjian — pembayaran (Pasal 10 ayat 1)' => ['Surat Perjanjian', 'pembayaran', 'pj/surat-perjanjian.html', 'Pasal 10'],
            'Lampiran SP — pembayaran (Bagian D butir 1)' => ['Lampiran Surat Pesanan', 'pembayaran', 'sppl/lampiran-sp.html', 'Bon Penerimaan Barang dengan melampirkan'],
        ];
    }

    #[DataProvider('lists')]
    public function test_the_requirements_follow_the_template_word_for_word(string $source, string $section, string $file, string $marker): void
    {
        $template = $this->listAfter(resource_path('document-templates/'.$file), $marker);

        $requirements = array_map(
            fn (array $item): string => self::REWORDED[$item[0]] ?? $item[0],
            FinanceRequirementSeeder::SOURCES[$source]['items'][$section],
        );

        $this->assertSame($template, $requirements);
    }

    /**
     * The Lampiran SP states its delivery papers in sentences rather than a
     * list (bagian B butir 3–4); each requirement must be named there.
     */
    public function test_the_surat_pesanan_delivery_papers_are_named_in_its_appendix(): void
    {
        $body = $this->plain((string) file_get_contents(resource_path('document-templates/sppl/lampiran-sp.html')));

        foreach ([
            'Surat Jalan Barang / Surat Serah Terima Pekerjaan dalam 2 (dua) rangkap',
            'copy Surat Pesanan',
            'Asli Surat Keterangan Barang',
            '(COO / COM / COC / COA)',
            'Working Permit / Safety Clearance',
            'Laporan Pekerjaan',
            'Dokumentasi pelaksanaan pekerjaan',
        ] as $phrase) {
            $this->assertStringContainsString($phrase, $body);
        }

        $this->assertCount(6, FinanceRequirementSeeder::SOURCES['Lampiran Surat Pesanan']['items']['penyerahan']);
    }

    /**
     * Whether every requirement marked optional is one the contract makes
     * conditional, and the other way round.
     */
    public function test_only_conditional_requirements_are_optional(): void
    {
        foreach (FinanceRequirementSeeder::SOURCES as $source => $definition) {
            foreach ($definition['items'] as $items) {
                foreach ($items as [$name, $isOptional]) {
                    $conditional = (bool) preg_match('/\((apabila ada|jika ada|untuk [^)]+)\)$/i', $name);

                    $this->assertSame($conditional, $isOptional, "{$source}: {$name}");
                }
            }
        }
    }

    /**
     * The top-level items of the first list after a marker, as plain text.
     *
     * @return array<int, string>
     */
    private function listAfter(string $path, string $marker): array
    {
        $body = (string) file_get_contents($path);
        $start = mb_stripos($body, $marker);

        $this->assertNotFalse($start, "\"{$marker}\" tidak ditemukan di {$path}");

        preg_match('/<ol[^>]*>(.*?)<\/ol>/is', mb_substr($body, (int) $start), $list);
        $this->assertNotEmpty($list, "Daftar setelah \"{$marker}\" tidak ditemukan");

        $topLevel = (string) preg_replace('/<ol[^>]*>.*?<\/ol>/is', '', $list[1]);
        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $topLevel, $items);

        return array_map(
            // Item punctuation is not part of the requirement.
            fn (string $item): string => rtrim($this->plain($item), ' ;.'),
            $items[1],
        );
    }

    private function plain(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
