<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Standard templates for the SPPL execution documents.
 *
 * The Surat Pesanan follows the negotiation: it carries the negotiated value,
 * the partner's bank account, the execution period and the warranty entered
 * on the execution steps, so it always agrees with the BA Negosiasi.
 *
 * Provides templates for:
 * 1. Berita Acara Negosiasi (SPPL) + Lampiran Harga Pembayaran Langsung
 * 2. Surat Pesanan (Barang) + Syarat Umum
 * 3. Surat Pesanan (Jasa) + Syarat Umum
 */
class SpplDocumentTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed or update templates for SPPL document types.
     */
    public function run(): void
    {
        $templates = [
            'ba-negosiasi-sppl' => ['Berita Acara Negosiasi (SPPL)', $this->baNegosiasi()],
            'purchase-order' => ['Purchase Order (PO)', $this->suratPesanan()],
            'surat-pesanan' => ['Surat Pesanan', $this->suratPesanan()],
            'surat-pesanan-barang' => ['Lampiran SP Barang', $this->syaratUmum('BARANG')],
            'surat-pesanan-jasa' => ['Lampiran SP Jasa', $this->syaratUmum('JASA')],
            'lampiran-sp-barang' => ['Lampiran SP Barang', $this->syaratUmum('BARANG')],
            'lampiran-sp-jasa' => ['Lampiran SP Jasa', $this->syaratUmum('JASA')],
        ];

        foreach ($templates as $code => [$name, $body]) {
            $type = DocumentType::query()->where('code', $code)->first();

            if ($type === null) {
                continue;
            }

            DocumentTemplate::query()->updateOrCreate(
                [
                    'document_type_id' => $type->id,
                    'procurement_method_id' => null,
                ],
                [
                    'version' => 1,
                    'name' => $name.' - Template Standar UP Kendari',
                    'body' => $body,
                    'placeholders' => $this->placeholdersIn($body),
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Berita Acara Negosiasi (Harga Pembayaran Langsung) - 1 Halaman Potret.
     */
    protected function baNegosiasi(): string
    {
        return <<<'HTML'
        <section style="font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.35; color: #000;">
            <style>
                @page { size: A4 landscape; margin: 15mm 20mm; }
            </style>
            <table style="width: 100%; max-width: 100%; border: none; border-collapse: collapse; margin-bottom: 12px;">
                <tr>
                    <td style="width: 50%; border: none; padding: 0; vertical-align: top;">
                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 38px; width: auto; display: block;">
                        <div style="font-weight: bold; font-size: 10.5pt; margin-top: 6px; letter-spacing: 0.5px;">UP KENDARI</div>
                    </td>
                    <td style="width: 50%; border: none; padding: 0; vertical-align: top; text-align: right;">
                        <table style="display: inline-table; width: auto; border: none; border-collapse: collapse; font-size: 9pt; text-align: left;">
                            <tr>
                                <td style="border: none; padding: 2px 4px 2px 0;">Tanggal</td>
                                <td style="border: none; padding: 2px 4px;">:</td>
                                <td style="border: none; padding: 2px 0;">{{tanggal_dokumen}}</td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 2px 4px 2px 0;">Perusahaan</td>
                                <td style="border: none; padding: 2px 4px;">:</td>
                                <td style="border: none; padding: 2px 0; font-weight: bold;">{{nama_mitra}}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div style="text-align: center; margin: 14px 0 12px 0;">
                <h2 style="font-size: 11.5pt; font-weight: bold; margin: 0; text-transform: uppercase; border: none; letter-spacing: 0.5px;">HARGA PEMBAYARAN LANGSUNG</h2>
            </div>

            <table style="width: 100%; max-width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 20px; font-size: 8pt; border: 1px solid #000; word-break: break-word;">
                <thead>
                    <tr style="text-align: center; font-weight: bold;">
                        <th rowspan="2" style="border: 1px solid #000; padding: 5px 2px; width: 5%; vertical-align: middle; text-align: center;">NO</th>
                        <th rowspan="2" style="border: 1px solid #000; padding: 5px 4px; width: 27%; vertical-align: middle; text-align: center;">NAMA BARANG/JASA</th>
                        <th rowspan="2" style="border: 1px solid #000; padding: 5px 2px; width: 6%; vertical-align: middle; text-align: center;">VOLUME</th>
                        <th rowspan="2" style="border: 1px solid #000; padding: 5px 2px; width: 6%; vertical-align: middle; text-align: center;">SATUAN</th>
                        <th colspan="2" style="border: 1px solid #000; padding: 5px 3px; width: 28%; vertical-align: middle; text-align: center;">HARGA SEBELUM NEGO</th>
                        <th colspan="2" style="border: 1px solid #000; padding: 5px 3px; width: 28%; vertical-align: middle; text-align: center;">HARGA SETELAH NEGO</th>
                    </tr>
                    <tr style="text-align: center; font-weight: bold;">
                        <th style="border: 1px solid #000; padding: 4px 2px; width: 14%; vertical-align: middle; text-align: center; font-size: 7.5pt;">HARGA SATUAN</th>
                        <th style="border: 1px solid #000; padding: 4px 2px; width: 14%; vertical-align: middle; text-align: center; font-size: 7.5pt;">JUMLAH HARGA</th>
                        <th style="border: 1px solid #000; padding: 4px 2px; width: 14%; vertical-align: middle; text-align: center; font-size: 7.5pt;">HARGA SATUAN</th>
                        <th style="border: 1px solid #000; padding: 4px 2px; width: 14%; vertical-align: middle; text-align: center; font-size: 7.5pt;">JUMLAH HARGA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px 2px; text-align: center;">1</td>
                        <td style="border: 1px solid #000; padding: 5px 4px;">{{nama_pengadaan}}</td>
                        <td style="border: 1px solid #000; padding: 5px 2px; text-align: center;">1</td>
                        <td style="border: 1px solid #000; padding: 5px 2px; text-align: center;">Lot</td>
                        <td style="border: 1px solid #000; padding: 5px 3px; text-align: right;">{{nilai_hpe_angka}}</td>
                        <td style="border: 1px solid #000; padding: 5px 3px; text-align: right;">{{nilai_hpe_angka}}</td>
                        <td style="border: 1px solid #000; padding: 5px 3px; text-align: right;">{{nilai_setelah_nego_angka}}</td>
                        <td style="border: 1px solid #000; padding: 5px 3px; text-align: right;">{{nilai_setelah_nego_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 4px 4px; text-align: center; font-weight: bold;">TOTAL HARGA</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_hpe_angka}}</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_setelah_nego_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 4px 4px; text-align: center; font-weight: bold;">DPP 11/12</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_hpe_dpp_angka}}</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_setelah_nego_dpp_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 4px 4px; text-align: center; font-weight: bold;">PPN 12%</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_hpe_ppn_angka}}</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_setelah_nego_ppn_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 4px 4px; text-align: center; font-weight: bold;">JUMLAH TOTAL</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_hpe_total_angka}}</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: right; font-weight: bold;">{{nilai_setelah_nego_total_angka}}</td>
                    </tr>
                </tbody>
            </table>

            <table style="width: 100%; max-width: 100%; border: none; border-collapse: collapse; margin-top: 36px; font-size: 9.5pt; page-break-inside: avoid;">
                <tr>
                    <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                        <b>{{nama_mitra}}</b><br>
                        <b>DIREKTUR</b><br><br><br><br><br><br>
                        <b>{{nama_direktur}}</b>
                    </td>
                    <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                        <b>PLN NP UP KENDARI</b><br>
                        <b>TL PELAKSANA PENGADAAN</b><br><br><br><br><br><br>
                        <b>{{pic_pelaksana}}</b>
                    </td>
                </tr>
            </table>
        </section>
        HTML;
    }

    /**
     * Surat Pesanan / Purchase Order (PO) - 1 Halaman.
     * Sesuai standar UP Kendari & sinkron dengan data hasil negosiasi.
     */
    protected function suratPesanan(?string $kind = null): string
    {
        $colItemHeader = match ($kind) {
            'BARANG' => 'NAMA BARANG SPESIFIKASI/<br>PART NUMBER',
            'JASA' => 'URAIAN PEKERJAAN JASA /<br>SPESIFIKASI',
            default => 'NAMA BARANG SPESIFIKASI/<br>PART NUMBER',
        };

        $colDeadlineHeader = match ($kind) {
            'BARANG' => 'BATAS WAKTU /<br>PENYERAHAN<br>BARANG',
            'JASA' => 'BATAS WAKTU /<br>PENYELESAIAN<br>JASA',
            default => 'BATAS WAKTU /<br>PENYERAHAN<br>BARANG / JASA',
        };

        return <<<HTML
        <section style="font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; line-height: 1.3; color: #000;">
            <table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 6px;">
                <tr>
                    <td style="border: none; padding: 0; vertical-align: top;">
                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 38px; width: auto; display: block;">
                    </td>
                </tr>
            </table>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; font-size: 13pt; margin-bottom: 4px; letter-spacing: 0.5px;">SURAT PESANAN</div>
                <table style="width: 100%; border: none; border-collapse: collapse; font-size: 9pt; font-weight: bold;">
                    <tr>
                        <td style="width: 85px; padding: 1.5px 0; border: none;">NOMOR</td>
                        <td style="width: 15px; padding: 1.5px 0; border: none;">:</td>
                        <td style="padding: 1.5px 0; border: none;">{{nomor_pengadaan}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0; border: none;">JUDUL</td>
                        <td style="padding: 1.5px 0; border: none;">:</td>
                        <td style="padding: 1.5px 0; border: none;">{{nama_pengadaan}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0; border: none;">TANGGAL</td>
                        <td style="padding: 1.5px 0; border: none;">:</td>
                        <td style="padding: 1.5px 0; border: none;">{{tanggal_dokumen}}</td>
                    </tr>
                </table>
            </div>

            <table style="width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 8.5pt; margin-bottom: 0;">
                <tr>
                    <td style="width: 58%; border: 1px solid #000; padding: 4px 6px; font-weight: bold; vertical-align: top;">
                        DOKUMEN YANG TERKAIT DENGAN SURAT PERINTAH KERJA INI
                    </td>
                    <td style="width: 42%; border: 1px solid #000; padding: 4px 6px; font-weight: bold; vertical-align: top;">
                        SUMBER DANA / KODE ANGGARAN : {{sumber_anggaran}}
                    </td>
                </tr>
                <tr>
                    <td style="border: 1px solid #000; padding: 6px 8px; vertical-align: top; line-height: 1.45;">
                        <div style="font-weight: bold;">1. NOTA DINAS</div>
                        <div style="padding-left: 8px;">
                            <span style="display: inline-block; width: 80px;">- NOMOR</span>: {{nomor_nota_dinas_manager}}<br>
                            <span style="display: inline-block; width: 80px;">- TANGGAL</span>: {{tanggal_nota_dinas_manager}}
                        </div>
                        <div style="font-weight: bold; margin-top: 4px;">2. SURAT PENAWARAN</div>
                        <div style="padding-left: 8px;">
                            <span style="display: inline-block; width: 80px;">- NOMOR</span>: {{nomor_surat_penawaran}}<br>
                            <span style="display: inline-block; width: 80px;">- TANGGAL</span>: {{tanggal_surat_penawaran}}
                        </div>
                    </td>
                    <td style="border: 1px solid #000; padding: 6px 8px; vertical-align: top; line-height: 1.45;">
                        <div style="font-weight: bold;">KEPADA</div>
                        <div style="font-weight: bold; margin-top: 4px;">{{nama_mitra}}</div>
                        <div>{{alamat_mitra}}</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="border: 1px solid #000; padding: 5px 6px; font-weight: bold;">
                        <span style="display: inline-block; width: 340px;">TEMPAT PENYERAHAN BARANG / PEKERJAAN :</span>
                        <span>{{unit_tujuan}}</span>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; border-collapse: collapse; border: 1.5px solid #000; border-top: none; font-size: 8.5pt; margin-top: 0; margin-bottom: 0;">
                <thead>
                    <tr style="text-align: center; font-weight: bold; background-color: #ffffff;">
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 6%; vertical-align: middle;">NOMOR</th>
                        <th style="border: 1px solid #000; padding: 6px 6px; width: 34%; vertical-align: middle;">{$colItemHeader}</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 7%; vertical-align: middle;">VOLUME</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 7%; vertical-align: middle;">SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 6px; width: 15%; vertical-align: middle;">HARGA SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 6px; width: 15%; vertical-align: middle;">JUMLAH HARGA</th>
                        <th style="border: 1px solid #000; padding: 6px 6px; width: 16%; vertical-align: middle;">{$colDeadlineHeader}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1px solid #000; padding: 8px 4px; text-align: center; vertical-align: middle;">1</td>
                        <td style="border: 1px solid #000; padding: 8px 6px; vertical-align: middle;">{{nama_pengadaan}}</td>
                        <td style="border: 1px solid #000; padding: 8px 4px; text-align: center; vertical-align: middle;">1</td>
                        <td style="border: 1px solid #000; padding: 8px 4px; text-align: center; vertical-align: middle;">Lot</td>
                        <td style="border: 1px solid #000; padding: 8px 6px; text-align: right; vertical-align: middle;">Rp {{nilai_setelah_nego_angka}}</td>
                        <td style="border: 1px solid #000; padding: 8px 6px; text-align: right; vertical-align: middle;">Rp {{nilai_setelah_nego_angka}}</td>
                        <td style="border: 1px solid #000; padding: 8px 6px; text-align: center; vertical-align: middle; font-weight: bold;">{{tanggal_selesai_pelaksanaan}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 6px 8px; vertical-align: middle; font-size: 8pt; line-height: 1.35;">
                            <b>PERHATIAN :</b><br>
                            Harga adalah sebelum Pajak Pertambahan Nilai (PPN)
                        </td>
                        <td style="border: 1px solid #000; padding: 6px 8px; text-align: center; vertical-align: middle; font-weight: bold;">TOTAL</td>
                        <td style="border: 1px solid #000; padding: 6px 8px; text-align: right; vertical-align: middle; font-weight: bold;">Rp {{nilai_setelah_nego_angka}}</td>
                        <td style="border: 1px solid #000; padding: 6px 8px; background-color: #fafafa;"></td>
                    </tr>
                    <tr>
                        <td colspan="7" style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; font-size: 8.5pt;">
                            Terbilang : <span style="font-weight: normal; font-style: italic;">{{nilai_setelah_nego_terbilang}}</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table style="width: 100%; border-collapse: collapse; border: 1.5px solid #000; border-top: 1px solid #000; font-size: 9pt; page-break-inside: avoid; margin-top: 0; margin-bottom: 0;">
                <tr>
                    <td style="width: 50%; border: 1px solid #000; text-align: center; vertical-align: top; padding: 12px 8px;">
                        <div style="font-weight: bold;">{{nama_mitra}}</div>
                        <div style="font-weight: bold; margin-bottom: 75px;">DIREKTUR</div>
                        <div style="font-weight: bold;">{{nama_direktur}}</div>
                    </td>
                    <td style="width: 50%; border: 1px solid #000; text-align: center; vertical-align: top; padding: 12px 8px;">
                        <div style="font-weight: bold;">PT PLN NUSANTARA POWER UP KENDARI</div>
                        <div style="font-weight: bold; margin-bottom: 75px;">MANAGER</div>
                        <div style="font-weight: bold;">{{nama_manager}}</div>
                    </td>
                </tr>
            </table>
        </section>
        HTML;
    }

    /**
     * Syarat Umum Surat Pesanan (Lampiran SP Barang / Jasa) - 1 Halaman.
     * Sesuai standar UP Kendari.
     */
    protected function syaratUmum(string $kind): string
    {
        $isBarang = strtoupper($kind) === 'BARANG';

        $pasalB1 = $isBarang
            ? 'Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa barang yang dijual sesuai dengan spesifikasi yang ditetapkan, dalam kondisi baru, belum pernah dipakai, bisa berfungsi dengan baik, bebas dari cacat yang terlihat atau tersembunyi serta bebas dari pelanggaran atas kekayaan intelektual.'
            : 'Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa pekerjaan yang telah diselesaikan sesuai dengan spesifikasi yang ditetapkan dan dapat dipergunakan sebagaimana mestinya serta bebas dari pelanggaran atas kekayaan intelektual.';

        $pasalB2 = $isBarang
            ? 'Apabila barang tidak sesuai dengan spesifikasi yang ditetapkan, maka Pemberi Pekerjaan (PIHAK PERTAMA) berhak meminta penggantian.'
            : 'Apabila pekerjaan tidak sesuai dengan spesifikasi yang ditetapkan, maka Pemberi Pekerjaan (PIHAK PERTAMA) berhak meminta penggantian.';

        $pasalB3 = $isBarang
            ? 'Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa barang yang dijual bebas dari segala macam kerusakan sampai diterbitkannya Berita Acara Penerimaan Material yang dilengkapi Foto Dokumentasi.'
            : 'Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa pekerjaan yang telah diselesaikan bebas dari segala macam kerusakan sampai diterbitkannya Berita Acara Penerimaan jasa yang dilengkapi Foto Dokumentasi.';

        $pasalB4Lead = $isBarang
            ? 'Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa barang yang dijual bebas dari segala macam kerusakan selama {{masa_garansi_bulan}} ({{masa_garansi_bulan_terbilang}}) bulan (disebut sebagai Masa Garansi) terhitung sejak alat terpasang/digunakan atau sejak tanggal diterbitkannya Berita Acara Pemeriksaan Barang, kecuali:'
            : 'Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa barang yang terpasang dalam kondisi baik selama {{masa_garansi_bulan}} ({{masa_garansi_bulan_terbilang}}) bulan (disebut sebagai Masa Garansi) terhitung sejak alat terpasang/digunakan atau sejak tanggal diterbitkannya Berita Acara Serah Terima Pekerjaan, kecuali:';

        $pasalB6 = $isBarang
            ? '<li style="margin-bottom: 2px;">Pada waktu penyerahan barang pelaksanaan pekerjaan (PIHAK KEDUA) melengkapi dokumen material safety data sheet (MSDS) Atau COA.</li>'
            : '';

        $titleC = $isBarang
            ? 'C. PEMERIKSAAN DAN SERAH TERIMA BARANG'
            : 'C. PEMERIKSAAN DAN SERAH TERIMA JASA';

        $pasalC1 = $isBarang
            ? 'Setelah pekerjaan selesai dilaksanakan foto barang dan surat jalan yang akan digunakan sebagai dasar bagi Pelaksana Pekerjaan (PIHAK KEDUA) untuk melakukan penagihan kepada Pemberi Pekerjaan (PIHAK PERTAMA).'
            : 'Setelah pekerjaan selesai dilaksanakan foto pekerjaan dan Berita Acara Serah Terima Pekerjaan yang akan digunakan sebagai dasar bagi Pelaksana Pekerjaan (PIHAK KEDUA) untuk melakukan penagihan kepada Pemberi Pekerjaan (PIHAK PERTAMA).';

        $docTagihanB = $isBarang
            ? 'Kuitansi tagihan, 3 (tiga) rangkap (1 rangkap bermaterai)'
            : 'Kwitansi tagihan, 3 (tiga) rangkap (1 rangkap bermaterai)';

        $docTagihanD = $isBarang
            ? 'Faktur Pajak (e-Faktur), 1 (satu) rangkap disertakan setelah ada konfirmasi dari bag pengadaan;'
            : 'Faktur Pajak (e-Faktur), 1 (satu) rangkap, diterbitkan setelah ada konfirmasi dari bag pengadaan;';

        $docTagihanI = $isBarang
            ? 'Copy Surat Jalan'
            : 'Surat Pernyataan Garansi';

        $docTagihanExtra = $isBarang
            ? ''
            : '<li style="margin-bottom: 1px;">Entry permit / working permit</li>';

        $dendaTelat = $isBarang
            ? 'Apabila terjadi keterlambatan penyerahan barang sesuai waktu yang telah ditentukan dalam Surat Pesanan ini maka PIHAK KEDUA dikenakan denda keterlambatan sebesar 1 ‰ (satu per mil) per hari kalender dari nilai item barang yang terlambat dengan batas maksimum denda keterlambatan sebesar 5% (lima persen) dari nilai item barang yang terlambat.'
            : 'Apabila terjadi keterlambatan penyerahan pekerjaan sesuai waktu yang telah ditentukan dalam Surat Pesanan ini maka PIHAK KEDUA dikenakan denda keterlambatan sebesar 1 ‰ (satu per mil) per hari kalender dari nilai item pekerjaan yang terlambat dengan batas maksimum denda keterlambatan sebesar 5% (lima persen) dari nilai item pekerjaan yang terlambat.';

        $invoiceDeadline = $isBarang
            ? 'Penyerahan dokumen pembayaran dari PIHAK KEDUA kepada PIHAK PERTAMA paling lambat tanggal 15 (lima belas) setiap bulan berjalan dengan batas waktu tanggal invoice maksimal 3 (tiga) bulan terhitung sejak tanggal penerbitan Berita Acara Penyelesaian Pekerjaan / Bon Penerimaan Barang.'
            : 'Penyerahan dokumen pembayaran dari PIHAK KEDUA kepada PIHAK PERTAMA paling lambat tanggal 15 (lima belas) setiap bulan berjalan dengan batas waktu tanggal invoice maksimal 3 (tiga) bulan terhitung sejak tanggal penerbitan Berita Acara Penyelesaian Pekerjaan / Bon Penerimaan Barang/Jasa.';

        return <<<HTML
        <section style="font-family: Arial, Helvetica, sans-serif; font-size: 8pt; line-height: 1.25; color: #000;">
            <div style="text-align: center; margin-bottom: 6px;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 36px; width: auto; display: inline-block;">
            </div>

            <div style="text-align: center; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 10.5pt; text-decoration: underline; letter-spacing: 0.5px;">SYARAT UMUM</div>
                <div style="font-weight: bold; font-size: 9pt; margin-top: 3px;">Surat Pesanan (SP) Nomor {{nomor_pengadaan}} tanggal {{tanggal_dokumen}}</div>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">A. UMUM</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li style="margin-bottom: 2px;">Pemberi Pekerjaan (PIHAK PERTAMA) adalah PT PLN NUSANTARA POWER UPDK KENDARI dan Pelaksana Pekerjaan (PIHAK KEDUA) adalah {{nama_mitra}}</li>
                    <li style="margin-bottom: 2px;">Surat Pesanan (SP) ini berlaku sampai dengan batas waktu berakhir, kecuali ditentukan lain sesuai kesepakatan Pemberi Pekerjaan (PIHAK PERTAMA) dan Pelaksana Pekerjaan (PIHAK KEDUA).</li>
                    <li style="margin-bottom: 2px;">Pekerjaan yang dilaksanakan/barang yang dijual oleh Pelaksana Pekerjaan (PIHAK KEDUA) adalah pekerjaan/barang yang sah menurut hukum serta bebas dari tuntutan pihak lain dan penyitaan dari yang berwajib.</li>
                    <li style="margin-bottom: 2px;">Sebagai tanda persetujuan atas syarat pelaksanaan pekerjaan pada halaman ini, agar Pelaksana Pekerjaan (PIHAK KEDUA) menandatangani Surat Pesanan (SP) ini di atas materai Rp. 10.000,00 dan menyerahkan kembali kepada Pemberi Pekerjaan (PIHAK PERTAMA).</li>
                </ol>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">B. JAMINAN DAN KEWAJIBAN PELAKSANAAN PEKERJAAN (PIHAK KEDUA)</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li style="margin-bottom: 2px;">{$pasalB1}</li>
                    <li style="margin-bottom: 2px;">{$pasalB2}</li>
                    <li style="margin-bottom: 2px;">{$pasalB3}</li>
                    <li style="margin-bottom: 2px;">{$pasalB4Lead}
                        <ol type="a" style="margin: 2px 0; padding-left: 16px;">
                            <li style="margin-bottom: 1px;">Rusak atau cacat yang diakibatkan dari modifikasi oleh PIHAK PERTAMA terhadap hasil Pekerjaan sebagaimana dimaksud pada Surat Pesanan ini tanpa persetujuan terlebih dahulu dari PIHAK KEDUA;</li>
                            <li style="margin-bottom: 1px;">Rusak atau cacat yang diakibatkan dari pemasangan/perawatan/pengoperasian dan service yang dilakukan tidak menurut pedoman pengoperasian dan/atau buku-buku instruksi yang relevan dari PIHAK KEDUA/pabrikan;</li>
                            <li style="margin-bottom: 1px;">Rusak atau cacat yang timbul oleh sebab-sebab yang diakibatkan oleh PIHAK PERTAMA dan/atau pihak ketiga;</li>
                            <li style="margin-bottom: 1px;">Rusak atau cacat yang disebabkan oleh Force Majeure.</li>
                        </ol>
                    </li>
                    <li style="margin-bottom: 2px;">Kerusakan barang selama masa garansi menjadi tanggung - jawab Pelaksana Pekerjaan (PIHAK KEDUA).</li>
                    {$pasalB6}
                </ol>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">{$titleC}</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li style="margin-bottom: 2px;">{$pasalC1}</li>
                    <li style="margin-bottom: 2px;">Pekerjaan dinyatakan selesai setelah serah terima pekerjaan yang dibuktikan dengan foto pekerjaan dan ditandatanganinya Berita Acara Serah Terima Pekerjaan kedua belah pihak yang diterbitkan oleh Pelaksana Pekerjaan (PIHAK KEDUA).</li>
                </ol>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">D. PEMBAYARAN</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li style="margin-bottom: 2px;">Penagihan dapat dilakukan Pelaksana Pekerjaan (PIHAK KEDUA) dengan mengirimkan dokumen penagihan sebagai berikut :
                        <ol type="a" style="margin: 2px 0; padding-left: 16px; list-style-type: lower-alpha;">
                            <li style="margin-bottom: 1px;">Surat Permohonan Pembayaran ditujukan kepada Manager UP Kendari;</li>
                            <li style="margin-bottom: 1px;">{$docTagihanB}</li>
                            <li style="margin-bottom: 1px;">Invoice, 3 (tiga) rangkap</li>
                            <li style="margin-bottom: 1px;">{$docTagihanD}</li>
                            <li style="margin-bottom: 1px;">Berita Acara Serah Terima Pekerjaan</li>
                            <li style="margin-bottom: 1px;">BA Keterlambatan (jika ada);</li>
                            <li style="margin-bottom: 1px;">Copy Surat Pesanan (SP);</li>
                            <li style="margin-bottom: 1px;">Copy NPWP dan PKP, 1 (satu) rangkap;</li>
                            <li style="margin-bottom: 1px;">{$docTagihanI}</li>
                            {$docTagihanExtra}
                        </ol>
                    </li>
                    <li style="margin-bottom: 2px;">Pembayaran dari PIHAK PERTAMA kepada PIHAK KEDUA dilakukan dengan pemindahbukuan/transfer setiap hari kerja melalui :
                        <div style="padding-left: 12px; margin-top: 1px;">
                            <span style="display: inline-block; width: 130px;">a. Bank</span>: {{nama_bank}}<br>
                            <span style="display: inline-block; width: 130px;">b. Nomor Rekening</span>: {{nomor_rekening}}<br>
                            <span style="display: inline-block; width: 130px;">c. Atas Nama</span>: {{nama_pemilik_rekening}}
                        </div>
                    </li>
                    <li style="margin-bottom: 2px;">{$dendaTelat}</li>
                    <li style="margin-bottom: 2px;">{$invoiceDeadline}</li>
                    <li style="margin-bottom: 2px;">Apabila PIHAK KEDUA terlambat menyerahkan dokumen pembayaran mengakibatkan PPN tidak bisa dikreditkan oleh PIHAK PERTAMA maka PIHAK KEDUA akan dikenakan sanksi denda keterlambatan sesuai dengan point 3 Surat Pesanan ini.</li>
                </ol>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">E. PEMUTUSAN ATAU PEMBATALAN SURAT PESANAN (SP)</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li style="margin-bottom: 2px;">Pemberi Pekerjaan (PIHAK PERTAMA) dapat memutuskan atau membatalkan Surat Pesanan (SP) ini secara sepihak tanpa kewajiban apapun terhadap Pelaksana Pekerjaan (PIHAK KEDUA) apabila Pelaksana Pekerjaan (PIHAK KEDUA) terbukti melanggar ketentuan dalam Surat Pesanan (SP).</li>
                    <li style="margin-bottom: 2px;">Dalam hal terjadi pemutusan atau pembatalan Surat Pesanan (SP) sebagaimana dimaksud dalam huruf E angka 1 di atas, Pelaksana Pekerjaan (PIHAK KEDUA) akan dikenakan sanksi tidak diperbolehkan untuk mengikuti pengadaan barang/jasa di wilayah kerja Pemberi Pekerjaan (PIHAK PERTAMA) selama minimal 1 (satu) tahun terhitung sejak tanggal pemutusan atau pembatalan Surat Pesanan (SP). Pelaksanaan Pemutusan Surat Pesanan (SP) akan dilakukan secara tertulis dari Pemberi Pekerjaan (PIHAK PERTAMA) kepada Pelaksana Pekerjaan (PIHAK KEDUA).</li>
                    <li style="margin-bottom: 2px;">Dalam hal terjadi pemutusan atau pembatalan Surat Pesanan (SP) secara sepihak, maka Pemberi Pekerjaan (PIHAK PERTAMA) dan Pelaksana Pekerjaan (PIHAK KEDUA) sepakat untuk tidak memberlakukan ketentuan-ketentuan Pasal 1266 dan 1267 Kitab Undang-Undang Hukum Perdata.</li>
                </ol>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">F. PENYELESAIAN PERSELISIHAN</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li style="margin-bottom: 2px;">Apabila terjadi perselisihan pendapat dalam rangka pelaksanaan Surat Perintah Kerja ini maka, PARA PIHAK sepakat untuk menyelesaikan dengan musyawarah.</li>
                    <li style="margin-bottom: 2px;">Segala sengketa, pertentangan atau perselisihan yang terjadi dari atau sehubungan dengan Surat Perintah Kerja ini, atau pelanggarannya yang tidak dapat diselesaikan dengan cara negosiasi, mediasi atau konsolidasi, akan diselesaikan secara Arbitrase melalui Badan Arbitrase Nasional Indonesia (BANI) di Surabaya.</li>
                </ol>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">G. ASURANSI</div>
                <div style="text-align: justify;">Asuransi untuk barang/jasa menjadi tanggungan Pelaksana Pekerjaan (PIHAK KEDUA) dengan ketentuan yang berlaku di Pelaksana Pekerjaan.</div>
            </div>

            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">H. LAIN-LAIN</div>
                <div style="text-align: justify;">Hal-hal yang belum diatur dalam Surat Pesanan (SP) ini akan diatur tersendiri.</div>
            </div>
        </section>
        HTML;
    }

    /**
     * Surat Pesanan Barang (Syarat Umum).
     */
    protected function suratPesananBarang(): string
    {
        return $this->syaratUmum('BARANG');
    }

    /**
     * Surat Pesanan Jasa (Syarat Umum).
     */
    protected function suratPesananJasa(): string
    {
        return $this->syaratUmum('JASA');
    }

    /**
     * The placeholder keys a body refers to.
     *
     * @return array<int, string>
     */
    protected function placeholdersIn(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }
}
