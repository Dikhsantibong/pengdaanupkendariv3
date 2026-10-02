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
            'surat-pesanan-barang' => ['Surat Pesanan (Barang)', $this->suratPesananBarang()],
            'surat-pesanan-jasa' => ['Surat Pesanan (Jasa)', $this->suratPesananJasa()],
            'surat-pesanan' => ['Surat Pesanan', $this->suratPesananJasa()],
            'lampiran-sp-barang' => ['Lampiran Surat Pesanan - Barang', $this->lampiranLegacy('BARANG')],
            'lampiran-sp-jasa' => ['Lampiran Surat Pesanan - Jasa', $this->lampiranLegacy('JASA')],
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
     * Berita Acara Negosiasi + Lampiran Harga Pembayaran Langsung in SPPL format.
     */
    protected function baNegosiasi(): string
    {
        return <<<'HTML'
        <section>
            <table style="width:100%; border-collapse:collapse; border:none; margin-bottom:12px;">
                <tr>
                    <td style="border:none; padding:0; vertical-align:top; font-family:Arial, sans-serif; font-size:9pt; line-height:1.2;">
                        <b>PT PLN (PERSERO)<br>
                        UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI<br>
                        UP KENDARI<br>
                        JL. KAPTEN PIERE TENDEAN NO. 45 KENDARI SULAWESI TENGGARA</b>
                    </td>
                </tr>
            </table>

            <div style="text-align:center; margin-bottom:16px;">
                <h2 style="font-size:13pt; font-weight:bold; margin:0 0 4px 0; text-transform:uppercase; border:none;">BERITA ACARA NEGOSIASI HARGA</h2>
                <p style="margin:0; font-size:10pt;">Nomor : {{nomor_pengadaan}}</p>
            </div>

            <p style="text-align:justify; margin-bottom:8px;">Pada hari ini, tanggal {{tanggal_dokumen}}, bertempat di PT PLN (Persero) UP Kendari, telah dilaksanakan proses negosiasi harga pengadaan langsung untuk pekerjaan:</p>

            <table style="width:100%; border-collapse:collapse; margin-bottom:12px; font-size:10pt;">
                <tr><td style="width:28%; padding:4px 6px;">Nama Pekerjaan</td><td style="padding:4px 6px;"><b>{{nama_pengadaan}}</b></td></tr>
                <tr><td style="padding:4px 6px;">Nomor Pengadaan</td><td style="padding:4px 6px;">{{nomor_pengadaan}}</td></tr>
                <tr><td style="padding:4px 6px;">Pelaksana / Penyedia</td><td style="padding:4px 6px;">{{nama_mitra}}</td></tr>
                <tr><td style="padding:4px 6px;">Unit Tujuan</td><td style="padding:4px 6px;">{{unit_tujuan}}</td></tr>
                <tr><td style="padding:4px 6px;">Sumber Anggaran</td><td style="padding:4px 6px;">{{sumber_anggaran_keterangan}} ({{sumber_anggaran}})</td></tr>
            </table>

            <p style="margin-bottom:8px;">Hasil kesepakatan negosiasi harga adalah sebagai berikut:</p>

            <table style="width:100%; border-collapse:collapse; margin-bottom:12px; font-size:10pt;">
                <tr>
                    <th style="width:5%; text-align:center; padding:5px;">No</th>
                    <th style="padding:5px;">Uraian</th>
                    <th style="width:32%; text-align:right; padding:5px;">Nilai (Rp)</th>
                </tr>
                <tr>
                    <td style="text-align:center;">1</td>
                    <td>Harga Perkiraan Engineer (HPE) / Sebelum Negosiasi</td>
                    <td style="text-align:right;">{{nilai_hpe}}</td>
                </tr>
                <tr>
                    <td style="text-align:center;">2</td>
                    <td>Harga Hasil Negosiasi (Sebelum PPN)</td>
                    <td style="text-align:right;"><b>{{nilai_setelah_nego}}</b></td>
                </tr>
                <tr>
                    <td style="text-align:center;">3</td>
                    <td>PPN 12%</td>
                    <td style="text-align:right;">{{nilai_setelah_nego_ppn}}</td>
                </tr>
                <tr style="font-weight:bold; background-color:#f9fafb;">
                    <td colspan="2" style="text-align:right; padding:5px;">Total Kesepakatan (Termasuk PPN 12%)</td>
                    <td style="text-align:right; padding:5px;">{{nilai_setelah_nego_total}}</td>
                </tr>
                <tr>
                    <td colspan="3" style="padding:6px; font-style:italic;">
                        <b>Terbilang:</b> {{nilai_setelah_nego_total_terbilang}}
                    </td>
                </tr>
            </table>

            <p style="text-align:justify; margin-bottom:16px;">Rincian uraian, volume, dan harga satuan hasil negosiasi tercantum dalam Lampiran Berita Acara ini yang merupakan satu kesatuan tidak terpisahkan. Harga kesepakatan tersebut telah disetujui bersama dan dijadikan dasar dalam penerbitan Surat Pesanan.</p>

            <p style="margin-top:16px; margin-bottom:8px;">Kendari, {{tanggal_dokumen}}</p>
            <table style="width:100%; border-collapse:collapse; border:none; margin-top:12px; page-break-inside:avoid;">
                <tr>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        <b>Penyedia / Pelaksana</b><br>
                        {{nama_mitra}}<br><br><br><br>
                        <u><b>{{nama_pemilik_rekening}}</b></u><br>
                        Direktur
                    </td>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        <b>PT PLN (PERSERO) UIKL SULAWESI<br>UP KENDARI<br>PEJABAT PELAKSANA PENGADAAN</b><br><br><br><br>
                        <u><b>{{pic_pelaksana}}</b></u><br>
                        Team Leader Pelaksana Pengadaan
                    </td>
                </tr>
            </table>
        </section>

        <div class="page-break" contenteditable="false"></div>

        <section class="lampiran">
            <table style="width:100%; border-collapse:collapse; border:none; margin-bottom:12px;">
                <tr>
                    <td style="border:none; padding:0; vertical-align:top; font-family:Arial, sans-serif; font-size:9pt; line-height:1.2;">
                        <b>PT PLN (PERSERO)<br>
                        UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI<br>
                        UP KENDARI<br>
                        JL. KAPTEN PIERE TENDEAN NO. 45 KENDARI SULAWESI TENGGARA</b>
                    </td>
                </tr>
            </table>

            <div style="text-align:center; margin-bottom:14px;">
                <h2 style="font-size:12pt; font-weight:bold; margin:0 0 2px 0; text-transform:uppercase; border:none;">LAMPIRAN BERITA ACARA NEGOSIASI</h2>
                <h3 style="font-size:11pt; font-weight:bold; margin:0 0 10px 0; text-transform:uppercase;">HARGA PEMBAYARAN LANGSUNG</h3>
            </div>

            <table style="width:100%; border-collapse:collapse; border:none; margin-bottom:10px; font-size:10pt;">
                <tr><td style="width:15%; border:none; padding:2px 0;">Nomor</td><td style="width:2%; border:none; padding:2px 0;">:</td><td style="border:none; padding:2px 0;">{{nomor_pengadaan}}</td></tr>
                <tr><td style="border:none; padding:2px 0;">Tanggal</td><td style="border:none; padding:2px 0;">:</td><td style="border:none; padding:2px 0;">{{tanggal_dokumen}}</td></tr>
                <tr><td style="border:none; padding:2px 0;">Pekerjaan</td><td style="border:none; padding:2px 0;">:</td><td style="border:none; padding:2px 0;">{{nama_pengadaan}}</td></tr>
            </table>

            <table style="width:100%; border-collapse:collapse; margin-bottom:8px; font-size:9.5pt;">
                <thead>
                    <tr style="background-color:#f2f2f2; text-align:center;">
                        <th style="width:4%; text-align:center; padding:4px;">No</th>
                        <th style="text-align:center; padding:4px;">URAIAN</th>
                        <th style="width:8%; text-align:center; padding:4px;">JUMLAH VOLUME</th>
                        <th style="width:8%; text-align:center; padding:4px;">SATUAN</th>
                        <th style="width:15%; text-align:center; padding:4px;">HARGA SEBELUM NEGO (Rp)</th>
                        <th style="width:15%; text-align:center; padding:4px;">HARGA SATUAN SETELAH NEGO (Rp)</th>
                        <th style="width:15%; text-align:center; padding:4px;">JUMLAH SETELAH NEGO (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align:center;">1</td>
                        <td>{{nama_pengadaan}}</td>
                        <td style="text-align:center;">1</td>
                        <td style="text-align:center;">Lot</td>
                        <td style="text-align:right;">{{nilai_hpe_angka}}</td>
                        <td style="text-align:right;">{{nilai_setelah_nego_angka}}</td>
                        <td style="text-align:right;">{{nilai_setelah_nego_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right; font-weight:bold; padding:4px 6px;">Sub Total</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_hpe_angka}}</td>
                        <td colspan="2" style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right; font-weight:bold; padding:4px 6px;">DPP (11/12)</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_hpe_dpp_angka}}</td>
                        <td colspan="2" style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_dpp_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right; font-weight:bold; padding:4px 6px;">PPN 12%</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_hpe_ppn_angka}}</td>
                        <td colspan="2" style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_ppn_angka}}</td>
                    </tr>
                    <tr style="background-color:#f9fafb;">
                        <td colspan="4" style="text-align:right; font-weight:bold; padding:4px 6px;">Jumlah Total</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_hpe_total_angka}}</td>
                        <td colspan="2" style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_total_angka}}</td>
                    </tr>
                    <tr>
                        <td colspan="7" style="padding:6px; font-style:italic;">
                            <b>Terbilang:</b> {{nilai_setelah_nego_total_terbilang}}
                        </td>
                    </tr>
                </tbody>
            </table>

            <table style="width:100%; border-collapse:collapse; border:none; margin-top:20px; page-break-inside:avoid;">
                <tr>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        <b>{{nama_mitra}}</b><br><br><br><br><br>
                        <u><b>{{nama_pemilik_rekening}}</b></u><br>
                        Direktur
                    </td>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        <b>PT PLN (PERSERO) UIKL SULAWESI<br>UP KENDARI<br>PEJABAT PELAKSANA PENGADAAN</b><br><br><br><br>
                        <u><b>{{pic_pelaksana}}</b></u><br>
                        Team Leader Pelaksana Pengadaan
                    </td>
                </tr>
            </table>
        </section>
        HTML;
    }

    /**
     * Surat Pesanan Barang (Page 1) + Syarat Umum Barang (Pages 2-3).
     */
    protected function suratPesananBarang(): string
    {
        return <<<'HTML'
        <section>
            <table style="width:100%; border-collapse:collapse; border:none; margin-bottom:12px;">
                <tr>
                    <td style="border:none; padding:0; vertical-align:top; font-family:Arial, sans-serif; font-size:9pt; line-height:1.2;">
                        <b>PT PLN (PERSERO)<br>
                        UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI<br>
                        UP KENDARI<br>
                        JL. KAPTEN PIERE TENDEAN NO. 45 KENDARI SULAWESI TENGGARA</b>
                    </td>
                </tr>
            </table>

            <div style="text-align:center; margin-bottom:12px;">
                <h2 style="font-size:13pt; font-weight:bold; margin:0 0 2px 0; text-transform:uppercase; border:none;">SURAT PESANAN</h2>
                <p style="margin:0; font-size:9.5pt;">Nomor : {{nomor_pengadaan}}</p>
                <p style="margin:0; font-size:9.5pt;">Tanggal : {{tanggal_dokumen}}</p>
                <p style="margin:0; font-size:9.5pt;">Lampiran : -</p>
            </div>

            <div style="margin-bottom:10px; font-size:10pt;">
                Kepada Yth.<br>
                <b>{{nama_mitra}}</b><br>
                Di Tempat
            </div>

            <p style="text-align:justify; margin-bottom:10px; font-size:10pt;">
                Berdasarkan Berita Acara Negosiasi Harga Nomor : {{nomor_pengadaan}} Tanggal {{tanggal_dokumen}} dengan ini kami sampaikan Surat Pesanan untuk melaksanakan pengadaan barang dengan ketentuan sebagai berikut :
            </p>

            <table style="width:100%; border-collapse:collapse; margin-bottom:8px; font-size:9.5pt;">
                <thead>
                    <tr style="background-color:#f2f2f2; text-align:center;">
                        <th style="width:4%; text-align:center; padding:4px;">No</th>
                        <th style="text-align:center; padding:4px;">URAIAN BARANG</th>
                        <th style="width:8%; text-align:center; padding:4px;">JUMLAH VOLUME</th>
                        <th style="width:8%; text-align:center; padding:4px;">SATUAN</th>
                        <th style="width:15%; text-align:center; padding:4px;">HARGA SATUAN (Rp)</th>
                        <th style="width:15%; text-align:center; padding:4px;">JUMLAH HARGA (Rp)</th>
                        <th style="width:18%; text-align:center; padding:4px;">BATAS WAKTU / PENYERAHAN BARANG</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align:center;">1</td>
                        <td>{{nama_pengadaan}}</td>
                        <td style="text-align:center;">1</td>
                        <td style="text-align:center;">Unit / Paket</td>
                        <td style="text-align:right;">{{nilai_setelah_nego_angka}}</td>
                        <td style="text-align:right;">{{nilai_setelah_nego_angka}}</td>
                        <td style="text-align:center;">{{tanggal_selesai_pelaksanaan}}</td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">Sub Total</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">DPP (11/12)</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_dpp_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">PPN 12%</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_ppn_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr style="background-color:#f9fafb;">
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">Jumlah Total</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_total_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr>
                        <td colspan="7" style="padding:6px; font-style:italic;">
                            <b>Terbilang:</b> {{nilai_setelah_nego_total_terbilang}}
                        </td>
                    </tr>
                </tbody>
            </table>

            <table style="width:100%; border-collapse:collapse; border:none; margin-top:20px; page-break-inside:avoid;">
                <tr>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        Pihak Kedua<br>
                        <b>{{nama_mitra}}</b><br><br><br><br><br>
                        <u><b>{{nama_pemilik_rekening}}</b></u><br>
                        Direktur
                    </td>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        Pihak Pertama<br>
                        <b>PT PLN (PERSERO) UIKL SULAWESI<br>UP KENDARI</b><br><br><br><br>
                        <u><b>{{direksi_pekerjaan}}</b></u><br>
                        Manager
                    </td>
                </tr>
            </table>
        </section>

        <div class="page-break" contenteditable="false"></div>

        <section>
            <div style="text-align:center; margin-bottom:14px;">
                <h2 style="font-size:12pt; font-weight:bold; margin:0; text-transform:uppercase; border:none;">SYARAT-SYARAT UMUM SURAT PESANAN (SP)</h2>
            </div>

            <div style="text-align:justify; font-size:10pt; line-height:1.4;">
                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">A. PENERIMAAN SURAT PESANAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Surat Pesanan ini ditandatangani oleh PIHAK KEDUA dan dikembalikan kepada PIHAK PERTAMA paling lambat 2 (dua) hari kalender terhitung sejak tanggal Surat Pesanan ini diterima.</li>
                    <li>Apabila dalam waktu sebagaimana dimaksud pada butir 1, PIHAK KEDUA tidak mengembalikan Surat Pesanan yang telah ditandatangani, maka Surat Pesanan ini dianggap batal dan PIHAK PERTAMA dapat membatalkan pesanan secara sepihak tanpa tuntutan ganti rugi apapun dari PIHAK KEDUA.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">B. HAK DAN KEWAJIBAN PIHAK KEDUA</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>PIHAK KEDUA wajib melaksanakan penyerahan barang sesuai dengan spesifikasi teknis, jumlah, waktu penyerahan dan ketentuan lain yang tercantum dalam Surat Pesanan ini.</li>
                    <li>PIHAK KEDUA tidak diperbolehkan mengalihkan penyerahan barang baik sebagian maupun seluruhnya kepada pihak lain tanpa persetujuan tertulis dari PIHAK PERTAMA.</li>
                    <li>PIHAK KEDUA wajib menjaga kerahasiaan data dan informasi yang diperoleh sehubungan dengan penyerahan barang ini.</li>
                    <li>PIHAK KEDUA memberikan jaminan/garansi atas barang yang diserahkan selama {{masa_garansi_bulan}} ({{masa_garansi_bulan_terbilang}}) bulan terhitung sejak tanggal Berita Acara Serah Terima Pekerjaan (BASTP).</li>
                    <li>Apabila selama masa garansi terjadi kerusakan atau cacat barang yang bukan disebabkan oleh kelalaian PIHAK PERTAMA, maka PIHAK KEDUA wajib memperbaiki atau mengganti barang baru atas biaya PIHAK KEDUA sendiri.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">C. PEMERIKSAAN DAN PENERIMAAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Pemeriksaan barang dilakukan oleh Pejabat Pelaksana Pengadaan / Direksi Pekerjaan bersama-sama dengan PIHAK KEDUA di tempat penyerahan barang yang ditentukan.</li>
                    <li>Hasil pemeriksaan dituangkan dalam Berita Acara Pemeriksaan Barang (BAP) dan Berita Acara Serah Terima Pekerjaan (BASTP) yang ditandatangani oleh kedua belah pihak.</li>
                    <li>Apabila hasil pemeriksaan menunjukkan barang tidak sesuai dengan spesifikasi teknis dalam Surat Pesanan, PIHAK KEDUA wajib mengganti dengan barang yang sesuai dalam jangka waktu yang ditentukan oleh PIHAK PERTAMA.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">D. PEMBAYARAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Pembayaran dilakukan secara sekaligus (100%) setelah seluruh barang diterima dengan baik dan lengkap oleh PIHAK PERTAMA yang dibuktikan dengan Berita Acara Serah Terima Pekerjaan (BASTP).</li>
                    <li>Pembayaran ditransfer ke rekening PIHAK KEDUA:
                        <table style="width:auto; margin:4px 0 4px 12px; border:none; font-size:10pt;">
                            <tr><td style="border:none; padding:1px 8px 1px 0;">- Nama Bank</td><td style="border:none; padding:1px 0;">: {{nama_bank}}</td></tr>
                            <tr><td style="border:none; padding:1px 8px 1px 0;">- Nomor Rekening</td><td style="border:none; padding:1px 0;">: {{nomor_rekening}}</td></tr>
                            <tr><td style="border:none; padding:1px 8px 1px 0;">- Atas Nama</td><td style="border:none; padding:1px 0;">: {{nama_pemilik_rekening}}</td></tr>
                        </table>
                    </li>
                    <li>Dokumen penagihan yang harus dilampirkan:
                        <ol type="a" style="margin:2px 0 2px 18px; padding:0;">
                            <li>Kuitansi bermaterai cukup</li>
                            <li>Faktur Pajak</li>
                            <li>Surat Pesanan asli</li>
                            <li>Berita Acara Serah Terima Pekerjaan (BASTP)</li>
                            <li>Salinan NPWP dan Rekening Bank</li>
                        </ol>
                    </li>
                </ol>

                <div class="page-break" contenteditable="false"></div>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">E. SANKSI DAN DENDA</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Apabila PIHAK KEDUA terlambat menyerahkan barang sesuai batas waktu yang telah ditetapkan, maka PIHAK KEDUA dikenakan denda keterlambatan sebesar 1‰ (satu permil) untuk setiap hari keterlambatan dari nilai Surat Pesanan (sebelum PPN), setinggi-tingginya 5% (lima persen) dari nilai Surat Pesanan.</li>
                    <li>Denda keterlambatan sebagaimana dimaksud pada butir 1 akan dipotong langsung dari pembayaran tagihan yang menjadi hak PIHAK KEDUA.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">F. PEMUTUSAN SURAT PESANAN</p>
                <p style="margin:0 0 4px 0;">PIHAK PERTAMA berhak memutuskan Surat Pesanan ini secara sepihak tanpa tuntutan ganti rugi dari PIHAK KEDUA dalam hal:</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>PIHAK KEDUA tidak dapat menyerahkan barang setelah melewati batas waktu penyerahan ditambah jangka waktu perpanjangan (apabila ada).</li>
                    <li>Denda keterlambatan telah mencapai batas maksimal 5% (lima persen) dari nilai Surat Pesanan.</li>
                    <li>PIHAK KEDUA mengalihkan pengadaan barang kepada pihak lain tanpa persetujuan tertulis dari PIHAK PERTAMA.</li>
                    <li>PIHAK KEDUA dinilai cidera janji (wanprestasi) dalam melaksanakan kewajibannya.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">G. KEADAAN KAHAR (FORCE MAJEURE)</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Keadaan kahar adalah peristiwa di luar kekuasaan para pihak yang meliputi antara lain: bencana alam, kebakaran, perang, huru-hara, epidemi/pandemi, dan kebijakan pemerintah yang berakibat langsung terhadap pelaksanaan Surat Pesanan ini.</li>
                    <li>Pihak yang mengalami keadaan kahar wajib memberitahukan secara tertulis kepada pihak lainnya paling lambat dalam waktu 7 (tujuh) hari kalender sejak terjadinya keadaan kahar dengan melampirkan bukti pernyataan dari instansi yang berwenang.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">H. PENYELESAIAN PERSELISIHAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Segala perselisihan yang timbul sehubungan dengan pelaksanaan Surat Pesanan ini akan diselesaikan oleh kedua belah pihak secara musyawarah untuk mufakat.</li>
                    <li>Apabila penyelesaian secara musyawarah tidak tercapai, maka kedua belah pihak sepakat untuk menyelesaikan perselisihan melalui Pengadilan Negeri di wilayah domisili PIHAK PERTAMA.</li>
                </ol>

                <p style="margin-top:14px; margin-bottom:12px;">Demikian Syarat-syarat Umum Surat Pesanan ini dibuat dan merupakan bagian yang tidak terpisahkan dari Surat Pesanan.</p>

                <table style="width:100%; border-collapse:collapse; border:none; margin-top:16px; page-break-inside:avoid;">
                    <tr>
                        <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                            Pihak Kedua<br>
                            <b>{{nama_mitra}}</b><br><br><br><br><br>
                            <u><b>{{nama_pemilik_rekening}}</b></u><br>
                            Direktur
                        </td>
                        <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                            Pihak Pertama<br>
                            <b>PT PLN (PERSERO) UIKL SULAWESI<br>UP KENDARI</b><br><br><br><br>
                            <u><b>{{direksi_pekerjaan}}</b></u><br>
                            Manager
                        </td>
                    </tr>
                </table>
            </div>
        </section>
        HTML;
    }

    /**
     * Surat Pesanan Jasa (Page 1) + Syarat Umum Jasa (Pages 2-3).
     */
    protected function suratPesananJasa(): string
    {
        return <<<'HTML'
        <section>
            <table style="width:100%; border-collapse:collapse; border:none; margin-bottom:12px;">
                <tr>
                    <td style="border:none; padding:0; vertical-align:top; font-family:Arial, sans-serif; font-size:9pt; line-height:1.2;">
                        <b>PT PLN (PERSERO)<br>
                        UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI<br>
                        UP KENDARI<br>
                        JL. KAPTEN PIERE TENDEAN NO. 45 KENDARI SULAWESI TENGGARA</b>
                    </td>
                </tr>
            </table>

            <div style="text-align:center; margin-bottom:12px;">
                <h2 style="font-size:13pt; font-weight:bold; margin:0 0 2px 0; text-transform:uppercase; border:none;">SURAT PESANAN</h2>
                <p style="margin:0; font-size:9.5pt;">Nomor : {{nomor_pengadaan}}</p>
                <p style="margin:0; font-size:9.5pt;">Tanggal : {{tanggal_dokumen}}</p>
                <p style="margin:0; font-size:9.5pt;">Lampiran : -</p>
            </div>

            <div style="margin-bottom:10px; font-size:10pt;">
                Kepada Yth.<br>
                <b>{{nama_mitra}}</b><br>
                Di Tempat
            </div>

            <p style="text-align:justify; margin-bottom:10px; font-size:10pt;">
                Berdasarkan Berita Acara Negosiasi Harga Nomor : {{nomor_pengadaan}} Tanggal {{tanggal_dokumen}} dengan ini kami sampaikan Surat Pesanan untuk melaksanakan pekerjaan jasa dengan ketentuan sebagai berikut :
            </p>

            <table style="width:100%; border-collapse:collapse; margin-bottom:8px; font-size:9.5pt;">
                <thead>
                    <tr style="background-color:#f2f2f2; text-align:center;">
                        <th style="width:4%; text-align:center; padding:4px;">No</th>
                        <th style="text-align:center; padding:4px;">URAIAN PEKERJAAN</th>
                        <th style="width:8%; text-align:center; padding:4px;">JUMLAH VOLUME</th>
                        <th style="width:8%; text-align:center; padding:4px;">SATUAN</th>
                        <th style="width:15%; text-align:center; padding:4px;">HARGA SATUAN (Rp)</th>
                        <th style="width:15%; text-align:center; padding:4px;">JUMLAH HARGA (Rp)</th>
                        <th style="width:18%; text-align:center; padding:4px;">BATAS WAKTU / PENYELESAIAN JASA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align:center;">1</td>
                        <td>{{nama_pengadaan}}</td>
                        <td style="text-align:center;">1</td>
                        <td style="text-align:center;">Lot</td>
                        <td style="text-align:right;">{{nilai_setelah_nego_angka}}</td>
                        <td style="text-align:right;">{{nilai_setelah_nego_angka}}</td>
                        <td style="text-align:center;">{{tanggal_selesai_pelaksanaan}}</td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">Sub Total</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">DPP (11/12)</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_dpp_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">PPN 12%</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_ppn_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr style="background-color:#f9fafb;">
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:4px 6px;">Jumlah Total</td>
                        <td style="text-align:right; font-weight:bold; padding:4px 6px;">{{nilai_setelah_nego_total_angka}}</td>
                        <td style="background-color:#fcfcfc;"></td>
                    </tr>
                    <tr>
                        <td colspan="7" style="padding:6px; font-style:italic;">
                            <b>Terbilang:</b> {{nilai_setelah_nego_total_terbilang}}
                        </td>
                    </tr>
                </tbody>
            </table>

            <table style="width:100%; border-collapse:collapse; border:none; margin-top:20px; page-break-inside:avoid;">
                <tr>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        Pihak Kedua<br>
                        <b>{{nama_mitra}}</b><br><br><br><br><br>
                        <u><b>{{nama_pemilik_rekening}}</b></u><br>
                        Direktur
                    </td>
                    <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                        Pihak Pertama<br>
                        <b>PT PLN (PERSERO) UIKL SULAWESI<br>UP KENDARI</b><br><br><br><br>
                        <u><b>{{direksi_pekerjaan}}</b></u><br>
                        Manager
                    </td>
                </tr>
            </table>
        </section>

        <div class="page-break" contenteditable="false"></div>

        <section>
            <div style="text-align:center; margin-bottom:14px;">
                <h2 style="font-size:12pt; font-weight:bold; margin:0; text-transform:uppercase; border:none;">SYARAT-SYARAT UMUM SURAT PESANAN (SP)</h2>
            </div>

            <div style="text-align:justify; font-size:10pt; line-height:1.4;">
                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">A. PENERIMAAN SURAT PESANAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Surat Pesanan ini ditandatangani oleh PIHAK KEDUA dan dikembalikan kepada PIHAK PERTAMA paling lambat 2 (dua) hari kalender terhitung sejak tanggal Surat Pesanan ini diterima.</li>
                    <li>Apabila dalam waktu sebagaimana dimaksud pada butir 1, PIHAK KEDUA tidak mengembalikan Surat Pesanan yang telah ditandatangani, maka Surat Pesanan ini dianggap batal dan PIHAK PERTAMA dapat membatalkan pesanan secara sepihak tanpa tuntutan ganti rugi apapun dari PIHAK KEDUA.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">B. HAK DAN KEWAJIBAN PIHAK KEDUA</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>PIHAK KEDUA wajib melaksanakan pekerjaan jasa sesuai dengan spesifikasi teknis, jumlah, waktu pelaksanaan dan ketentuan lain yang tercantum dalam Surat Pesanan ini.</li>
                    <li>PIHAK KEDUA tidak diperbolehkan mengalihkan pelaksanaan pekerjaan baik sebagian maupun seluruhnya kepada pihak lain tanpa persetujuan tertulis dari PIHAK PERTAMA.</li>
                    <li>PIHAK KEDUA wajib menjaga kerahasiaan data dan informasi yang diperoleh sehubungan dengan pelaksanaan pekerjaan ini.</li>
                    <li>PIHAK KEDUA memberikan jaminan/garansi atas hasil pekerjaan selama {{masa_garansi_bulan}} ({{masa_garansi_bulan_terbilang}}) bulan terhitung sejak tanggal Berita Acara Serah Terima Pekerjaan (BASTP).</li>
                    <li>Apabila selama masa pemeliharaan/garansi terjadi kerusakan atau ketidaksesuaian yang bukan disebabkan oleh kelalaian PIHAK PERTAMA, maka PIHAK KEDUA wajib memperbaiki atau mengganti atas biaya PIHAK KEDUA sendiri.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">C. PEMERIKSAAN DAN PENERIMAAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Pemeriksaan hasil pekerjaan dilakukan oleh Pejabat Pelaksana Pengadaan / Direksi Pekerjaan bersama-sama dengan PIHAK KEDUA.</li>
                    <li>Hasil pemeriksaan dituangkan dalam Berita Acara Pemeriksaan Pekerjaan (BAP) dan Berita Acara Serah Terima Pekerjaan (BASTP) yang ditandatangani oleh kedua belah pihak.</li>
                    <li>Apabila hasil pemeriksaan tidak sesuai dengan ketentuan Surat Pesanan, PIHAK KEDUA wajib memperbaiki dalam jangka waktu yang ditentukan oleh PIHAK PERTAMA.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">D. PEMBAYARAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Pembayaran dilakukan secara sekaligus (100%) setelah seluruh pekerjaan selesai dilaksanakan dan diterima dengan baik oleh PIHAK PERTAMA yang dibuktikan dengan Berita Acara Serah Terima Pekerjaan (BASTP).</li>
                    <li>Pembayaran ditransfer ke rekening PIHAK KEDUA:
                        <table style="width:auto; margin:4px 0 4px 12px; border:none; font-size:10pt;">
                            <tr><td style="border:none; padding:1px 8px 1px 0;">- Nama Bank</td><td style="border:none; padding:1px 0;">: {{nama_bank}}</td></tr>
                            <tr><td style="border:none; padding:1px 8px 1px 0;">- Nomor Rekening</td><td style="border:none; padding:1px 0;">: {{nomor_rekening}}</td></tr>
                            <tr><td style="border:none; padding:1px 8px 1px 0;">- Atas Nama</td><td style="border:none; padding:1px 0;">: {{nama_pemilik_rekening}}</td></tr>
                        </table>
                    </li>
                    <li>Dokumen penagihan yang harus dilampirkan:
                        <ol type="a" style="margin:2px 0 2px 18px; padding:0;">
                            <li>Kuitansi bermaterai cukup</li>
                            <li>Faktur Pajak</li>
                            <li>Surat Pesanan asli</li>
                            <li>Berita Acara Serah Terima Pekerjaan (BASTP)</li>
                            <li>Salinan NPWP dan Rekening Bank</li>
                        </ol>
                    </li>
                </ol>

                <div class="page-break" contenteditable="false"></div>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">E. SANKSI DAN DENDA</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Apabila PIHAK KEDUA terlambat menyelesaikan pekerjaan sesuai jangka waktu yang telah ditetapkan, maka PIHAK KEDUA dikenakan denda keterlambatan sebesar 1‰ (satu permil) untuk setiap hari keterlambatan dari nilai Surat Pesanan (sebelum PPN), setinggi-tingginya 5% (lima persen) dari nilai Surat Pesanan.</li>
                    <li>Denda keterlambatan sebagaimana dimaksud pada butir 1 akan dipotong langsung dari pembayaran yang menjadi hak PIHAK KEDUA.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">F. PEMUTUSAN SURAT PESANAN</p>
                <p style="margin:0 0 4px 0;">PIHAK PERTAMA berhak memutuskan Surat Pesanan ini secara sepihak tanpa tuntutan ganti rugi dari PIHAK KEDUA dalam hal:</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>PIHAK KEDUA tidak dapat menyelesaikan pekerjaan setelah melewati batas waktu pelaksanaan ditambah jangka waktu perpanjangan (apabila ada).</li>
                    <li>Denda keterlambatan telah mencapai batas maksimal 5% (lima persen) dari nilai Surat Pesanan.</li>
                    <li>PIHAK KEDUA mengalihkan pekerjaan kepada pihak lain tanpa persetujuan tertulis dari PIHAK PERTAMA.</li>
                    <li>PIHAK KEDUA dinilai cidera janji (wanprestasi) dalam melaksanakan kewajibannya.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">G. KEADAAN KAHAR (FORCE MAJEURE)</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Keadaan kahar adalah peristiwa di luar kekuasaan para pihak yang meliputi antara lain: bencana alam, perang, huru-hara, epidemi/pandemi, dan kebijakan pemerintah yang berakibat langsung terhadap pelaksanaan Surat Pesanan ini.</li>
                    <li>Pihak yang mengalami keadaan kahar wajib memberitahukan secara tertulis kepada pihak lainnya paling lambat dalam waktu 7 (tujuh) hari kalender sejak terjadinya keadaan kahar dengan melampirkan bukti dari instansi yang berwenang.</li>
                </ol>

                <p style="font-weight:bold; margin-top:8px; margin-bottom:4px;">H. PENYELESAIAN PERSELISIHAN</p>
                <ol style="margin:0 0 8px 20px; padding:0;">
                    <li>Segala perselisihan yang timbul sehubungan dengan pelaksanaan Surat Pesanan ini akan diselesaikan oleh kedua belah pihak secara musyawarah untuk mufakat.</li>
                    <li>Apabila penyelesaian secara musyawarah tidak tercapai, maka kedua belah pihak sepakat untuk menyelesaikan perselisihan melalui Pengadilan Negeri di wilayah domisili PIHAK PERTAMA.</li>
                </ol>

                <p style="margin-top:14px; margin-bottom:12px;">Demikian Syarat-syarat Umum Surat Pesanan ini dibuat dan merupakan bagian yang tidak terpisahkan dari Surat Pesanan.</p>

                <table style="width:100%; border-collapse:collapse; border:none; margin-top:16px; page-break-inside:avoid;">
                    <tr>
                        <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                            Pihak Kedua<br>
                            <b>{{nama_mitra}}</b><br><br><br><br><br>
                            <u><b>{{nama_pemilik_rekening}}</b></u><br>
                            Direktur
                        </td>
                        <td style="width:50%; border:none; text-align:center; vertical-align:top; font-size:10pt;">
                            Pihak Pertama<br>
                            <b>PT PLN (PERSERO) UIKL SULAWESI<br>UP KENDARI</b><br><br><br><br>
                            <u><b>{{direksi_pekerjaan}}</b></u><br>
                            Manager
                        </td>
                    </tr>
                </table>
            </div>
        </section>
        HTML;
    }

    /**
     * Legacy Lampiran Surat Pesanan for goods or for services.
     */
    protected function lampiranLegacy(string $kind): string
    {
        $columns = $kind === 'BARANG'
            ? '<th>Nama Barang / Spesifikasi</th><th style="width:10%">Qty</th><th style="width:10%">Satuan</th>'
            : '<th>Uraian Pekerjaan Jasa</th><th style="width:10%">Volume</th><th style="width:10%">Satuan</th>';

        $row = '<td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td>';

        return <<<HTML
        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td>Lampiran Surat Pesanan</td><td>: {{nomor_pengadaan}}</td></tr>
                <tr><td>Tanggal</td><td>: {{tanggal_dokumen}}</td></tr>
                <tr><td>Pekerjaan</td><td>: {{nama_pengadaan}}</td></tr>
            </table>

            <h1>RINCIAN {$kind}</h1>

            <table>
                <tr><th style="width:5%">No</th>{$columns}<th style="width:16%">Harga Satuan (Rp)</th><th style="width:16%">Jumlah (Rp)</th></tr>
                <tr><td>1</td>{$row}</tr>
                <tr><td>2</td>{$row}</tr>
                <tr><td>3</td>{$row}</tr>
                <tr><td colspan="5"><b>Total (sesuai BA Negosiasi)</b></td><td><b>{{nilai_setelah_nego}}</b></td></tr>
            </table>

            <p class="note">Total lampiran mengikuti nilai hasil negosiasi pada Berita Acara Negosiasi.</p>
        </section>
        HTML;
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
