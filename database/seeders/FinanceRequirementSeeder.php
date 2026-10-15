<?php

namespace Database\Seeders;

use App\Enums\FinanceSection;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The Keuangan checklist: what the partner hands over (Penyerahan) and what a
 * payment request carries (Pembayaran), taken word for word from the delivery
 * and payment articles of each contract template:
 *
 * - SPK Barang: Pasal 6 ayat (7) and Pasal 5 ayat (1)
 * - SPK Jasa: Pasal 6 ayat (8) and Pasal 5 ayat (1)
 * - Surat Perjanjian (PJ): Pasal 5 ayat (5) and Pasal 10 ayat (1)
 * - Lampiran Surat Pesanan (SPPL): bagian B butir 3–4 and bagian D butir 1
 *
 * Each requirement applies to its own contract format (and goods/services
 * category), the rest being excluded. The rows are ordinary master data, so
 * an administrator maintains them on Data Master → Item Checklist; only
 * missing rows are added when this runs again.
 */
class FinanceRequirementSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The requirements per source: format code, work category, the article
     * of each section, and each section's items as [name, optional].
     *
     * @var array<string, array{format: string, category: string|null, sources: array<string, string>, items: array<string, array<int, array{0: string, 1: bool}>>}>
     */
    public const SOURCES = [
        'SPK Barang' => [
            'format' => 'SPK',
            'category' => 'barang',
            'sources' => ['penyerahan' => 'SPK Barang Pasal 6 ayat (7)', 'pembayaran' => 'SPK Barang Pasal 5 ayat (1)'],
            'items' => [
                'penyerahan' => [
                    ['Surat Jalan / Delivery Order', false],
                    ['Packing List', false],
                    ['Dokumen keaslian barang (COO / COM / COC / COA)', false],
                    ['Buku manual / petunjuk penggunaan (apabila ada)', true],
                    ['Surat Pernyataan Garansi Barang', false],
                    ['Salinan Surat Perintah Kerja', false],
                ],
                'pembayaran' => [
                    ['Permohonan Pembayaran', false],
                    ['Kwitansi tagihan dalam rangkap 3 (tiga) lembar pertama bermaterai 10.000', false],
                    ['Asli Bon Penerimaan Barang', false],
                    ['Asli Berita Acara Pemeriksaan Barang', false],
                    ['Asli atau Salinan Surat Jalan / Delivery Order', false],
                    ['Salinan Surat Perintah Kerja', false],
                    ['Salinan Surat Keputusan Pengukuhan Pengusaha Kena Pajak (PKP)', false],
                    ['Salinan Nomor Pokok Wajib Pajak (NPWP)', false],
                    ['E-Faktur Pajak 4 (empat) rangkap', false],
                    ['E-Faktur Pajak Pengganti/Revisi 4 (empat) rangkap (apabila ada)', true],
                    ['Surat Keterangan Bebas Potong PPh 21, PPh 22, PPh 23 dan PPH Pasal 4 ayat 2 (apabila ada)', true],
                    ['Surat Pernyataan Garansi Barang', false],
                    ['Salinan Addendum Pengadaan Barang/Jasa (apabila ada)', true],
                ],
            ],
        ],
        'SPK Jasa' => [
            'format' => 'SPK',
            'category' => 'jasa',
            'sources' => ['penyerahan' => 'SPK Jasa Pasal 6 ayat (8)', 'pembayaran' => 'SPK Jasa Pasal 5 ayat (1)'],
            'items' => [
                'penyerahan' => [
                    ['Asli Surat Penyerahan Pekerjaan', false],
                    ['Laporan Pekerjaan', false],
                    ['Dokumentasi Foto Pelaksanaan Pekerjaan', false],
                    ['Copy Safety Clearance atau Working Permit', false],
                    ['Surat Pernyataan Garansi Pekerjaan', false],
                    ['Sertifikat CSMS dengan klasifikasi minimal Moderat', false],
                    ['Salinan Surat Perintah Kerja', false],
                ],
                'pembayaran' => [
                    ['Permohonan Pembayaran', false],
                    ['Kwitansi tagihan dalam rangkap 3 (tiga) lembar pertama bermaterai 10.000', false],
                    ['Asli Berita Acara Penyelesaian Pekerjaan', false],
                    ['Asli atau Salinan Surat Penyerahan Pekerjaan', false],
                    ['Salinan Surat Perintah Kerja', false],
                    ['Asli Safety Clearence', false],
                    ['Salinan Surat Keputusan Pengukuhan Pengusaha Kena Pajak (PKP)', false],
                    ['Salinan Nomor Pokok Wajib Pajak (NPWP)', false],
                    ['E-Faktur Pajak 4 (empat) rangkap', false],
                    ['E-Faktur Pajak Pengganti/Revisi 4 (empat) rangkap (apabila ada)', true],
                    ['Surat Keterangan Bebas Potong PPh 21, PPh 22, PPh 23 dan PPH Pasal 4 ayat 2 (apabila ada)', true],
                    ['Salinan Addendum Pengadaan Barang/Jasa (apabila ada)', true],
                ],
            ],
        ],
        'Surat Perjanjian' => [
            'format' => 'PJ',
            'category' => null,
            'sources' => ['penyerahan' => 'Surat Perjanjian Pasal 5 ayat (5)', 'pembayaran' => 'Surat Perjanjian Pasal 10 ayat (1)'],
            'items' => [
                'penyerahan' => [
                    ['Asli Surat Keterangan Asal Barang bermaterai Rp 10.000,- (TTE tersertifikasi dan e-meterai)', false],
                    ['Copy Surat Asal Barang / Packing List / Air Waybill', false],
                    ['Asli Surat Jalan Pengiriman/DO', false],
                    ['Surat dukungan manufacture', false],
                    ['Asli Certificate of manufacture (COM) dan atau Certificate of origin (COO)', false],
                    ['Brosur/katalog barang', false],
                    // The months are the procurement's own, filled into the contract.
                    ['Surat Garansi Barang bermaterai selama masa garansi (TTE tersertifikasi dan e-meterai)', false],
                ],
                'pembayaran' => [
                    ['Surat permohonan pembayaran', false],
                    ['Invoice', false],
                    ['Kwitansi tagihan bermaterai Rp 10.000', false],
                    ['Bon Penerimaan Barang', false],
                    // The article's full wording, with "tanda tangan elektronik" shortened to TTE to fit a name.
                    ['Berita Acara Pemeriksaan Barang yang telah disetujui oleh PIHAK KESATU, termasuk rekomendasi jumlah hari keterlambatan/pengurang pembayaran (jika ada) yang ter-create dari Sistem Pemeriksaan Pekerjaan Secara Digital dan sudah dilengkapi TTE tersertifikasi', false],
                    ['E-Faktur Pajak', false],
                    ['Surat keterangan bebas potong (jika ada)', true],
                    ['Copy Jaminan Masa Garansi/Jaminan Pemeliharaan', false],
                ],
            ],
        ],
        'Lampiran Surat Pesanan' => [
            'format' => 'SPPL',
            'category' => null,
            'sources' => ['penyerahan' => 'Lampiran SP Bagian B butir 3–4', 'pembayaran' => 'Lampiran SP Bagian D butir 1'],
            'items' => [
                'penyerahan' => [
                    ['Surat Jalan Barang / Surat Serah Terima Pekerjaan 2 (dua) rangkap', false],
                    ['Copy Surat Pesanan', false],
                    ['Asli Surat Keterangan Barang / dokumen keaslian barang (COO / COM / COC / COA) (untuk barang)', true],
                    ['Working Permit / Safety Clearance (untuk pekerjaan jasa)', true],
                    ['Laporan Pekerjaan (untuk pekerjaan jasa)', true],
                    ['Dokumentasi pelaksanaan pekerjaan (untuk pekerjaan jasa)', true],
                ],
                'pembayaran' => [
                    ['Surat Permohonan Pembayaran (nilai yang ditagihkan, nama dan nomor rekening bank harus sesuai kontrak, tanggal, tanda tangan, stempel dan materai)', false],
                    ['Kwitansi tagihan bermaterai 3 (tiga) rangkap', false],
                    ['Asli Bon Penerimaan Barang (untuk pengadaan barang / barang dan jasa)', true],
                    ['Asli Berita Acara Pemeriksaan Barang (untuk pengadaan barang / barang dan jasa)', true],
                    ['Asli Berita Acara Penyelesaian Pekerjaan (untuk pekerjaan jasa / barang dan jasa)', true],
                    ['Asli atau Copy Surat Serah Terima Pekerjaan (untuk pekerjaan jasa / barang dan jasa)', true],
                    ['Asli Safety Clearence (untuk pekerjaan jasa / barang dan jasa)', true],
                    ['Asli atau Copy Surat Pesanan', false],
                    ['Copy Surat Keputusan Pengukuhan Pengusaha Kena Pajak (PKP) dan Copy Nomor Pokok Wajib Pajak (NPWP) masing-masing 1 (satu) rangkap', false],
                    ['Faktur Pajak (e-Faktur), 1 (satu) rangkap diterbitkan setelah ada konfirmasi dari bag pengadaan', false],
                    ['E-Faktur Pajak Pengganti / Revisi 4 (empat) rangkap (apabila ada)', true],
                    ['Surat Keterangan Bebas Potong PPh 21, PPh 22, PPh 23 dan PPH Pasal 4 ayat 2 (apabila ada)', true],
                    ['Surat Pernyataan Garansi', false],
                    ['BA Keterlambatan (apabila ada)', true],
                    ['Salinan Addendum Pengadaan Barang / Jasa (apabila ada)', true],
                ],
            ],
        ],
    ];

    /**
     * Add the missing requirement rows.
     */
    public function run(): void
    {
        $formats = ContractNumberFormat::query()->pluck('id', 'code');

        // Ordered per section, source after source, so each list reads as
        // the article it was taken from.
        $order = ['penyerahan' => 0, 'pembayaran' => 0];

        foreach (self::SOURCES as $source) {
            $ownFormat = $formats[$source['format']] ?? null;

            // A requirement without its contract format would apply to every
            // procurement, so it waits until the format exists.
            if ($ownFormat === null) {
                continue;
            }

            foreach ($source['items'] as $section => $items) {
                foreach ($items as $index => [$name, $isOptional]) {
                    $sortOrder = ++$order[$section];
                    $description = $source['sources'][$section].' huruf '.chr(ord('a') + $index);

                    $item = ChecklistItem::query()->firstOrCreate(
                        [
                            'stage' => ProcurementStage::Keuangan->value,
                            'section' => FinanceSection::from($section)->value,
                            'name' => $name,
                            'description' => $description,
                        ],
                        [
                            'work_category' => $source['category'],
                            'is_optional' => $isOptional,
                            'sort_order' => $sortOrder,
                            'is_active' => true,
                        ],
                    );

                    // Only the contract it comes from asks for it.
                    if ($item->wasRecentlyCreated) {
                        $item->excludedContractNumberFormats()->sync(
                            $formats->reject(fn (mixed $id): bool => (int) $id === (int) $ownFormat)->values()->all(),
                        );
                    }
                }
            }
        }
    }
}
