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
     * The company name and address at the top right of the BA and SP letterheads.
     */
    public const COMPANY_HEADING = '<div style="font-weight: bold; font-size: 13pt; line-height: 1.2;">PT. PLN Nusantara Power UP Kendari</div>'
        .'<div style="font-size: 8pt; margin-top: 2px;">Jl. Chairil Anwar No.2, Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara 93117</div>';

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
        $heading = self::COMPANY_HEADING;

        return <<<HTML
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
                        {$heading}
                        <table style="display: inline-table; margin-top: 6px; width: auto; border: none; border-collapse: collapse; font-size: 9pt; text-align: left;">
                            <tr>
                                <td style="border: none; padding: 2px 4px 2px 0;">Tanggal</td>
                                <td style="border: none; padding: 2px 4px;">:</td>
                                <td style="border: none; padding: 2px 0;">{{tanggal_mulai_pelaksanaan}}</td>
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
                        <td colspan="4" style="border: 1px solid #000; padding: 4px 4px; text-align: center;">TOTAL HARGA</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: center;">{{nilai_hpe_angka}}</td>
                        <td colspan="2" style="border: 1px solid #000; padding: 4px 4px; text-align: center;">{{nilai_setelah_nego_angka}}</td>
                    </tr>
                </tbody>
            </table>

            <p style="margin: -14px 0 0 0; font-size: 8pt;">*Harga belum termasuk PPN</p>

            <table style="width: 100%; max-width: 100%; border: none; border-collapse: collapse; margin-top: 32px; font-size: 9.5pt; page-break-inside: avoid;">
                <tr>
                    <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                        {{nama_mitra}}<br>
                        DIREKTUR<br><br><br><br><br><br>
                        {{nama_direktur}}
                    </td>
                    <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                        PLN NP UP KENDARI<br>
                        TL PELAKSANA PENGADAAN<br><br><br><br><br><br>
                        {{pic_pelaksana}}
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

        $heading = self::COMPANY_HEADING;

        return <<<HTML
        <section style="font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; line-height: 1.3; color: #000;">
            <table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 10px;">
                <tr>
                    <td style="width: 35%; border: none; padding: 0; vertical-align: middle;">
                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 38px; width: auto; display: block;">
                    </td>
                    <td style="width: 65%; border: none; padding: 0; vertical-align: middle; text-align: right;">
                        {$heading}
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
                        <td style="padding: 1.5px 0; border: none;">{{tanggal_mulai_pelaksanaan}}</td>
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
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 6%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">NOMOR</th>
                        <th style="border: 1px solid #000; padding: 6px 6px; width: 33%; vertical-align: middle;">{$colItemHeader}</th>
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 8%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">VOLUME</th>
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 8%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 14%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">HARGA SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 15%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">JUMLAH HARGA</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 16%; vertical-align: middle; font-size: 7.5pt; line-height: 1.25;">{$colDeadlineHeader}</th>
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
                        <td colspan="7" style="border: 1px solid #000; padding: 6px 8px; font-size: 8.5pt;">
                            <b>NOTE :</b> {{nomor_prk}}
                        </td>
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
     * Syarat Umum Surat Pesanan (Lampiran SP Barang / Jasa) - 2 Halaman.
     *
     * Barang and Jasa share one narrative written for "barang / pekerjaan",
     * following the UP Kendari paper form; the kind only names the copy.
     */
    public function syaratUmum(string $kind): string
    {
        $li = 'style="margin-bottom: 1.5px;"';

        return <<<HTML
        <style>
            @page { size: A4 portrait; margin: 22mm 15mm 18mm 15mm; }
            .header-logo {
                position: fixed;
                top: -16mm;
                left: 0;
                right: 0;
                text-align: center;
                height: 14mm;
            }
        </style>
        <div class="header-logo">
            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 38px; width: auto; display: inline-block;">
        </div>
        <section style="font-family: Arial, Helvetica, sans-serif; font-size: 8pt; line-height: 1.3; color: #000;">
            <div style="text-align: center; margin-bottom: 8px;">
                <div style="font-weight: bold; font-size: 10pt; letter-spacing: 0.5px;">SYARAT UMUM</div>
                <div style="font-weight: bold; font-size: 8.5pt; margin-top: 2px;">Surat Pesanan (SP) Nomor {{nomor_pengadaan}} tanggal {{tanggal_dokumen}}</div>
            </div>

            <div style="margin-bottom: 6px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">A. UMUM</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li {$li}>Pembeli / Pemberi Pekerjaan (PIHAK PERTAMA) adalah PT PLN Nusantara Power UP Kendari dan Penyedia / Pelaksana Pekerjaan (PIHAK KEDUA) adalah {{nama_mitra}}</li>
                    <li {$li}>Para Pihak sepakat untuk mengadakan perjanjian sesuai Surat Pesanan ini.</li>
                    <li {$li}>Surat Pesanan ini berlaku sampai dengan Pelaksana Pekerjaan selesai melakukan penyerahan barang / pekerjaan yang ditentukan dalam Surat Pesanan ini.</li>
                    <li {$li}>Barang yang dijual / Pekerjaan yang dilaksanakan oleh Penyedia / Pelaksana Pekerjaan adalah barang / pekerjaan yang sah menurut hukum dan didukung oleh dokumen yang resmi, dalam arti kata bebas dari tuntutan Pihak lain dan penyitaan dari pihak yang berwajib.</li>
                    <li {$li}>Sebagai tanda persetujuan atas syarat Pembelian / Pelaksanaan pekerjaan pada halaman ini, agar Penyedia menandatangani Surat Pesanan ini di atas materai Rp. 10.000 dan menyerahkan kembali kepada Pembeli / Pemberi Pekerjaan.</li>
                    <li {$li}>Dalam proses pelaksanaan pengadaan ini, Penyedia wajib mentaati semua ketentuan-ketentuan yang disyaratkan dalam Sistem Manajemen Lingkungan, Sistem Manajemen K3, Sistem Manajemen Mutu, Sistem Manajemen Pengamanan, dan Sistem Manajemen Energi di PT PLN NUSANTARA POWER UP KENDARI.</li>
                </ol>
            </div>

            <div style="margin-bottom: 6px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">B. PENERIMAAN BARANG / PEKERJAAN</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li {$li}>Penerimaan barang / pekerjaan dilakukan setiap hari kerja mulai pukul 09.00 s/d 15.00 WIB.</li>
                    <li {$li}>Penyerahan barang / pekerjaan dilaksanakan di Gudang Pembeli / Pemberi Pekerjaan atau tempat lain yang ditunjuk.</li>
                    <li {$li}>Penyerahan barang / pekerjaan harus disertai Surat Jalan Barang / Surat Serah Terima Pekerjaan dalam 2 (dua) rangkap, diserahkan ke Gudang Pembeli / Pemberi Pekerjaan dengan melampirkan <i>copy</i> Surat Pesanan dan dokumen yang disyaratkan dalam Surat Pesanan ini.</li>
                    <li {$li}>Mutu barang / pekerjaan yang diserahkan harus dalam keadaan baik 100% baru dan bebas dari cacat. Baik yang kelihatan maupun tersembunyi. Penyerahan Barang harus dilengkapi dengan Asli Surat Keterangan Barang dan / atau dokumen lain yang disyaratkan dalam Surat Pesanan ini, yang mendukung keaslian barang (COO / COM / COC / COA) dan Penyerahan Pekerjaan harus dilengkapi dengan <i>Working Permit / Safety Clearance</i>, Laporan Pekerjaan, Dokumentasi pelaksanaan pekerjaan dan dokumen lain yang disyaratkan dalam surat pesanan ini.</li>
                </ol>
            </div>

            <div style="margin-bottom: 6px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">C. PEMERIKSAAN BARANG / PEKERJAAN</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li {$li}>Barang / pekerjaan yang diserahkan, diperiksa oleh Petugas Penerima Gudang Pemberi Pekerjaan berdasarkan Surat Pesanan.</li>
                    <li {$li}>Dalam hal barang / pekerjaan tersebut perlu diperiksa secara rinci, membutuhkan waktu pemeriksaan &plusmn; 2 (dua) minggu.</li>
                    <li {$li}>Apabila barang / pekerjaan ditolak, maka selambat paling lama 5 (lima) hari kalender dari tanggal penolakan Penyedia harus mengambilnya dengan biaya sendiri dan bila tidak diambil maka resiko kerusakan dan lain - lain ditanggung Penyedia.</li>
                    <li {$li}>Barang / pekerjaan dinyatakan diterima setelah diterbitkan Bon Penerimaan Barang / Berita Acara Penyelesaian Pekerjaan oleh Gudang Pemberi Pekerjaan.</li>
                    <li {$li}>Pemeriksaan dan pengesahan Bon Penerimaan Barang / Berita Acara Penyelesaian Pekerjaan dilaksanakan paling lambat 2 (dua) minggu setelah barang / pekerjaan diterima.</li>
                    <li {$li}>Apabila barang / pekerjaan yang diperiksa perlu pengetesan dan memakan waktu lebih dari 2 (dua) minggu, maka Penyedia wajib memberikan surat jaminan bahwa barang / pekerjaan yang dikirim / diserahkan dapat berfungsi dengan baik. Apabila ternyata dalam pelaksanaan pengetesan barang / pekerjaan tersebut tidak dapat berfungsi sebagaimana mestinya, maka Penyedia wajib mengganti dengan barang yang sejenis atau memperbaiki pekerjaan tersebut.</li>
                </ol>
            </div>

            <div style="page-break-before: always;"></div>

            <div style="margin-bottom: 6px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">D. PEMBAYARAN</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li {$li}>Pihak Penyedia wajib melakukan permintaan pembayaran yang diajukan kepada Pembeli / Pemberi Pekerjaan paling lambat tanggal 15 (lima belas) setiap bulan berjalan dengan batas waktu tanggal invoice maksimal 3 (tiga) bulan terhitung sejak tanggal penerbitan Berita Acara Penyelesaian Pekerjaan / Bon Penerimaan Barang dengan melampirkan :
                        <ol type="a" style="margin: 1px 0; padding-left: 16px; list-style-type: lower-alpha;">
                            <li {$li}>Surat Permohonan Pembayaran (nilai yang ditagihkan, nama dan nomor rekening bank harus sesuai kontrak, tanggal, tanda tangan, stempel dan materai).</li>
                            <li {$li}>Kwitansi tagihan bermaterai 3 (tiga) rangkap.</li>
                            <li {$li}>Asli Bon Penerimaan Barang (untuk pengadaan barang / barang dan jasa).</li>
                            <li {$li}>Asli Berita Acara Pemeriksaan Barang (untuk pengadaan barang / barang dan jasa).</li>
                            <li {$li}>Asli Berita Acara Penyelesaian Pekerjaan (untuk pekerjaan jasa / barang dan jasa).</li>
                            <li {$li}>Asli atau Copy Surat Serah Terima Pekerjaan (untuk pekerjaan jasa / barang dan jasa).</li>
                            <li {$li}>Asli Safety Clearence (untuk pekerjaan jasa / barang dan jasa).</li>
                            <li {$li}>Asli atau Copy Surat Pesanan.</li>
                            <li {$li}>Copy Surat Keputusan Pengukuhan Pengusaha Kena Pajak (PKP) dan Copy Nomor Pokok Wajib Pajak (NPWP) masing-masing 1 (satu) rangkap.</li>
                            <li {$li}>Faktur Pajak (e-Faktur), 1 (satu) rangkap diterbitkan setelah ada konfirmasi dari bag pengadaan.</li>
                            <li {$li}>E-Faktur Pajak Pengganti / Revisi 4 (empat) rangkap (apabila ada).</li>
                            <li {$li}>Surat Keterangan Bebas Potong PPh 21, PPh 22, PPh 23 dan PPH Pasal 4 ayat 2 (apabila ada).</li>
                            <li {$li}>Surat Pernyataan Garansi.</li>
                            <li {$li}>BA Keterlambatan (apabila ada);</li>
                            <li {$li}>Salinan Addendum Pengadaan Barang / Jasa (apabila ada).</li>
                        </ol>
                    </li>
                    <li {$li}>Pembayaran dari Pihak Penyedia kepada Pihak Pemberi Pekerjaan dilakukan dengan pemindah bukuan / transfer setiap hari kerja melalui :
                        <div style="padding-left: 12px; margin-top: 1px;">
                            <span style="display: inline-block; width: 110px;">a. Bank</span>: {{nama_bank}}<br>
                            <span style="display: inline-block; width: 110px;">b. Nomor Rekening</span>: {{nomor_rekening}}<br>
                            <span style="display: inline-block; width: 110px;">c. Atas Nama</span>: {{nama_pemilik_rekening}}
                        </div>
                    </li>
                </ol>
            </div>

            <div style="margin-bottom: 6px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">E. SANKSI KETERLAMBATAN</div>
                <ol style="margin: 0; padding-left: 18px; text-align: justify;">
                    <li {$li}>Dalam hal terjadi keterlambatan penyerahan barang / pekerjaan sesuai waktu yang telah ditentukan, maka Penyedia dikenakan denda keterlambatan sebesar 1&permil; per-hari kalender dari nilai Surat Pesanan dengan batas maksimum denda keterlambatan sebesar 5% dari nilai Surat Pesanan, kecuali Force Majeure.</li>
                    <li {$li}>Apabila terjadi keterlambatan permintaan pembayaran yang mengakibatkan Pembeli / Pemberi Pekerjaan terkena denda atas PPN terutang, Maka beban denda atas PPN terutang tersebut dibebankan kepada Pihak Penyedia.</li>
                    <li {$li}>Apabila terjadi keterlambatan penyerahan barang / pekerjaan. Penyedia harus memberitahukan kepada Pembeli / Pemberi Pekerjaan selambat-lambatnya 7 (tujuh) hari kalender terhitung sejak berakhirnya batas waktu penyerahan barang / pekerjaan dan mengajukan perpanjangan waktu penyerahan barang / pekerjaan.</li>
                    <li {$li}>Apabila dalam waktu sebagaimana dimaksud dalam butir 3 tersebut diatas Penyedia Pekerjaan tidak memberitahukan kepada Pembeli / Pemberi Pekerjaan, Pembeli / Pemberi Pekerjaan dapat memutuskan Surat Pesanan ini secara sepihak serta Penyedia dikenakan sanksi tidak diperbolehkan untuk mengikuti pengadaan barang / jasa di wilayah kerja PT PLN Nusantara Power selama minimum 1 (satu) tahun sejak tanggal pemutusan Surat Pesanan. Pelaksanaan Pemutusan Surat Pesanan akan dilakukan secara tertulis dari Pihak Pembeli / Pemberi Pekerjaan kepada Pihak Penyedia.</li>
                    <li {$li}>Dalam hal terjadi pemutusan Surat Pesanan secara sepihak, maka Pembeli / Pemberi Pekerjaan dan Penyedia Pekerjaan sepakat untuk tidak memberlakukan ketentuan Pasal 1266 dan Pasal 1267 Kitab Undang-Undang Hukum Perdata.</li>
                    <li {$li}>Dalam hal terjadi Force Majeure, maka Penyedia harus melaporkan kepada Pembeli / Pemberi Pekerjaan selambat-lambatnya 7 (tujuh) hari kalender sejak terjadinya Force Majeure. Apabila dalam waktu tersebut Penyedia tidak melaporkan, maka Force Majeure dianggap tidak ada dan Penyedia dikenakan sanksi keterlambatan sesuai ketentuan butir 1.</li>
                </ol>
            </div>

            <div style="margin-bottom: 6px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">F. KEPATUHAN TERHADAP PERATURAN DAN KEBIJAKAN ANTI PENYUAPAN</div>
                <div style="padding-left: 18px; text-align: justify;">PARA PIHAK menyepakati bahwa pada saat melaksanakan Perjanjian ini berdasarkan pada prinsip itikad baik, tidak saling mempengaruhi baik langsung maupun tidak langsung, menerima serta bertanggung jawab atas segala keputusan yang ditetapkan sesuai dengan kesepakatan PARA PIHAK, menghindari serta mencegah terjadinya pertentangan kepentingan (<i>conflict of interest</i>), menghindari dan mencegah penyalahgunaan wewenang dan/atau kolusi dengan tujuan untuk keuntungan pribadi-golongan-atau pihak lain, dan tidak menerima, tidak menawarkan atau tidak menjanjikan untuk memberi atau menerima hadiah, imbalan berupa apa saja kepada siapapun yang diketahui atau patut diduga berkaitan dengan pelaksanaan Perjanjian ini serta tidak akan melakukan tindakan-tindakan yang menyebabkan terjadinya ketidakwajaran dalam pelaksanaan Perjanjian ini termasuk namun tidak terbatas pada tindakan Penipuan, Penyuapan, Kolusi, dan Korupsi.</div>
            </div>

            <div style="margin-bottom: 6px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">G. LAIN-LAIN</div>
                <div style="padding-left: 18px; text-align: justify;">Hal-hal lain yang belum diatur dalam syarat umum Surat Pesanan ini akan diatur tersendiri.</div>
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
