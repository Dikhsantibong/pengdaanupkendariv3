<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The berita acara produced during the execution stage of a procurement.
 *
 * These are printed, signed by hand and scanned back in, so each template
 * leaves the parts that are filled in at the meeting as blanks rather than
 * guessing at them. Like every template they stay editable from Data Master.
 */
class BeritaAcaraTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a general template for every berita acara document type.
     */
    public function run(): void
    {
        $bodies = [
            'ba-aanwijzing' => $this->aanwijzing(),
            'lampiran-bapp' => $this->lampiranBapp(),
            'ba-evaluasi-teknis' => $this->evaluasiTeknis(),
            'ba-evaluasi-harga' => $this->evaluasiHarga(),
            'ba-hasil-evaluasi' => $this->hasilEvaluasi(),
            'ba-klarifikasi' => $this->klarifikasi(),
        ];

        $names = [
            'ba-aanwijzing' => 'Berita Acara Aanwijzing (Penjelasan Pelelangan)',
            'lampiran-bapp' => 'Lampiran BAPP (Berita Acara Pembukaan Penawaran)',
            'ba-evaluasi-teknis' => 'Berita Acara Evaluasi Teknis',
            'ba-evaluasi-harga' => 'Berita Acara Evaluasi Harga',
            'ba-hasil-evaluasi' => 'Berita Acara Hasil Evaluasi',
            'ba-klarifikasi' => 'Berita Acara Klarifikasi dan Negosiasi',
        ];

        $missing = [];

        foreach ($bodies as $code => $body) {
            $documentType = DocumentType::query()->where('code', $code)->first();

            if ($documentType === null) {
                $missing[] = $code;

                continue;
            }

            DocumentTemplate::query()->updateOrCreate(
                [
                    'document_type_id' => $documentType->id,
                    'procurement_method_id' => null,
                    'version' => 1,
                ],
                [
                    'name' => $names[$code].' - UP Kendari',
                    'body' => $body,
                    'placeholders' => $this->placeholdersIn($body),
                    'is_active' => true,
                ],
            );
        }

        if ($missing !== []) {
            $this->command->warn(
                'Jenis dokumen belum ada, template dilewati: '.implode(', ', $missing).'.'
            );

            return;
        }

        $this->command->info('Template berita acara pelaksanaan berhasil dipasang.');
    }

    /**
     * The letterhead every berita acara opens with.
     */
    protected function header(string $title, string $subtitle = ''): string
    {
        $subtitleRow = $subtitle === '' ? '' : "<h1 style=\"margin-top:0\">{$subtitle}</h1>";

        return <<<HTML
        <table class="lampiran-head">
            <tr>
                <td><b>PT PLN NUSANTARA POWER<br>UP KENDARI</b></td>
                <td style="text-align:right">Nomor : <span class="fill">............ /BA/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
            </tr>
        </table>

        <h1>{$title}</h1>
        {$subtitleRow}

        <table>
            <tr><td style="width:32%">Nomor Pengadaan</td><td>{{nomor_pengadaan}}</td></tr>
            <tr><td>Nama Pekerjaan</td><td><b>{{nama_pengadaan}}</b></td></tr>
            <tr><td>Unit Tujuan</td><td>{{unit_tujuan}}</td></tr>
            <tr><td>Metode Pengadaan</td><td>{{metode_pengadaan}}</td></tr>
            <tr><td>Sumber Anggaran</td><td>{{sumber_anggaran_keterangan}} ({{sumber_anggaran}})</td></tr>
            <tr><td>Nilai HPE</td><td>{{nilai_hpe}}<br><i>Terbilang: {{nilai_hpe_terbilang}}</i></td></tr>
            <tr><td>Direksi Pekerjaan</td><td>{{direksi_pekerjaan}}</td></tr>
        </table>
        HTML;
    }

    /**
     * The signature block every berita acara closes with.
     */
    protected function signature(string $leftRole, string $rightRole): string
    {
        return <<<HTML
        <p>Demikian Berita Acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

        <p>Kendari, {{tanggal_dokumen}}</p>

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

        <p class="note">Dokumen ini dicetak untuk ditandatangani. Setelah ditandatangani, unggah kembali hasil pindaiannya pada arsip dokumen pengadaan ini.</p>
        HTML;
    }

    /**
     * Berita Acara Aanwijzing.
     */
    protected function aanwijzing(): string
    {
        $header = $this->header(
            'BERITA ACARA PENJELASAN PELELANGAN',
            '( AANWIJZING )',
        );
        $signature = $this->signature(
            'Pejabat Pelaksana Pengadaan',
            'Wakil Peserta Pelelangan',
        );

        return <<<HTML
        <section>
            {$header}

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, bertempat di PT PLN Nusantara Power UP Kendari, telah dilaksanakan Rapat Penjelasan Pelelangan (Aanwijzing) untuk pekerjaan <b>{{nama_pengadaan}}</b>.</p>

            <h3>A. Peserta yang Hadir</h3>
            <table>
                <tr><th style="width:6%">No</th><th>Nama Perusahaan</th><th style="width:24%">Nama Wakil</th><th style="width:20%">Jabatan</th><th style="width:16%">Tanda Tangan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>B. Pokok-Pokok Penjelasan</h3>
            <ol>
                <li>Penjelasan mengenai lingkup pekerjaan sebagaimana diatur dalam RKS dan Term of Reference (TOR).</li>
                <li>Penjelasan mengenai syarat administrasi, keuangan, dan teknis yang harus dipenuhi peserta.</li>
                <li>Penjelasan mengenai tata cara penyampaian dan pembukaan Dokumen Penawaran.</li>
                <li>Penjelasan mengenai jaminan penawaran, jaminan pelaksanaan, serta sanksi yang berlaku.</li>
                <li><span class="fill">..............................................................................</span></li>
            </ol>

            <h3>C. Pertanyaan dan Jawaban</h3>
            <table>
                <tr><th style="width:6%">No</th><th style="width:22%">Penanya</th><th>Pertanyaan</th><th>Jawaban</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>D. Perubahan Dokumen Pelelangan</h3>
            <p><span class="fill">..............................................................................</span></p>
            <p class="note">Perubahan atas isi RKS yang disepakati pada rapat ini dituangkan dalam Addendum RKS dan merupakan bagian yang tidak terpisahkan dari Dokumen Pelelangan.</p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Lampiran BAPP.
     */
    protected function lampiranBapp(): string
    {
        $header = $this->header(
            'LAMPIRAN BERITA ACARA PEMBUKAAN PENAWARAN',
            '( BAPP )',
        );
        $signature = $this->signature(
            'Pejabat Pelaksana Pengadaan',
            'Saksi dari Peserta Pelelangan',
        );

        return <<<HTML
        <section>
            {$header}

            <p>Lampiran ini merupakan bagian yang tidak terpisahkan dari Berita Acara Pembukaan Penawaran pekerjaan <b>{{nama_pengadaan}}</b>, yang dibuka pada hari <span class="fill">..................</span> tanggal <span class="fill">..................</span>.</p>

            <h3>A. Rekapitulasi Penawaran yang Masuk</h3>
            <table>
                <tr>
                    <th style="width:5%">No</th>
                    <th>Nama Penyedia Barang/Jasa</th>
                    <th style="width:16%">Nomor &amp; Tanggal Surat Penawaran</th>
                    <th style="width:16%">Harga Penawaran (Rp)</th>
                    <th style="width:14%">Jaminan Penawaran</th>
                    <th style="width:12%">Kelengkapan</th>
                </tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p class="note">Kolom Kelengkapan diisi LENGKAP atau TIDAK LENGKAP sesuai hasil pemeriksaan Sampul Pertama.</p>

            <h3>B. Daftar Periksa Kelengkapan Dokumen</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Kelengkapan yang Diperiksa</th><th style="width:14%">Penyedia 1</th><th style="width:14%">Penyedia 2</th><th style="width:14%">Penyedia 3</th></tr>
                <tr><td>1</td><td>Surat Pernyataan Minat</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td>Pakta Integritas</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td>Surat Pernyataan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td>Dokumen Administrasi</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>5</td><td>Dokumen Keuangan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>6</td><td>Dokumen Teknis</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>7</td><td>Asli Jaminan Penawaran</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>8</td><td>Surat Penawaran Harga dan Rinciannya</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Catatan Pembukaan Penawaran</h3>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Berita Acara Evaluasi Teknis.
     */
    protected function evaluasiTeknis(): string
    {
        $header = $this->header('BERITA ACARA EVALUASI TEKNIS');
        $signature = $this->signature(
            'Pejabat Pelaksana Pengadaan',
            'Mengetahui,<br>{{direksi_pekerjaan}}',
        );

        return <<<HTML
        <section>
            {$header}

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, telah dilaksanakan evaluasi teknis atas dokumen penawaran pekerjaan <b>{{nama_pengadaan}}</b>.</p>

            <h3>A. Dasar Evaluasi</h3>
            <ol>
                <li>Rencana Kerja dan Syarat-syarat (RKS) pekerjaan di atas beserta addendumnya.</li>
                <li>Term of Reference (TOR) dan spesifikasi teknis yang dipersyaratkan.</li>
                <li>Berita Acara Penjelasan Lelang (Aanwijzing).</li>
            </ol>

            <h3>B. Kriteria Evaluasi Teknis</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Kriteria yang Dinilai</th><th style="width:14%">Penyedia 1</th><th style="width:14%">Penyedia 2</th><th style="width:14%">Penyedia 3</th></tr>
                <tr><td>1</td><td>Kesesuaian spesifikasi teknis Barang/Jasa</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td>Kesesuaian lingkup pekerjaan dengan TOR</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td>Pengalaman pekerjaan sejenis</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td>Ketersediaan peralatan dan personel</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>5</td><td>Jadwal dan metode pelaksanaan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>6</td><td>Sertifikat dan perizinan yang dipersyaratkan</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>7</td><td>Pemenuhan aspek K3L / CSMS</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p class="note">Setiap kriteria diisi MEMENUHI atau TIDAK MEMENUHI. Evaluasi teknis menggunakan metode Sistem Gugur.</p>

            <h3>C. Hasil Evaluasi Teknis</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Nama Penyedia Barang/Jasa</th><th style="width:20%">Hasil</th><th>Alasan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>D. Kesimpulan</h3>
            <p>Penawaran yang dinyatakan MEMENUHI persyaratan teknis dilanjutkan ke tahap evaluasi harga.</p>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Berita Acara Evaluasi Harga.
     */
    protected function evaluasiHarga(): string
    {
        $header = $this->header('BERITA ACARA EVALUASI HARGA');
        $signature = $this->signature(
            'Pejabat Pelaksana Pengadaan',
            'Mengetahui,<br>{{direksi_pekerjaan}}',
        );

        return <<<HTML
        <section>
            {$header}

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, telah dilaksanakan evaluasi harga penawaran atas pekerjaan <b>{{nama_pengadaan}}</b> dengan Harga Perkiraan Sendiri/HPE sebesar <b>{{nilai_hpe}}</b> <i>({{nilai_hpe_terbilang}})</i>.</p>

            <h3>A. Perbandingan Harga Penawaran terhadap HPE</h3>
            <table>
                <tr>
                    <th style="width:5%">No</th>
                    <th>Nama Penyedia Barang/Jasa</th>
                    <th style="width:18%">Harga Penawaran (Rp)</th>
                    <th style="width:14%">% terhadap HPE</th>
                    <th style="width:14%">Peringkat</th>
                    <th style="width:14%">Keterangan</th>
                </tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>B. Metode Evaluasi</h3>
            <ol>
                <li>Evaluasi harga dilakukan dengan metode <b>Sistem Gugur</b> terhadap penawaran yang telah dinyatakan memenuhi persyaratan administrasi, keuangan, dan teknis.</li>
                <li>Harga penawaran dibandingkan terhadap HPE yang telah ditetapkan. Penawaran di atas HPE tidak menggugurkan dan tetap dievaluasi serta ditindaklanjuti dengan negosiasi.</li>
                <li>Penawaran dengan nilai lebih rendah dari 80% HPE dinyatakan tidak wajar dan diklarifikasi secara tertulis kepada penyedia yang bersangkutan.</li>
                <li>Dalam hal terdapat perbedaan antara harga pada Surat Penawaran dan Rincian Penawaran, yang berlaku adalah harga pada Surat Penawaran bermeterai cukup.</li>
            </ol>

            <h3>C. Hasil Evaluasi Harga</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Nama Penyedia Barang/Jasa</th><th style="width:20%">Hasil</th><th>Alasan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p class="note">Kolom Hasil diisi MEMENUHI atau TIDAK MEMENUHI.</p>

            <h3>D. Kesimpulan</h3>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Berita Acara Hasil Evaluasi.
     */
    protected function hasilEvaluasi(): string
    {
        $header = $this->header(
            'BERITA ACARA HASIL EVALUASI',
            '( BAHE )',
        );
        $signature = $this->signature(
            'Pejabat Pelaksana Pengadaan',
            'Menyetujui,<br>Pejabat yang Berwenang',
        );

        return <<<HTML
        <section>
            {$header}

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, Pejabat Pelaksana Pengadaan telah menyelesaikan seluruh tahapan evaluasi atas pekerjaan <b>{{nama_pengadaan}}</b>.</p>

            <h3>A. Rekapitulasi Hasil Evaluasi</h3>
            <table>
                <tr>
                    <th style="width:5%">No</th>
                    <th>Nama Penyedia Barang/Jasa</th>
                    <th style="width:12%">Administrasi</th>
                    <th style="width:12%">Keuangan</th>
                    <th style="width:12%">Teknis</th>
                    <th style="width:12%">Harga</th>
                    <th style="width:14%">Kesimpulan</th>
                </tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p class="note">Setiap kolom diisi MEMENUHI atau TIDAK MEMENUHI sesuai hasil evaluasi pada tahapan yang bersangkutan.</p>

            <h3>B. Urutan Peserta yang Memenuhi Persyaratan</h3>
            <table>
                <tr><th style="width:12%">Peringkat</th><th>Nama Penyedia Barang/Jasa</th><th style="width:22%">Harga Akhir (Rp)</th></tr>
                <tr><td>Pertama</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Kedua</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Ketiga</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Usulan Calon Pemenang</h3>
            <table>
                <tr><td style="width:32%">Nama Penyedia Barang/Jasa</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Alamat</td><td class="fill">&nbsp;</td></tr>
                <tr><td>NPWP</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Harga Akhir</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jangka Waktu Pelaksanaan</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>D. Catatan</h3>
            <p>Berita Acara Hasil Evaluasi ini bersifat rahasia sampai dengan saat pengumuman pemenang, dan menjadi dasar penerbitan Surat Penunjukan Penyedia Barang/Jasa (SPPBJ) setelah masa sanggah berakhir.</p>
            <p><span class="fill">..............................................................................</span></p>

            {$signature}
        </section>
        HTML;
    }

    /**
     * Berita Acara Klarifikasi.
     */
    protected function klarifikasi(): string
    {
        $header = $this->header(
            'BERITA ACARA KLARIFIKASI DAN NEGOSIASI',
        );
        $signature = $this->signature(
            'Pejabat Pelaksana Pengadaan',
            'Penyedia Barang/Jasa',
        );

        return <<<HTML
        <section>
            {$header}

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, telah dilaksanakan klarifikasi dan negosiasi atas pekerjaan <b>{{nama_pengadaan}}</b> antara:</p>

            <table>
                <tr><td style="width:32%">Pejabat Pelaksana Pengadaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Penyedia Barang/Jasa</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Diwakili oleh</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jabatan</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>A. Materi Klarifikasi</h3>
            <table>
                <tr><th style="width:5%">No</th><th style="width:24%">Materi</th><th>Hal yang Diklarifikasi</th><th>Penjelasan Penyedia</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>B. Hasil Negosiasi Harga</h3>
            <table>
                <tr><td style="width:40%">Nilai HPE</td><td>{{nilai_hpe}}</td></tr>
                <tr><td>Harga Penawaran Awal</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Harga Hasil Negosiasi (Harga Akhir)</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Selisih terhadap HPE</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p class="note">Klarifikasi tidak boleh mengubah substansi penawaran. Harga hasil negosiasi disebut Harga Akhir dan menjadi Harga Kontrak.</p>

            <h3>C. Kesepakatan Teknis dan Waktu Pelaksanaan</h3>
            <ol>
                <li>Lingkup pekerjaan sesuai RKS dan Term of Reference (TOR).</li>
                <li>Jangka waktu pelaksanaan: <span class="fill">..................</span></li>
                <li>Target penyelesaian pengadaan: {{target_penyelesaian}}</li>
                <li><span class="fill">..............................................................................</span></li>
            </ol>

            <h3>D. Kesimpulan</h3>
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
