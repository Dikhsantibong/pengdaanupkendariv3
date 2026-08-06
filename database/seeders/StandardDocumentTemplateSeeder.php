<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Working templates for the checklist steps that had none.
 *
 * These are deliberately plain: they carry the letterhead, the procurement
 * data and the sections the document needs, with the wording left simple so
 * the unit can replace it with the official text from the Data Master screen
 * without waiting on a developer. Every one is editable and versionable like
 * any other template.
 */
class StandardDocumentTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a standard template for each document type that lacks one.
     */
    public function run(): void
    {
        $templates = [
            'penawaran' => ['Penawaran', $this->penawaran()],
            'csms' => ['CSMS', $this->csms()],
            'nota-dinas-perintah-pekerjaan' => [
                'Nota Dinas Perintah Pekerjaan',
                $this->notaDinasPerintahPekerjaan(),
            ],
            'penyusunan-hps' => ['Penyusunan HPS', $this->penyusunanHps()],
            'proses-smart-scm' => ['Proses SMART SCM', $this->prosesSmartScm()],
            'penyusunan-kontrak' => ['Penyusunan Kontrak', $this->penyusunanKontrak()],
            'jaminan-bank' => ['Jaminan Bank', $this->jaminanBank()],
            'amandemen' => ['Amandemen', $this->amandemen()],
            'masa-pemeliharaan' => ['Masa Pemeliharaan', $this->masaPemeliharaan()],
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

        $this->command->info("Template standar terpasang untuk {$seeded} jenis dokumen.");
    }

    /**
     * The letterhead and procurement summary every standard document opens with.
     */
    protected function header(string $title, string $numberPrefix): string
    {
        return <<<HTML
        <table class="lampiran-head">
            <tr>
                <td><b>PT PLN NUSANTARA POWER<br>UP KENDARI</b></td>
                <td style="text-align:right">Nomor : <span class="fill">............ /{$numberPrefix}/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
            </tr>
        </table>

        <h1>{$title}</h1>

        <table>
            <tr><td style="width:32%">Nomor Pengadaan</td><td>{{nomor_pengadaan}}</td></tr>
            <tr><td>Nama Pekerjaan</td><td><b>{{nama_pengadaan}}</b></td></tr>
            <tr><td>Unit Tujuan</td><td>{{unit_tujuan}}</td></tr>
            <tr><td>Direksi Pekerjaan</td><td>{{direksi_pekerjaan}}</td></tr>
            <tr><td>Metode Pengadaan</td><td>{{metode_pengadaan}}</td></tr>
            <tr><td>Sumber Anggaran</td><td>{{sumber_anggaran_keterangan}} ({{sumber_anggaran}})</td></tr>
            <tr><td>Nilai HPE</td><td>{{nilai_hpe}}<br><i>Terbilang: {{nilai_hpe_terbilang}}</i></td></tr>
            <tr><td>Target Penyelesaian</td><td>{{target_penyelesaian}}</td></tr>
        </table>
        HTML;
    }

    /**
     * The signature block every standard document closes with.
     */
    protected function signature(string $leftRole, string $rightRole): string
    {
        return <<<HTML
        <p style="margin-top:18pt">Kendari, {{tanggal_dokumen}}</p>

        <table class="signature">
            <tr>
                <td class="role">{$leftRole}</td>
                <td class="role">{$rightRole}</td>
            </tr>
            <tr><td class="space"></td><td class="space"></td></tr>
            <tr>
                <td class="name fill">( Nama Jelas )</td>
                <td class="name fill">( Nama Jelas )</td>
            </tr>
        </table>

        <p class="note">Template standar. Perbarui redaksi sesuai dokumen resmi melalui menu Data Master &rarr; Template Dokumen. Setelah dicetak dan ditandatangani, unggah kembali hasil pindaiannya pada tahapan checklist ini.</p>
        HTML;
    }

    /**
     * Dokumen Penawaran.
     */
    protected function penawaran(): string
    {
        $header = $this->header('DOKUMEN PENAWARAN', 'PNW');
        $signature = $this->signature('Pejabat Pelaksana Pengadaan', 'Penyedia Barang/Jasa');

        return <<<HTML
        <section>
            {$header}

            <h3>A. Identitas Penyedia Barang/Jasa</h3>
            <table>
                <tr><td style="width:32%">Nama Perusahaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Alamat</td><td class="fill">&nbsp;</td></tr>
                <tr><td>NPWP</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Nomor Surat Penawaran</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Tanggal Surat Penawaran</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>B. Rincian Penawaran</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Uraian Barang/Jasa</th><th style="width:10%">Qty</th><th style="width:10%">Satuan</th><th style="width:16%">Harga Satuan (Rp)</th><th style="width:16%">Jumlah (Rp)</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Sub Total</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>PPN</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Total Penawaran</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Terbilang:</b> <span class="fill">.................................................................</span></td></tr>
            </table>

            <h3>C. Ketentuan Penawaran</h3>
            <ol>
                <li>Harga penawaran sudah termasuk seluruh pajak yang berlaku.</li>
                <li>Masa berlaku penawaran <span class="fill">......</span> hari kalender sejak tanggal pembukaan penawaran.</li>
                <li>Jangka waktu penyerahan Barang/Jasa <span class="fill">......</span> hari kalender sejak Surat Penunjukan diterbitkan.</li>
                <li>Penawaran ini disusun sesuai RKS dan Term of Reference (TOR) pekerjaan di atas.</li>
            </ol>

            <h3>D. Catatan</h3>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Dokumen CSMS.
     */
    protected function csms(): string
    {
        $header = $this->header(
            'CONTRACTOR SAFETY MANAGEMENT SYSTEM<br>( CSMS )',
            'CSMS',
        );
        $signature = $this->signature('Pejabat Pelaksana Pengadaan', 'Fungsi K3 &amp; Lingkungan');

        return <<<HTML
        <section>
            {$header}

            <h3>A. Identitas Penyedia Barang/Jasa</h3>
            <table>
                <tr><td style="width:32%">Nama Perusahaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Nomor Sertifikat CSMS</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Masa Berlaku</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Kategori Risiko Pekerjaan</td><td class="fill">Rendah / Sedang / Tinggi / Ekstrim</td></tr>
            </table>

            <h3>B. Penilaian Tahap Kualifikasi</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Elemen Penilaian</th><th style="width:12%">Bobot</th><th style="width:12%">Nilai</th><th style="width:20%">Keterangan</th></tr>
                <tr><td>1</td><td>Kepemimpinan dan Komitmen K3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td>Kebijakan dan Sasaran Strategis K3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td>Organisasi, Sumber Daya dan Dokumentasi</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td>Manajemen Risiko (HIRAC)</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>5</td><td>Perencanaan dan Prosedur Kerja Aman</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>6</td><td>Implementasi dan Pemantauan Kinerja</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>7</td><td>Audit dan Tinjauan Manajemen</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="2"><b>Total</b></td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td></td></tr>
            </table>

            <h3>C. Kesimpulan</h3>
            <p>Berdasarkan penilaian di atas, Penyedia Barang/Jasa dinyatakan <b><span class="fill">LULUS / TIDAK LULUS</span></b> tahap kualifikasi CSMS untuk pekerjaan <b>{{nama_pengadaan}}</b>.</p>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Nota Dinas Perintah Pekerjaan.
     */
    protected function notaDinasPerintahPekerjaan(): string
    {
        $header = $this->header('NOTA DINAS PERINTAH PEKERJAAN', 'ND');
        $signature = $this->signature('Yang Memerintahkan,<br>Manager UP Kendari', 'Menerima Perintah,<br>{{direksi_pekerjaan}}');

        return <<<HTML
        <section>
            {$header}

            <table>
                <tr><td style="width:16%">Kepada</td><td>: {{direksi_pekerjaan}}</td></tr>
                <tr><td>Dari</td><td>: Manager PT PLN Nusantara Power UP Kendari</td></tr>
                <tr><td>Perihal</td><td>: Perintah Pelaksanaan Pekerjaan</td></tr>
                <tr><td>Tanggal</td><td>: {{tanggal_dokumen}}</td></tr>
            </table>

            <h3>A. Dasar</h3>
            <ol>
                <li>Nota Dinas Usulan Pengadaan Nomor {{nomor_prk}}.</li>
                <li>Term of Reference (TOR) pekerjaan {{nama_pengadaan}}.</li>
                <li>Rencana Anggaran Biaya (RAB) dan HPE yang telah ditetapkan.</li>
            </ol>

            <h3>B. Perintah</h3>
            <p>Sehubungan dengan dasar tersebut di atas, dengan ini diperintahkan untuk melaksanakan pekerjaan:</p>
            <table>
                <tr><td style="width:32%">Nama Pekerjaan</td><td><b>{{nama_pengadaan}}</b></td></tr>
                <tr><td>Lokasi</td><td>{{unit_tujuan}}</td></tr>
                <tr><td>Nilai Anggaran</td><td>{{nilai_hpe}}</td></tr>
                <tr><td>Sumber Anggaran</td><td>{{sumber_anggaran_keterangan}} ({{sumber_anggaran}})</td></tr>
                <tr><td>Target Penyelesaian</td><td>{{target_penyelesaian}}</td></tr>
                <tr><td>PIC Perencana</td><td>{{pic_perencana}}</td></tr>
                <tr><td>PIC Pelaksana</td><td>{{pic_pelaksana}}</td></tr>
            </table>

            <h3>C. Ketentuan Pelaksanaan</h3>
            <ol>
                <li>Pekerjaan dilaksanakan sesuai lingkup dan spesifikasi pada TOR serta RKS.</li>
                <li>Seluruh tahapan pengadaan mengikuti Peraturan Direksi PT PLN Nusantara Power tentang Pengadaan Barang/Jasa yang berlaku.</li>
                <li>Pelaksana wajib mematuhi ketentuan Keselamatan, Keamanan Kerja dan Lingkungan (K3L) di lingkungan UP Kendari.</li>
                <li>Progres pekerjaan dilaporkan secara berkala kepada Direksi Pekerjaan.</li>
                <li><span class="fill">..............................................................................</span></li>
            </ol>

            <p>Demikian nota dinas ini dibuat untuk dilaksanakan sebagaimana mestinya.</p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Penyusunan HPS.
     */
    protected function penyusunanHps(): string
    {
        $header = $this->header(
            'HARGA PERKIRAAN SENDIRI<br>( HPS )',
            'HPS',
        );
        $signature = $this->signature('Disusun Oleh,<br>Pejabat Perencana Pengadaan', 'Ditetapkan Oleh,<br>Pejabat yang Berwenang');

        return <<<HTML
        <section>
            {$header}

            <h3>A. Dasar Penyusunan</h3>
            <ol>
                <li>Term of Reference (TOR) dan Rencana Anggaran Biaya (RAB) pekerjaan di atas.</li>
                <li>Harga pasar setempat pada saat penyusunan HPS.</li>
                <li>Data kontrak sejenis yang pernah dilaksanakan.</li>
                <li><span class="fill">..............................................................................</span></li>
            </ol>

            <h3>B. Rincian Harga Perkiraan Sendiri</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Uraian Barang/Jasa</th><th style="width:9%">Qty</th><th style="width:9%">Satuan</th><th style="width:16%">Harga Satuan (Rp)</th><th style="width:16%">Jumlah (Rp)</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Sub Total</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>PPN</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Total HPS</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Terbilang:</b> <span class="fill">.................................................................</span></td></tr>
            </table>

            <h3>C. Perbandingan terhadap HPE</h3>
            <table>
                <tr><td style="width:40%">Nilai HPE</td><td>{{nilai_hpe}}</td></tr>
                <tr><td>Nilai HPS</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Selisih</td><td class="fill">&nbsp;</td></tr>
            </table>

            <p class="note">HPS bersifat rahasia sampai dengan pembukaan penawaran, dan digunakan sebagai dasar penilaian kewajaran harga penawaran.</p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Proses SMART SCM.
     */
    protected function prosesSmartScm(): string
    {
        $header = $this->header('LEMBAR PROSES SMART SCM', 'SCM');
        $signature = $this->signature('Pejabat Pelaksana Pengadaan', 'Mengetahui,<br>{{direksi_pekerjaan}}');

        return <<<HTML
        <section>
            {$header}

            <h3>A. Data Proses pada Aplikasi SMART SCM</h3>
            <table>
                <tr><td style="width:32%">Nomor Paket SMART SCM</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Nomor PR/RO</td><td>{{nomor_pr_ro}}</td></tr>
                <tr><td>Tanggal Inisiasi</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Status Terakhir</td><td>{{status_progres}}</td></tr>
                <tr><td>Diproses Oleh</td><td>{{pic_pelaksana}}</td></tr>
            </table>

            <h3>B. Riwayat Tahapan pada SMART SCM</h3>
            <table>
                <tr><th style="width:5%">No</th><th style="width:22%">Tahapan</th><th style="width:16%">Tanggal</th><th style="width:18%">Pelaksana</th><th>Keterangan</th></tr>
                <tr><td>1</td><td>Inisiasi Paket</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td>Unggah Dokumen Pengadaan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td>Pengumuman / Undangan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td>Pemasukan Penawaran</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>5</td><td>Evaluasi dan Penetapan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>6</td><td>Penerbitan Kontrak / SPK</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Catatan Proses</h3>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Penyusunan Kontrak.
     */
    protected function penyusunanKontrak(): string
    {
        $header = $this->header('LEMBAR PENYUSUNAN KONTRAK', 'PK');
        $signature = $this->signature('Disusun Oleh,<br>Pejabat Pelaksana Pengadaan', 'Diperiksa Oleh,<br>{{direksi_pekerjaan}}');

        return <<<HTML
        <section>
            {$header}

            <h3>A. Data Calon Penyedia Barang/Jasa</h3>
            <table>
                <tr><td style="width:32%">Nama Perusahaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Alamat</td><td class="fill">&nbsp;</td></tr>
                <tr><td>NPWP</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Nomor SPPBJ</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Tanggal SPPBJ</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>B. Pokok-Pokok Kontrak</h3>
            <table>
                <tr><td style="width:32%">Nilai Kontrak</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jangka Waktu Pelaksanaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Cara Pembayaran</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jaminan Pelaksanaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Masa Garansi/Pemeliharaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Sanksi Keterlambatan</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Daftar Periksa Kelengkapan Kontrak</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Kelengkapan</th><th style="width:14%">Ada</th><th style="width:24%">Keterangan</th></tr>
                <tr><td>1</td><td>Surat Penunjukan Penyedia Barang/Jasa (SPPBJ)</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td>Berita Acara Hasil Evaluasi</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td>Berita Acara Klarifikasi dan Negosiasi</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td>Asli Jaminan Pelaksanaan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>5</td><td>RKS dan Term of Reference (TOR)</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>6</td><td>Dokumen Penawaran dan Rincian Harga</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>7</td><td>Akta Pendirian dan Perubahannya</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>8</td><td>NPWP dan Pengukuhan PKP</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>D. Catatan Penyusunan</h3>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Jaminan Bank.
     */
    protected function jaminanBank(): string
    {
        $header = $this->header('LEMBAR VERIFIKASI JAMINAN BANK', 'JB');
        $signature = $this->signature('Diverifikasi Oleh,<br>Pejabat Pelaksana Pengadaan', 'Mengetahui,<br>Fungsi Keuangan');

        return <<<HTML
        <section>
            {$header}

            <h3>A. Data Jaminan</h3>
            <table>
                <tr><td style="width:32%">Jenis Jaminan</td><td class="fill">Jaminan Penawaran / Jaminan Pelaksanaan / Jaminan Pemeliharaan</td></tr>
                <tr><td>Nomor Bank Garansi</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Bank Penerbit</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Atas Nama (Yang Dijamin)</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Nilai Jaminan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Masa Berlaku</td><td class="fill">&nbsp; s.d. &nbsp;</td></tr>
            </table>

            <h3>B. Daftar Periksa Keabsahan</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Syarat Jaminan</th><th style="width:14%">Sesuai</th><th style="width:24%">Keterangan</th></tr>
                <tr><td>1</td><td>Judul jaminan &ldquo;Garansi Bank&rdquo; atau &ldquo;Bank Garansi&rdquo;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td>Nama dan alamat jelas Bank Penerbit</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td>Nama dan alamat jelas Pemberi Pekerjaan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td>Nama dan alamat jelas Penyedia Barang/Jasa</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>5</td><td>Nama paket pekerjaan yang dijamin sesuai</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>6</td><td>Nilai jaminan dalam angka dan huruf sama</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>7</td><td>Dapat dicairkan tanpa syarat (unconditional)</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>8</td><td>Mengesampingkan Pasal 1831 KUH Perdata</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>9</td><td>Bank termasuk Daftar Penerbit Jaminan Terseleksi</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>10</td><td>Sudah dikonfirmasi keabsahannya ke Bank Penerbit</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Kesimpulan</h3>
            <p>Jaminan tersebut dinyatakan <b><span class="fill">SAH / TIDAK SAH</span></b> dan dapat diterima sebagai jaminan atas pekerjaan <b>{{nama_pengadaan}}</b>.</p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Amandemen.
     */
    protected function amandemen(): string
    {
        $header = $this->header('AMANDEMEN PERJANJIAN / KONTRAK', 'AMD');
        $signature = $this->signature('PIHAK KESATU<br>PT PLN Nusantara Power UP Kendari', 'PIHAK KEDUA<br>Penyedia Barang/Jasa');

        return <<<HTML
        <section>
            {$header}

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, yang bertanda tangan di bawah ini sepakat mengadakan amandemen atas Perjanjian/Kontrak pekerjaan <b>{{nama_pengadaan}}</b>.</p>

            <h3>A. Data Perjanjian Induk</h3>
            <table>
                <tr><td style="width:32%">Nomor Perjanjian</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Tanggal Perjanjian</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Nilai Perjanjian</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jangka Waktu</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Amandemen Ke-</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>B. Alasan Amandemen</h3>
            <p><span class="fill">..............................................................................</span></p>

            <h3>C. Perubahan yang Disepakati</h3>
            <table>
                <tr><th style="width:5%">No</th><th style="width:20%">Pasal / Ketentuan</th><th>Semula</th><th>Menjadi</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>D. Ketentuan Penutup</h3>
            <ol>
                <li>Ketentuan lain dalam Perjanjian induk yang tidak diubah dalam amandemen ini dinyatakan tetap berlaku dan mengikat para pihak.</li>
                <li>Amandemen ini merupakan bagian yang tidak terpisahkan dari Perjanjian induk.</li>
                <li>Amandemen ini berlaku sejak tanggal ditandatangani oleh para pihak.</li>
            </ol>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Masa Pemeliharaan.
     */
    protected function masaPemeliharaan(): string
    {
        $header = $this->header(
            'BERITA ACARA MASA PEMELIHARAAN<br>DAN SERAH TERIMA AKHIR PEKERJAAN',
            'BAMP',
        );
        $signature = $this->signature('Tim Pemeriksa Kualitas Barang/Jasa', 'Penyedia Barang/Jasa');

        return <<<HTML
        <section>
            {$header}

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, telah dilaksanakan pemeriksaan akhir atas masa pemeliharaan pekerjaan <b>{{nama_pengadaan}}</b>.</p>

            <h3>A. Data Masa Pemeliharaan</h3>
            <table>
                <tr><td style="width:32%">Nomor Perjanjian/Kontrak</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Penyedia Barang/Jasa</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Tanggal BAST Pertama</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Masa Pemeliharaan</td><td class="fill">&nbsp; s.d. &nbsp;</td></tr>
                <tr><td>Jaminan Pemeliharaan</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>B. Hasil Pemeriksaan</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Uraian Pemeriksaan</th><th style="width:16%">Hasil</th><th style="width:24%">Keterangan</th></tr>
                <tr><td>1</td><td>Barang/Jasa berfungsi sesuai spesifikasi</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td>Tidak terdapat cacat mutu yang belum diperbaiki</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td>Seluruh perbaikan pada masa pemeliharaan selesai</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td>Dokumen dan manual telah diserahkan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Kesimpulan</h3>
            <p>Masa pemeliharaan dinyatakan <b><span class="fill">SELESAI / BELUM SELESAI</span></b>. Dengan berakhirnya masa pemeliharaan ini, Jaminan Pemeliharaan dapat dikembalikan kepada Penyedia Barang/Jasa.</p>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
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
