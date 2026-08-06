<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The documents the Kontrak step produces.
 *
 * The step yields three separate documents: the SPK itself, its annex, and the
 * negotiation berita acara together with its own annex in a single file.
 */
class KontrakTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the contract documents.
     */
    public function run(): void
    {
        $templates = [
            'spk' => ['SPK (Surat Perintah Kerja)', $this->spk()],
            'lampiran-spk' => ['Lampiran Surat Perintah Kerja', $this->lampiranSpk()],
            'ba-negosiasi' => ['Berita Acara Negosiasi dan Lampiran', $this->beritaAcaraNegosiasi()],
        ];

        $seeded = 0;

        foreach ($templates as $code => [$name, $body]) {
            $documentType = DocumentType::query()->where('code', $code)->first();

            if ($documentType === null) {
                continue;
            }

            DocumentTemplate::query()->updateOrCreate(
                [
                    'document_type_id' => $documentType->id,
                    'procurement_method_id' => null,
                    'version' => 1,
                ],
                [
                    'name' => $name.' - Template Standar UP Kendari',
                    'body' => $body,
                    'placeholders' => $this->placeholdersIn($body),
                    'is_active' => true,
                ],
            );

            $seeded++;
        }

        $this->command->info("Template kontrak terpasang untuk {$seeded} jenis dokumen.");
    }

    /**
     * Surat Perintah Kerja.
     */
    protected function spk(): string
    {
        return <<<'HTML'
        <section>
            <table class="lampiran-head">
                <tr>
                    <td><b>PT PLN NUSANTARA POWER<br>UP KENDARI</b></td>
                    <td style="text-align:right">Nomor : <span class="fill">............ .SPK/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
                </tr>
            </table>

            <h1>SURAT PERINTAH KERJA<br>( SPK )</h1>

            <p>Yang bertanda tangan di bawah ini:</p>
            <table>
                <tr><td style="width:22%">Nama</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jabatan</td><td>Manager PT PLN Nusantara Power UP Kendari</td></tr>
                <tr><td>Alamat</td><td>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari</td></tr>
            </table>
            <p>selanjutnya disebut <b>PIHAK KESATU</b>, dengan ini memberikan perintah kerja kepada:</p>
            <table>
                <tr><td style="width:22%">Nama</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jabatan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Perusahaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Alamat</td><td class="fill">&nbsp;</td></tr>
                <tr><td>NPWP</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p>selanjutnya disebut <b>PIHAK KEDUA</b>, untuk melaksanakan pekerjaan sebagai berikut:</p>

            <h3>Pasal 1 &mdash; Lingkup Pekerjaan</h3>
            <table>
                <tr><td style="width:32%">Nama Pekerjaan</td><td><b>{{nama_pengadaan}}</b></td></tr>
                <tr><td>Lokasi</td><td>{{unit_tujuan}}</td></tr>
                <tr><td>Nomor Pengadaan</td><td>{{nomor_pengadaan}}</td></tr>
                <tr><td>Metode Pengadaan</td><td>{{metode_pengadaan}}</td></tr>
                <tr><td>Direksi Pekerjaan</td><td>{{direksi_pekerjaan}}</td></tr>
            </table>
            <p>Rincian lingkup pekerjaan diuraikan dalam Lampiran Surat Perintah Kerja yang merupakan bagian tidak terpisahkan dari SPK ini.</p>

            <h3>Pasal 2 &mdash; Nilai Pekerjaan</h3>
            <table>
                <tr><td style="width:32%">Nilai SPK</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Sumber Anggaran</td><td>{{sumber_anggaran_keterangan}} ({{sumber_anggaran}})</td></tr>
            </table>
            <p>Nilai tersebut bersifat lump sum, sudah termasuk seluruh pajak yang berlaku, dan merupakan Harga Akhir hasil negosiasi sebagaimana Berita Acara Negosiasi terlampir.</p>

            <h3>Pasal 3 &mdash; Jangka Waktu Pelaksanaan</h3>
            <ol>
                <li>Jangka waktu pelaksanaan pekerjaan adalah <span class="fill">......</span> (<span class="fill">..................</span>) hari kalender terhitung sejak tanggal SPK ini diterbitkan.</li>
                <li>Target penyelesaian pengadaan: {{target_penyelesaian}}.</li>
                <li>Perpanjangan waktu hanya dapat diberikan atas persetujuan tertulis PIHAK KESATU.</li>
            </ol>

            <h3>Pasal 4 &mdash; Cara Pembayaran</h3>
            <ol>
                <li>Pembayaran dilakukan 100% setelah pekerjaan diterima dengan baik, dibuktikan dengan Berita Acara Pemeriksaan Pekerjaan dan Berita Acara Serah Terima Pekerjaan.</li>
                <li>Pembayaran ditransfer ke rekening PIHAK KEDUA: <span class="fill">..............................................</span></li>
                <li>Seluruh biaya bank menjadi beban PIHAK KEDUA.</li>
            </ol>

            <h3>Pasal 5 &mdash; Sanksi Keterlambatan</h3>
            <p>Keterlambatan penyelesaian pekerjaan dikenakan denda sebesar 1&permil; (satu perseribu) dari nilai SPK untuk setiap hari keterlambatan, dengan maksimum 5% (lima persen) dari nilai SPK, kecuali keterlambatan tersebut disebabkan oleh keadaan kahar (force majeure) atau kesalahan PIHAK KESATU.</p>

            <h3>Pasal 6 &mdash; Keselamatan dan Kesehatan Kerja</h3>
            <p>PIHAK KEDUA wajib mematuhi seluruh ketentuan Sistem Manajemen Keselamatan dan Kesehatan Kerja (SMK3), Sistem Manajemen Lingkungan (SML) dan Contractor Safety Management System (CSMS) yang berlaku di lingkungan PT PLN Nusantara Power UP Kendari.</p>

            <h3>Pasal 7 &mdash; Penyelesaian Perselisihan</h3>
            <p>Perselisihan yang timbul diselesaikan secara musyawarah. Apabila tidak tercapai kesepakatan, penyelesaian dilakukan melalui Badan Arbitrase Nasional Indonesia (BANI) di Surabaya.</p>

            <h3>Pasal 8 &mdash; Penutup</h3>
            <p>SPK ini dibuat dalam rangkap 2 (dua) bermeterai cukup, masing-masing mempunyai kekuatan hukum yang sama, dan berlaku sejak tanggal ditandatangani oleh para pihak.</p>

            <table class="signature">
                <tr>
                    <td class="role">PIHAK KESATU<br>PT PLN Nusantara Power UP Kendari</td>
                    <td class="role">PIHAK KEDUA<br>Penyedia Barang/Jasa</td>
                </tr>
                <tr><td class="space">Meterai Rp 10.000,-</td><td class="space">Meterai Rp 10.000,-</td></tr>
                <tr>
                    <td class="name fill">( Nama Jelas )</td>
                    <td class="name fill">( Nama Jelas )</td>
                </tr>
            </table>

            <p class="note">Template standar. Perbarui redaksi sesuai dokumen resmi melalui menu Data Master &rarr; Template Dokumen. Setelah dicetak dan ditandatangani, unggah kembali hasil pindaiannya pada tahapan checklist ini.</p>
        </section>
        HTML;
    }

    /**
     * Lampiran Surat Perintah Kerja.
     */
    protected function lampiranSpk(): string
    {
        return <<<'HTML'
        <section>
            <table class="lampiran-head">
                <tr>
                    <td><b>LAMPIRAN SURAT PERINTAH KERJA</b></td>
                    <td style="text-align:right">SPK No. : <span class="fill">............ .SPK/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
                </tr>
            </table>

            <h1>LAMPIRAN SURAT PERINTAH KERJA</h1>
            <p style="text-align:center"><b>{{nama_pengadaan}}</b><br>{{unit_tujuan}}</p>

            <h3>A. Rincian Lingkup dan Harga Pekerjaan</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Uraian Barang/Jasa</th><th style="width:9%">Qty</th><th style="width:9%">Satuan</th><th style="width:16%">Harga Satuan (Rp)</th><th style="width:16%">Jumlah (Rp)</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Sub Total</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>PPN</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Total Nilai SPK</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Terbilang:</b> <span class="fill">.................................................................</span></td></tr>
            </table>

            <h3>B. Spesifikasi Teknis</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Uraian</th><th>Spesifikasi yang Dipersyaratkan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Jadwal Pelaksanaan</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Tahapan Pekerjaan</th><th style="width:18%">Mulai</th><th style="width:18%">Selesai</th><th style="width:14%">Bobot (%)</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>D. Ketentuan Serah Terima</h3>
            <ol>
                <li>Penyerahan pekerjaan disertai Berita Acara Serah Terima Pekerjaan (BASTP).</li>
                <li>Pemeriksaan dilakukan oleh Tim Pemeriksa Kualitas Barang/Jasa paling lambat 14 (empat belas) hari kerja setelah penyerahan.</li>
                <li>Masa garansi/pemeliharaan selama <span class="fill">......</span> bulan sejak Berita Acara Pemeriksaan diterbitkan.</li>
            </ol>

            <table class="signature">
                <tr>
                    <td class="role">PIHAK KESATU<br>PT PLN Nusantara Power UP Kendari</td>
                    <td class="role">PIHAK KEDUA<br>Penyedia Barang/Jasa</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">( Nama Jelas )</td>
                    <td class="name fill">( Nama Jelas )</td>
                </tr>
            </table>

            <p class="note">Template standar. Perbarui redaksi sesuai dokumen resmi melalui menu Data Master &rarr; Template Dokumen.</p>
        </section>
        HTML;
    }

    /**
     * Berita Acara Negosiasi together with its annex, in one file.
     */
    protected function beritaAcaraNegosiasi(): string
    {
        return <<<'HTML'
        <section>
            <table class="lampiran-head">
                <tr>
                    <td><b>PT PLN NUSANTARA POWER<br>UP KENDARI</b></td>
                    <td style="text-align:right">Nomor : <span class="fill">............ /BAN/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
                </tr>
            </table>

            <h1>BERITA ACARA NEGOSIASI</h1>

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, telah dilaksanakan negosiasi harga atas pekerjaan <b>{{nama_pengadaan}}</b> antara:</p>

            <table>
                <tr><td style="width:32%">Pejabat Pelaksana Pengadaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Penyedia Barang/Jasa</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Diwakili oleh</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jabatan</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>A. Dasar Negosiasi</h3>
            <ol>
                <li>Berita Acara Hasil Evaluasi pekerjaan di atas.</li>
                <li>Harga Perkiraan Sendiri/HPE sebesar {{nilai_hpe}} <i>({{nilai_hpe_terbilang}})</i>.</li>
                <li>Dokumen penawaran harga yang disampaikan Penyedia Barang/Jasa.</li>
            </ol>

            <h3>B. Hasil Negosiasi</h3>
            <table>
                <tr><td style="width:40%">Nilai HPE</td><td>{{nilai_hpe}}</td></tr>
                <tr><td>Harga Penawaran Awal</td><td class="fill">&nbsp;</td></tr>
                <tr><td><b>Harga Akhir Hasil Negosiasi</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Selisih terhadap HPE</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Persentase terhadap HPE</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p>Harga Akhir sebagaimana tersebut di atas disepakati kedua belah pihak dan menjadi Harga Kontrak.</p>

            <h3>C. Kesepakatan Lain</h3>
            <ol>
                <li>Jangka waktu pelaksanaan: <span class="fill">..................</span></li>
                <li>Cara pembayaran: <span class="fill">..................</span></li>
                <li>Masa garansi/pemeliharaan: <span class="fill">..................</span></li>
                <li><span class="fill">..............................................................................</span></li>
            </ol>

            <p>Demikian Berita Acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

            <table class="signature">
                <tr>
                    <td class="role">Pejabat Pelaksana Pengadaan</td>
                    <td class="role">Penyedia Barang/Jasa</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">( Nama Jelas )</td>
                    <td class="name fill">( Nama Jelas )</td>
                </tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr>
                    <td><b>LAMPIRAN BERITA ACARA NEGOSIASI</b></td>
                    <td style="text-align:right">Nomor : <span class="fill">............ /BAN/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
                </tr>
            </table>

            <h1>RINCIAN HASIL NEGOSIASI HARGA</h1>
            <p style="text-align:center"><b>{{nama_pengadaan}}</b></p>

            <table>
                <tr>
                    <th style="width:5%">No</th>
                    <th>Uraian Barang/Jasa</th>
                    <th style="width:8%">Qty</th>
                    <th style="width:8%">Sat.</th>
                    <th style="width:15%">Harga Penawaran (Rp)</th>
                    <th style="width:15%">Harga Negosiasi (Rp)</th>
                    <th style="width:15%">Jumlah (Rp)</th>
                </tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Sub Total</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>PPN</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Total Harga Akhir</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="7"><b>Terbilang:</b> <span class="fill">.................................................................</span></td></tr>
            </table>

            <p class="note">Lampiran ini merupakan bagian yang tidak terpisahkan dari Berita Acara Negosiasi di atas dan dicetak dalam satu berkas yang sama.</p>

            <table class="signature">
                <tr>
                    <td class="role">Pejabat Pelaksana Pengadaan</td>
                    <td class="role">Penyedia Barang/Jasa</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">( Nama Jelas )</td>
                    <td class="name fill">( Nama Jelas )</td>
                </tr>
            </table>
        </section>
        HTML;
    }

    /**
     * Read the placeholders referenced by a template body.
     *
     * @return array<int, string>
     */
    protected function placeholdersIn(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }
}
