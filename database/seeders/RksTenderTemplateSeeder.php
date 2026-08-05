<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The official UP Kendari RKS template for procurements carried out by Tender.
 *
 * The source document is a Dokumen Pelelangan Terbuka. Clauses that belong to
 * a single package (lingkup pekerjaan, syarat teknis, spesifikasi) are written
 * as fill blanks pointing at the TOR, so the template stays reusable while the
 * procedural and legal wording is preserved verbatim. Like every other
 * template it remains editable from the Data Master screen.
 */
class RksTenderTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the Tender specific RKS template.
     */
    public function run(): void
    {
        $documentType = DocumentType::query()->where('code', 'rks')->first();
        $method = ProcurementMethod::query()->where('code', 'tender')->first();

        if ($documentType === null || $method === null) {
            $this->command->warn('Jenis dokumen RKS atau metode Tender belum ada, template dilewati.');

            return;
        }

        $body = $this->body();

        DocumentTemplate::query()->updateOrCreate(
            [
                'document_type_id' => $documentType->id,
                'procurement_method_id' => $method->id,
                'version' => 1,
            ],
            [
                'name' => 'RKS Resmi UP Kendari - Metode Tender (Pelelangan Terbuka)',
                'body' => $body,
                'placeholders' => $this->placeholdersIn($body),
                'is_active' => true,
            ],
        );

        $this->command->info('Template RKS untuk metode Tender berhasil dipasang.');
    }

    /**
     * The template body.
     */
    protected function body(): string
    {
        return implode("\n", [
            $this->cover(),
            $this->tableOfContents(),
            $this->babUmum(),
            $this->babInstruksiUmum(),
            $this->babDokumenPelelangan(),
            $this->babPenyiapanPenawaran(),
            $this->babPemasukanPenawaran(),
            $this->babPembukaanDanEvaluasi(),
            $this->babPenetapanDanSanggah(),
            $this->babPenunjukanDanJaminan(),
            $this->babSyaratPerjanjian(),
            $this->babPenutup(),
            $this->lampiran(),
        ]);
    }

    /**
     * Cover page.
     */
    protected function cover(): string
    {
        return <<<'HTML'
        <section class="cover">
            <p class="org">PT PLN NUSANTARA POWER<br>UP KENDARI</p>

            <p class="doc-title">
                DOKUMEN PELELANGAN TERBUKA<br>
                RENCANA KERJA DAN SYARAT-SYARAT (RKS)
            </p>

            <div class="doc-meta">
                <table>
                    <tr><td>NOMOR</td><td>:</td><td class="fill">............ .RKS/612/UPKD/{{tahun}}</td></tr>
                    <tr><td>TANGGAL</td><td>:</td><td>{{tanggal_dokumen}}</td></tr>
                </table>
            </div>

            <p class="subject">
                UNTUK<br>
                {{nama_pengadaan}}<br>
                {{unit_tujuan}}
            </p>

            <p class="footer">UP KENDARI<br>{{tahun}}</p>
        </section>
        HTML;
    }

    /**
     * Contents and annex listing.
     */
    protected function tableOfContents(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h2 class="bab-heading">DAFTAR ISI</h2>
            <ul class="toc">
                <li>BAB I UMUM
                    <ul>
                        <li>A. Dasar Hukum</li>
                        <li>B. Pengertian dan Istilah</li>
                        <li>C. Undangan kepada Calon Penyedia Barang/Jasa</li>
                    </ul>
                </li>
                <li>BAB II INSTRUKSI KEPADA PESERTA PELELANGAN
                    <ul>
                        <li>A. Umum</li>
                        <li>B. Dokumen Pelelangan</li>
                        <li>C. Penyiapan Dokumen Penawaran</li>
                        <li>D. Pemasukan Dokumen Penawaran</li>
                        <li>E. Pembukaan dan Evaluasi Penawaran</li>
                        <li>F. Negosiasi Penawaran dan Klarifikasi</li>
                        <li>G. Penetapan Pemenang Pelelangan</li>
                        <li>H. Masa Sanggah dan Jaminan Sanggah</li>
                        <li>I. Penunjukan Pemenang</li>
                        <li>J. Jaminan Pelaksanaan (Performance Bond)</li>
                        <li>K. Pelelangan Gagal</li>
                        <li>L. Syarat-syarat Perjanjian</li>
                    </ul>
                </li>
                <li>BAB III PENUTUP</li>
            </ul>

            <h3>DAFTAR LAMPIRAN</h3>
            <ul class="toc">
                <li>Lampiran 1 &mdash; Spesifikasi Pekerjaan</li>
                <li>Lampiran 2 &mdash; Spesifikasi Barang/Jasa yang Ditawarkan</li>
                <li>Lampiran 3 &mdash; Contoh Surat Penawaran</li>
                <li>Lampiran 4 &mdash; Contoh Daftar Rincian Harga Penawaran</li>
                <li>Lampiran 5 &mdash; Contoh Surat Pernyataan</li>
                <li>Lampiran 6 &mdash; Contoh Pakta Integritas</li>
                <li>Lampiran 7 &mdash; Contoh Daftar Referensi Pengalaman Pekerjaan</li>
                <li>Lampiran 8 &mdash; Ketentuan Blacklist</li>
                <li>Lampiran 9 &mdash; Contoh Surat Pernyataan Minat</li>
                <li>Lampiran 10 &mdash; Daftar Penerbit Jaminan Terseleksi Pengadaan Barang/Jasa</li>
                <li>Lampiran 11 &mdash; Term of Reference (TOR)</li>
            </ul>
        </section>
        HTML;
    }

    /**
     * BAB I - Umum.
     */
    protected function babUmum(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h2 class="bab-heading">BAB I<br>UMUM</h2>

            <h3>A. Dasar Hukum</h3>
            <p>Dokumen Rencana Kerja dan Syarat-Syarat ini disusun berdasarkan:</p>
            <ol>
                <li>Keputusan Direksi PT PLN Nusantara Power Nomor 0034.P/DIR/2025 tanggal 14 November 2025 tentang Kebijakan Strategis Barang/Jasa PT PLN Nusantara Power.</li>
                <li>Peraturan Direksi PT PLN Nusantara Power Nomor 0035.P/DIR/2025 tanggal 14 November 2025 tentang Kebijakan Pelaksana Pengadaan Barang/Jasa Lainnya PT PLN Nusantara Power.</li>
                <li>Peraturan Direksi PT PLN Nusantara Power Nomor 0038.P/DIR/2025 tanggal 14 November 2025 tentang Kebijakan Pelaksana Pengadaan.</li>
            </ol>

            <h3>B. Pengertian dan Istilah</h3>
            <p>Dalam dokumen ini dipergunakan pengertian, istilah dan singkatan sebagai berikut:</p>
            <ol>
                <li><b>PT PLN NUSANTARA POWER UP KENDARI</b> adalah Unit Pembangkitan milik PT PLN Nusantara Power yang dalam hal ini sebagai Pengguna Barang/Jasa.</li>
                <li><b>APLN NP</b> adalah Anggaran PLN NP yang ditetapkan dalam Rencana Kerja dan Anggaran Perusahaan (RKAP) yang telah disahkan oleh RUPS, termasuk anggaran untuk pekerjaan mendesak atau keadaan Darurat (Emergency) yang belum ditetapkan dalam RKAP.</li>
                <li><b>Barang</b> adalah benda dalam berbagai bentuk dan uraian, meliputi antara lain bahan baku, barang setengah jadi, barang jadi/peralatan yang spesifikasinya ditetapkan oleh Pengguna Barang/Jasa.</li>
                <li><b>Daftar Hitam (Blacklist)</b> adalah sanksi yang diberikan PT PLN NP kepada Penyedia Barang/Jasa berupa tidak diperbolehkannya Penyedia Barang/Jasa tersebut mengikuti pengadaan barang/jasa di wilayah kerja PT PLN NP dan PT PLN (Persero) Group, dalam jangka waktu tertentu, sebagai akibat dari Wanprestasi atas Perjanjian yang dibuat sebelumnya.</li>
                <li><b>Dokumen Penawaran</b> adalah surat penawaran beserta seluruh dokumen lampirannya yang disiapkan oleh Penyedia Barang/Jasa.</li>
                <li><b>Direksi Pekerjaan</b> adalah wakil Pengguna Barang/Jasa untuk mengendalikan pelaksanaan pekerjaan, dalam hal ini adalah Manajer Bidang atau Supervisor Bidang. Pada pengadaan ini Direksi Pekerjaan adalah <b>{{direksi_pekerjaan}}</b>.</li>
                <li><b>Harga Perkiraan Sendiri/HPS</b> adalah perhitungan harga perkiraan dari suatu Barang yang dihitung berdasarkan biaya pokok produksi atau estimasi biaya pokok pekerjaan yang disesuaikan dengan kondisi ekonomi terkini dan faktor-faktor lain yang berfungsi untuk melihat kewajaran harga penawaran.</li>
                <li><b>Jadwal Pengadaan</b> adalah rincian waktu proses pengadaan Barang.</li>
                <li><b>Klarifikasi</b> adalah kegiatan meminta penjelasan oleh Pejabat Pelaksana Pengadaan dan atau Bidang Engineering/User terkait kepada Penyedia Barang/Jasa atas substansi penawaran yang kurang jelas dalam rangka evaluasi penawaran.</li>
                <li><b>Negosiasi</b> adalah kegiatan untuk pembahasan aspek teknis, harga dan waktu pelaksanaan antara Fungsi Pelaksana Pengadaan dengan calon Penyedia Barang/Jasa.</li>
                <li><b>Pakta Integritas</b> adalah surat pernyataan yang berisi ikrar untuk mencegah dan tidak melakukan persekongkolan baik vertikal atau horizontal maupun penyelewengan hukum lainnya, bertindak dengan penuh kehati-hatian sesuai dengan peraturan yang berlaku dalam pelaksanaan Pengadaan Barang/Jasa.</li>
                <li><b>Pengguna Barang/Jasa</b> adalah Direksi atau Pejabat struktural satu tingkat di bawah Direksi, SM/Kepala BPWC atau Pejabat struktural satu tingkat di bawah SM/Kepala BPWC yang diberi kuasa, yang menggunakan dan/atau menerima manfaat baik langsung maupun tidak langsung dari Barang/Jasa yang dihasilkan dari proses pengadaan Barang/Jasa.</li>
                <li><b>Pejabat Yang Berwenang</b> adalah Pejabat yang berwenang menggunakan, mengelola dan memelihara aset perusahaan agar senantiasa dapat dipergunakan dan dimanfaatkan dengan sebaik-baiknya guna menunjang aktivitas perusahaan, dalam hal ini adalah Direksi, Senior Manajer, Pejabat yang diberi kuasa sebagai pemberi tugas kepada pelaksana pengadaan barang/jasa.</li>
                <li><b>Fungsi Perencana Pengadaan</b> adalah struktural yang bertugas dan bertanggung jawab dalam perencanaan Pengadaan Barang/Jasa.</li>
                <li><b>Fungsi Pelaksana Pengadaan</b> adalah struktural yang bertugas dan bertanggung jawab dalam pelaksanaan Pengadaan Barang/Jasa.</li>
                <li><b>Penyedia Barang/Jasa</b> adalah badan usaha yang berbentuk Perseroan Terbatas (PT), Badan Usaha Milik Negara (BUMN), atau badan hukum publik lainnya, yang kegiatan usahanya menyediakan barang/jasa dan sesuai dengan persyaratan yang diminta.</li>
                <li><b>Perjanjian/Kontrak</b> adalah perikatan dalam bentuk tertulis antara Pengguna Barang/Jasa dengan Penyedia Barang/Jasa.</li>
            </ol>

            <h3>C. Undangan kepada Calon Penyedia Barang/Jasa</h3>
            <p>PT PLN NP UP Kendari dengan ini bermaksud mengundang para Calon Penyedia Barang/Jasa yang berkompeten pada pekerjaan <b>{{nama_pengadaan}}</b> pada {{unit_tujuan}} untuk berpartisipasi dalam Pelelangan Terbuka Pascakualifikasi.</p>
            <p>Peserta Pelelangan wajib menyampaikan Dokumen Penawaran sesuai dengan ketentuan yang tercantum dalam Rencana Kerja dan Syarat-syarat (RKS) ini. Dokumen Penawaran yang tidak memenuhi syarat dan ketentuan tersebut maka tidak dapat diikutsertakan dalam proses pelelangan selanjutnya.</p>

            <table>
                <tr><th style="width:36%">Uraian</th><th>Keterangan</th></tr>
                <tr><td>Nomor Pengadaan</td><td>{{nomor_pengadaan}}</td></tr>
                <tr><td>Nomor PR/RO</td><td>{{nomor_pr_ro}}</td></tr>
                <tr><td>Nomor PRK</td><td>{{nomor_prk}}</td></tr>
                <tr><td>Metode Pengadaan</td><td>{{metode_pengadaan}}</td></tr>
                <tr><td>Sumber Anggaran</td><td>{{sumber_anggaran_keterangan}} ({{sumber_anggaran}})</td></tr>
                <tr><td>Nilai HPE/Anggaran</td><td>{{nilai_hpe}}<br><i>Terbilang: {{nilai_hpe_terbilang}}</i></td></tr>
                <tr><td>Target Penyelesaian</td><td>{{target_penyelesaian}}</td></tr>
                <tr><td>Direksi Pekerjaan</td><td>{{direksi_pekerjaan}}</td></tr>
            </table>
        </section>
        HTML;
    }

    /**
     * BAB II section A - Umum.
     */
    protected function babInstruksiUmum(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h2 class="bab-heading">BAB II<br>INSTRUKSI KEPADA PESERTA PELELANGAN</h2>

            <h3>A. UMUM</h3>

            <h4>1. Lingkup Pekerjaan</h4>
            <p><b>{{nama_pengadaan}}</b> dengan detail spesifikasi dan persyaratan teknis yang harus dipenuhi sebagaimana diatur dalam Term of Reference (TOR) pada Lampiran 11 dan Spesifikasi Pekerjaan pada Lampiran 1, yang merupakan bagian tidak terpisahkan dari RKS ini.</p>
            <p>Lokasi pekerjaan dan/atau penyerahan Barang/Jasa adalah {{unit_tujuan}}, sesuai jadwal yang diterbitkan oleh PT PLN NP UP Kendari.</p>

            <h4>2. Sumber Dana</h4>
            <p>Sumber dana untuk keperluan pelaksanaan pekerjaan ini adalah {{sumber_anggaran_keterangan}} ({{sumber_anggaran}}) Tahun {{tahun}}, dengan nilai HPE sebesar {{nilai_hpe}} <i>({{nilai_hpe_terbilang}})</i>.</p>

            <h4>3. Etika Pengadaan</h4>
            <ol>
                <li>Melaksanakan tugas secara tertib, disertai rasa tanggung jawab untuk mencapai sasaran kelancaran dan ketepatan tercapainya tujuan Pengadaan Barang.</li>
                <li>Bekerja secara profesional dan mandiri atas dasar kejujuran serta menjaga kerahasiaan Dokumen Pelelangan yang seharusnya dirahasiakan untuk mencegah terjadinya penyimpangan dalam proses Pengadaan Barang.</li>
                <li>Tidak saling mempengaruhi baik langsung maupun tidak langsung untuk mencegah dan menghindari terjadinya persaingan tidak sehat.</li>
                <li>Menerima dan bertanggung jawab atas segala keputusan yang ditetapkan sesuai dengan kesepakatan para pihak.</li>
                <li>Menghindari dan mencegah terjadinya pertentangan kepentingan (conflict of interest) para pihak yang terkait dalam proses Pengadaan Barang baik langsung maupun tidak langsung yang merugikan kepentingan Pengguna Barang/Jasa.</li>
                <li>Menghindari dan mencegah terjadinya pemborosan dan kebocoran keuangan perusahaan dalam Pengadaan Barang.</li>
                <li>Menghindari dan mencegah penyalahgunaan wewenang dan atau kolusi dengan tujuan untuk kepentingan pribadi, golongan atau pihak lain yang secara langsung atau tidak langsung merugikan perusahaan.</li>
                <li>Tidak menerima, tidak menawarkan dan tidak menjanjikan untuk memberi atau menerima hadiah, imbalan berupa apa saja kepada siapapun yang diketahui atau patut diduga berkaitan dengan Pengadaan Barang.</li>
            </ol>

            <h4>4. Syarat Peserta Pelelangan</h4>
            <ol>
                <li>Perusahaan yang berbadan hukum Perseroan Terbatas (PT). Dapat berupa Kerjasama Operasi / Joint Operation atau perusahaan PMA atau BUMN atau BUMD.</li>
                <li>Memiliki Nomor Induk Berusaha (NIB) / Surat Ijin Usaha Perdagangan (SIUP) yang sesuai dengan bidang pekerjaan dan masih berlaku.</li>
                <li>Memiliki izin usaha dan sertifikasi khusus yang dipersyaratkan untuk bidang pekerjaan ini sebagaimana diuraikan dalam TOR (Lampiran 11).</li>
                <li>Calon Peserta yang diperbolehkan untuk melakukan pendaftaran pelelangan adalah Penyedia Barang/Jasa yang tidak sedang menjalani sanksi Blacklist di lingkungan PT PLN NP dan PT PLN (Persero) Group.</li>
                <li>Calon Peserta tidak dalam pengawasan pengadilan, tidak bangkrut/pailit, kegiatan usahanya tidak sedang dihentikan dan/atau Direksi yang bertindak untuk dan atas nama perusahaan tidak sedang menjalani sanksi pidana.</li>
                <li>Calon Peserta setidaknya harus:
                    <ol>
                        <li>Mempunyai pengalaman minimal <span class="fill">......</span> tahun terakhir pada pekerjaan sejenis sebagaimana dirinci dalam TOR.</li>
                        <li>Memiliki peralatan, armada dan/atau personel dengan spesifikasi minimal sebagaimana dipersyaratkan dalam TOR, dengan awak/personel yang sehat jasmani dan rohani serta memiliki surat izin dan kartu tanda penduduk (KTP) yang masih berlaku.</li>
                        <li>Mampu memberikan garansi/jaminan atas kualitas dan kuantitas Barang/Jasa yang diserahkan kepada PT PLN NP sesuai dengan ketentuan dalam TOR.</li>
                        <li>Memenuhi peraturan pemerintah tentang Lingkungan &amp; K3, dan segala risiko yang timbul terkait pelaksanaan pekerjaan tersebut menjadi tanggung jawab Penyedia Barang/Jasa.</li>
                        <li>Telah memenuhi kewajiban perpajakan tahun terakhir.</li>
                        <li>Bersedia untuk menandatangani Pakta Integritas.</li>
                    </ol>
                </li>
            </ol>

            <h4>5. Dilarang Ikut Sebagai Peserta Pelelangan</h4>
            <ol>
                <li>Mereka yang dinyatakan pailit.</li>
                <li>Mereka yang keikutsertaannya akan bertentangan dengan kepentingan tugasnya (Conflict of Interest).</li>
                <li>Mereka yang keikutsertaannya dalam satu pelelangan berada dalam satu kesatuan pengaruh pemilik modal dan atau kepengurusan dengan Peserta Lelang yang lain (kecuali BUMN/BUMD) sehingga dapat diperkirakan akan dapat terjadi pengaturan/kerjasama di antara para Peserta Lelang atau terjadinya persaingan yang tidak wajar/sehat.</li>
                <li>Apabila peserta Pelelangan Terbuka terbukti berada dalam satu kekuatan pengaruh pemilik modal dan/atau kepengurusan, maka terhadap peserta tersebut dikenakan sanksi Blacklist selama 24 (dua puluh empat) bulan.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II section B - Dokumen Pelelangan.
     */
    protected function babDokumenPelelangan(): string
    {
        return <<<'HTML'
        <section>
            <h3>B. DOKUMEN PELELANGAN</h3>

            <h4>1. Isi Dokumen Pelelangan</h4>
            <p>1.1 Dokumen Pelelangan terdiri dari:</p>
            <ol>
                <li>Instruksi kepada Peserta.</li>
                <li>Persiapan Penawaran.</li>
                <li>Syarat-syarat Perjanjian.</li>
                <li>Jaminan Penawaran dan Jaminan Pelaksanaan.</li>
                <li>Lampiran-lampiran.</li>
            </ol>
            <p>1.2 Peserta berkewajiban memeriksa keseluruhan isi Dokumen Pelelangan. Kelalaian menyampaikan Dokumen Penawaran yang tidak memenuhi persyaratan yang ditetapkan dalam Dokumen Pelelangan ini sepenuhnya merupakan risiko Peserta Pelelangan.</p>

            <h4>2. Bahasa Dokumen Pelelangan</h4>
            <p>Dokumen Pelelangan beserta seluruh korespondensi tertulis dalam proses pengadaan menggunakan Bahasa Indonesia.</p>

            <h4>3. Waktu dan Tempat Pendaftaran serta Pengambilan Dokumen Pelelangan</h4>
            <table>
                <tr><td style="width:30%">Hari, Tanggal</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Waktu</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Website</td><td>https://smartscm.plnnusantarapower.co.id/</td></tr>
                <tr><td>Telp / Fax</td><td>0401-3082256</td></tr>
            </table>

            <h4>4. Pemberian Penjelasan Pelelangan (Aanwijzing)</h4>
            <table>
                <tr><td style="width:30%">Hari, Tanggal</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Waktu</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Tempat</td><td>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara</td></tr>
            </table>
            <ol>
                <li>Peserta Pelelangan yang melakukan pendaftaran atau wakilnya harus menunjukkan tanda pengenal dan surat kuasa bermeterai cukup, serta harus mengisi daftar hadir.</li>
                <li>Semua permintaan penjelasan terhadap isi RKS hanya dapat dilakukan dalam forum Penjelasan Pelelangan (Aanwijzing).</li>
                <li>Ketidakhadiran Peserta Pelelangan pada saat rapat penjelasan tidak dijadikan dasar untuk menggugurkan penawaran.</li>
                <li>Pemberian penjelasan dituangkan dalam Berita Acara Penjelasan Lelang (BAPL) yang ditandatangani oleh Pejabat Pelaksana Pengadaan, Pejabat Perencana Pengadaan, Pengguna Barang/Jasa terkait dan minimal 1 (satu) wakil dari Peserta Pelelangan yang hadir.</li>
                <li>Semua perubahan dalam dokumen pelelangan/RKS sebagai hasil penjelasan dan atau jawaban atas pertanyaan Penyedia Barang/Jasa harus dituangkan dalam Addendum RKS atau Berita Acara Penjelasan Lelang.</li>
                <li>Berita Acara Penjelasan Lelang (BAPL) merupakan bagian tidak terpisahkan dari Dokumen Pelelangan.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II section C - Penyiapan Dokumen Penawaran.
     */
    protected function babPenyiapanPenawaran(): string
    {
        return <<<'HTML'
        <section>
            <h3>C. PENYIAPAN DOKUMEN PENAWARAN</h3>

            <h4>1. Biaya dalam Penyiapan Dokumen Penawaran</h4>
            <ol>
                <li>Peserta Pelelangan menanggung semua biaya dalam penyiapan dan penyampaian Dokumen Penawaran.</li>
                <li>PT PLN NP tidak bertanggung jawab atas kerugian apapun yang ditanggung oleh Peserta Pelelangan.</li>
            </ol>

            <h4>2. Bahasa Dokumen Penawaran</h4>
            <ol>
                <li>Semua Dokumen Penawaran harus menggunakan Bahasa Indonesia.</li>
                <li>Dokumen Penunjang yang terkait dengan Dokumen Penawaran dapat menggunakan Bahasa Indonesia atau Bahasa Asing.</li>
                <li>Dokumen Penunjang yang berbahasa asing perlu disertai penjelasan dalam Bahasa Indonesia. Dalam hal terjadi perbedaan penafsiran maka yang berlaku adalah penjelasan dalam Bahasa Indonesia.</li>
            </ol>

            <h4>3. Dokumen Penawaran</h4>
            <ol>
                <li>Peserta Pelelangan menyampaikan Dokumen Penawaran dalam jumlah 1 (satu) rangkap.</li>
                <li>Informasi yang tercantum dalam penawaran Peserta Pelelangan bersifat rahasia.</li>
                <li>Dokumen Penawaran yang memerlukan pengesahan harus ditandatangani di atas meterai oleh Pimpinan Perusahaan atau yang mendapat kuasa.</li>
                <li>Bagi Pabrikan yang merupakan Perusahaan Asing di luar negeri, maka dokumen penawaran dibuat dan atas nama Pabrikan luar negeri.</li>
                <li>Metode penawaran dilakukan dengan sistem <b>Satu Tahap Dua Sampul</b>. Peserta Pelelangan harus menyerahkan Dokumen Penawaran (Syarat Administrasi, Keuangan, dan Teknik) dalam 1 (satu) sampul, dan Dokumen Harga dalam 1 (satu) sampul lainnya, diserahkan sesuai jadwal yang ditentukan.</li>
            </ol>

            <p><b>3.6 Dokumen Penawaran terdiri dari:</b></p>

            <p><b>3.6.1 Syarat Administrasi</b></p>
            <ol>
                <li>Surat Pernyataan Minat Untuk Mengikuti Pengadaan (Lampiran 9).</li>
                <li>Asli Pakta Integritas (Lampiran 6).</li>
                <li>Asli Surat Pernyataan (Lampiran 5) yang berisi antara lain:
                    <ol>
                        <li>Kesanggupan mematuhi dan memenuhi semua ketentuan yang ditetapkan dalam Dokumen Pelelangan.</li>
                        <li>Kesanggupan memenuhi Persyaratan Teknis/Term of Reference (TOR).</li>
                        <li>Pernyataan bahwa perusahaan yang dipimpin tidak dalam keadaan bangkrut.</li>
                        <li>Pernyataan bahwa direktur perusahaan tidak dalam pengawasan pengadilan dan tidak sedang menjalani sanksi pidana.</li>
                        <li>Pernyataan tidak akan menuntut ganti rugi dalam bentuk apapun jika pelelangan ini dinyatakan batal atau penawaran ditolak.</li>
                    </ol>
                </li>
                <li>Copy Akta Pendirian Perusahaan berikut perubahan-perubahannya (jika ada).</li>
                <li>Copy izin usaha atau Sertifikat Badan Usaha (SBU) sesuai dengan bidang usahanya yang masih berlaku.</li>
                <li>Copy Surat Keterangan Domisili Perusahaan.</li>
                <li>Asli Daftar Susunan Pemilik Saham.</li>
                <li>Asli Daftar Susunan Pengurus Perusahaan.</li>
                <li>Copy NPWP dan PKP.</li>
                <li>Copy bukti Tanda Daftar Perusahaan (TDP) atau Nomor Induk Berusaha (NIB).</li>
                <li>Copy bukti pelunasan kewajiban pajak tahun terakhir (SPT/PPh).</li>
                <li>Copy bukti telah melakukan kewajiban pajak PPh Pasal 23 atau Pasal 25 atau Pasal 29 atau PPN sekurang-kurangnya 3 (tiga) bulan terakhir.</li>
                <li>Asli Perjanjian Kerjasama Operasi / Joint Operation dalam bentuk Akta Notaris (apabila peserta adalah Joint Operation).</li>
            </ol>

            <p><b>3.6.2 Syarat Keuangan</b></p>
            <ol>
                <li>Copy laporan keuangan 2 (dua) tahun terakhir yang telah diaudit oleh akuntan publik dengan opini wajar tanpa pengecualian dan/atau wajar dengan pengecualian, dan/atau dokumen pemeringkat keuangan atau hasil rating dari D&amp;B (Dun&amp;Bradstreet) atau pemeringkatan yang setara.</li>
                <li>Copy rekening koran perusahaan terbaru dari Bank sekurang-kurangnya 3 (tiga) bulan terakhir tahun berjalan.</li>
            </ol>

            <p><b>3.6.3 Syarat Teknis</b></p>
            <ol>
                <li>Daftar pengalaman pekerjaan sejenis sesuai ketentuan pada butir A.4 dan TOR (Lampiran 7).</li>
                <li>Copy izin, sertifikat dan persetujuan teknis yang dipersyaratkan dalam TOR (Lampiran 11) yang masih berlaku.</li>
                <li>Copy surat kepemilikan dan/atau penguasaan peralatan/armada dengan kapasitas minimal sebagaimana dipersyaratkan dalam TOR.</li>
                <li>Copy sertifikat kelaikan operasi dan sertifikat kalibrasi/tera peralatan yang masih berlaku dari badan sertifikasi yang berkompeten, apabila dipersyaratkan.</li>
                <li>Copy surat izin dan tanda pengenal personel/operator yang masih berlaku, apabila dipersyaratkan.</li>
                <li>Copy sertifikat CSMS PT PLN NP sesuai kategori risiko pekerjaan.</li>
                <li>Spesifikasi Barang/Jasa yang ditawarkan (Lampiran 2).</li>
            </ol>

            <p><b>3.7 Syarat Dokumen Harga</b></p>
            <ol>
                <li>Asli Jaminan Penawaran dari Bank Umum (tidak termasuk Bank Perkreditan Rakyat dan Perusahaan Asuransi).</li>
                <li>Surat Penawaran Harga beserta total harga penawaran (Lampiran 3).</li>
                <li>Daftar rincian harga penawaran (Lampiran 4).</li>
            </ol>
            <p class="note">Catatan: seluruh berkas lampiran Dokumen Penawaran tersebut di atas agar disusun secara urut.</p>

            <h4>4. Harga Penawaran</h4>
            <ol>
                <li>Harga penawaran adalah harga pekerjaan pada Unit Pengguna Barang/Jasa.</li>
                <li>Harga penawaran ditulis dalam angka dan huruf. Apabila terdapat perbedaan antara penulisan nilai dalam angka dan huruf, maka nilai penawaran yang diakui adalah nilai dalam tulisan huruf.</li>
                <li>Harga penawaran sudah termasuk pajak-pajak yang berlaku.</li>
            </ol>

            <h4>5. Mata Uang Penawaran dan Cara Pembayaran</h4>
            <ol>
                <li>Semua harga dalam penawaran harus dalam bentuk mata uang Rupiah (IDR).</li>
                <li>Cara pembayaran atas pelaksanaan pengadaan diuraikan sesuai ketentuan dalam RKS ini.</li>
            </ol>

            <h4>6. Masa Berlaku Penawaran</h4>
            <p>Masa berlaku penawaran adalah selama 90 (sembilan puluh) hari kalender terhitung sejak tanggal Pembukaan Penawaran.</p>

            <h4>7. Jaminan Penawaran (Bid Bond)</h4>
            <p>7.1 Peserta menyerahkan Jaminan Penawaran dalam mata uang Rupiah (IDR) dengan nominal minimal sebesar <b>1% (satu persen)</b> dari nilai penawaran (sudah termasuk PPN yang berlaku).</p>
            <p>7.2 Jaminan Penawaran memenuhi ketentuan sebagai berikut:</p>
            <ol>
                <li>Berupa Bank Garansi yang diterbitkan oleh Bank Umum (tidak termasuk Bank Perkreditan Rakyat dan Perusahaan Asuransi), sesuai Daftar Penerbit Jaminan Terseleksi pada Lampiran 10.</li>
                <li>Jaminan Penawaran yang diterbitkan harus mempunyai syarat-syarat sekurang-kurangnya sebagai berikut:
                    <ol>
                        <li>Judul jaminan adalah &ldquo;Garansi Bank&rdquo; atau &ldquo;Bank Garansi&rdquo;.</li>
                        <li>Nama dan alamat jelas Bank Penerbit (Penjamin).</li>
                        <li>Nama dan alamat jelas Pemberi Pekerjaan (Pemegang Jaminan).</li>
                        <li>Nama dan alamat jelas Penyedia Barang/Jasa (Yang Dijamin).</li>
                        <li>Nama paket pekerjaan yang dijamin.</li>
                        <li>Besar jumlahnya jaminan dalam angka dan huruf.</li>
                        <li>Pernyataan pihak Penjamin bahwa Jaminan Penawaran dapat dicairkan dengan segera sesuai ketentuan dalam Jaminan Penawaran.</li>
                        <li>Masa berlaku surat Jaminan Penawaran.</li>
                        <li>Batas akhir waktu pengajuan tuntutan pencairan surat Jaminan Penawaran oleh Pengguna Barang/Jasa kepada pihak Penjamin.</li>
                        <li>Mengesampingkan ketentuan Pasal 1831 Kitab Undang-undang Hukum Perdata, mengacu ketentuan Pasal 1832 Kitab Undang-undang Hukum Perdata.</li>
                        <li>Tanda tangan pihak Penjamin.</li>
                    </ol>
                </li>
                <li>Masa berlakunya Jaminan Penawaran sekurang-kurangnya 30 (tiga puluh) hari kalender setelah masa berlakunya penawaran atau 4 (empat) bulan sejak tanggal pembukaan surat penawaran.</li>
                <li>Tercantum nama dan alamat:
                    <table>
                        <tr><td style="width:24%">Nama</td><td>PT PLN NUSANTARA POWER UP KENDARI</td></tr>
                        <tr><td>Alamat</td><td>Jl. Chairil Anwar Nomor 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara</td></tr>
                        <tr><td>Jaminan</td><td>{{nama_pengadaan}}</td></tr>
                    </table>
                </li>
                <li>Asli Jaminan Penawaran harus diserahkan kepada Pelaksana Pengadaan pada saat penyampaian Dokumen Penawaran.</li>
                <li>Dalam hal masa berlaku Jaminan Penawaran diperkirakan berakhir sebelum penandatanganan Perjanjian, maka paling lambat 7 (tujuh) hari kerja sebelum berakhirnya masa berlaku Jaminan Penawaran tersebut, Pejabat Pelaksana Pengadaan dapat meminta Peserta Pelelangan untuk memperpanjang Jaminan Penawaran.</li>
                <li>Peserta Pelelangan dianggap mengundurkan diri, Jaminan Penawaran dicairkan dan menjadi milik PT PLN NP, serta diusulkan Blacklist sesuai ketentuan yang berlaku di PT PLN NP apabila:
                    <ol>
                        <li>Peserta Pelelangan tidak bersedia memperpanjang Jaminan Penawaran setelah diminta Pejabat Pelaksana Pengadaan.</li>
                        <li>Peserta Pelelangan telah ditunjuk sebagai Penyedia Barang/Jasa dan tidak bersedia memperpanjang Jaminan Penawaran sampai dengan penandatanganan Perjanjian.</li>
                        <li>Calon Penyedia Barang/Jasa mengundurkan diri sebelum penandatanganan Perjanjian.</li>
                    </ol>
                </li>
                <li>Jaminan Penawaran harus dapat dicairkan tanpa syarat (unconditional) sebesar nilai jaminan dalam waktu paling lambat 14 (empat belas) hari kerja setelah surat pernyataan Wanprestasi dari Pejabat Pelaksana Pengadaan diterima oleh Penerbit Jaminan.</li>
                <li>Jaminan Penawaran akan dikembalikan kepada Peserta Pelelangan setelah dikeluarkannya Surat Penunjukan Penyedia Barang/Jasa (SPPBJ), dalam hal:
                    <ol>
                        <li>Untuk Peserta Pelelangan yang ditunjuk sebagai Pemenang Pengadaan, ditukar dengan Jaminan Pelaksanaan pada saat akan menandatangani Perjanjian/Kontrak.</li>
                        <li>Untuk Peserta Pelelangan dengan Harga Penawaran terendah kedua, ketiga, dan seterusnya, dikembalikan setelah ada pengumuman Penunjukan Pemenang dari Pejabat yang Berwenang.</li>
                    </ol>
                </li>
            </ol>
            <p>7.3 Jaminan Penawaran tidak dipersyaratkan untuk PT PLN (Persero) / Anak Perusahaan PT PLN (Persero) / Perusahaan Terafiliasi PT PLN (Persero) / Anak Perusahaan PT PLN NP / Perusahaan Terafiliasi PT PLN NP.</p>
        </section>
        HTML;
    }

    /**
     * BAB II section D - Pemasukan Dokumen Penawaran.
     */
    protected function babPemasukanPenawaran(): string
    {
        return <<<'HTML'
        <section>
            <h3>D. PEMASUKAN DOKUMEN PENAWARAN</h3>

            <h4>1. Penyampaian Dokumen Penawaran</h4>
            <p><b>1.1 Pemasukan penawaran</b></p>
            <ol>
                <li>Peserta Pelelangan menyampaikan Dokumen Penawaran yang dilengkapi dengan Nomor dan Tanggal Surat Penawaran.</li>
                <li>Tanggal Surat Penawaran harus dalam rentang waktu pemasukan penawaran.</li>
                <li>Surat Penawaran harus ditandatangani oleh Pimpinan Perusahaan atau penerima kuasa dari Pimpinan Perusahaan kepada nama yang tercantum di dalam akta pendirian perusahaan/perubahannya, atau Kepala Cabang Perusahaan yang diangkat oleh Kantor Pusat dan dibuktikan dengan dokumen otentik, bertanggal, bermeterai Rp 10.000,- (sepuluh ribu rupiah), dan distempel.</li>
            </ol>

            <p><b>1.2 Penyampulan dan Penyampaian Dokumen Penawaran</b></p>
            <ol>
                <li>Peserta Pelelangan menyampaikan Dokumen Penawaran dalam jumlah 2 (dua) rangkap dalam 1 (satu) sampul.</li>
                <li>Penyampaian dokumen penawaran dilakukan dengan diunggah ke website https://smartscm.plnnusantarapower.co.id pada paket pekerjaan {{nama_pengadaan}}, serta mengirimkan dokumen hardcopy ke alamat PT PLN Nusantara Power UP Kendari, Jl. Chairil Anwar No. 2, Kelurahan Mataiwoi, Kecamatan Wua-Wua, Kota Kendari, Provinsi Sulawesi Tenggara, Kode Pos 93118, hingga batas waktu penyampaian dokumen penawaran.</li>
                <li>Sampul terbuat dari bahan tidak tembus pandang.</li>
                <li>Pada sisi depan kanan bawah sampul penutup dicantumkan alamat tujuan, dan pada sisi depan kiri atas dicantumkan nama paket pekerjaan beserta nomor dan tanggal RKS:
                    <table>
                        <tr>
                            <td style="width:50%"><b>Sisi kiri atas</b><br>{{nama_pengadaan}}<br>RKS Nomor: <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal: {{tanggal_dokumen}}</td>
                            <td><b>Sisi kanan bawah</b><br>KEPADA:<br>PELAKSANA PENGADAAN BARANG/JASA<br>PT PLN NUSANTARA POWER UP KENDARI<br>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara, 93118</td>
                        </tr>
                    </table>
                </li>
                <li>Apabila Dokumen Penawaran disampaikan secara langsung, maka dokumen penawaran harus dimasukkan oleh Peserta Pelelangan yang bersangkutan ke dalam tempat yang telah disediakan oleh Pejabat Pelaksana Pengadaan.</li>
                <li>Apabila Dokumen Penawaran disampaikan melalui pos atau jasa pengiriman, sampul sebagaimana dimaksud dimasukkan ke dalam sampul luar yang hanya mencantumkan alamat pelaksanaan pengadaan serta tempat, hari, tanggal, bulan dan tahun.</li>
            </ol>

            <h4>2. Batas Waktu Penyampaian Dokumen Penawaran</h4>
            <table>
                <tr><td style="width:30%">Hari, Tanggal</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Waktu</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Tempat</td><td>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara, 93118<br>Up: Fungsi Pelaksana Pengadaan</td></tr>
                <tr><td>Telp / Fax</td><td>0401-3082256</td></tr>
            </table>

            <h4>3. Perubahan dan Keterlambatan Dokumen Penawaran</h4>
            <ol>
                <li>Perubahan penawaran dapat dilakukan sebelum batas akhir waktu pemasukan penawaran.</li>
                <li>Penarikan penawaran tidak dapat dilakukan setelah batas akhir waktu pemasukan penawaran. Apabila dilakukan maka Jaminan Penawaran dicairkan dan menjadi milik PT PLN NP.</li>
                <li>Dokumen Penawaran yang diterima setelah batas waktu pemasukan penawaran tidak diikutsertakan.</li>
                <li>Peserta Pelelangan yang mendaftar untuk ikut pelelangan namun tidak memasukkan Dokumen Penawaran tanpa alasan profesional dikenakan sanksi Blacklist selama 6 (enam) bulan.</li>
                <li>PT PLN Nusantara Power UP Kendari tidak memberikan ganti rugi kepada Peserta Lelang bila penawarannya ditolak atau proses pelelangan dinyatakan gagal/batal.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II sections E and F - Pembukaan, evaluasi, negosiasi.
     */
    protected function babPembukaanDanEvaluasi(): string
    {
        return <<<'HTML'
        <section>
            <h3>E. PEMBUKAAN DAN EVALUASI PENAWARAN</h3>

            <h4>1. Pembukaan Penawaran</h4>
            <table>
                <tr><td style="width:30%">Hari, Tanggal</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Waktu</td><td>Sesuai Jadwal</td></tr>
                <tr><td>Tempat</td><td>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara, 93118<br>Up: Fungsi Pelaksana Pengadaan</td></tr>
            </table>
            <p><b>1.2 Pembukaan penawaran dengan Metode Satu Tahap Dua Sampul:</b></p>
            <ol>
                <li>Pejabat Pelaksana Pengadaan menghitung jumlah sampul yang masuk, sedangkan surat pengunduran diri tidak dihitung sebagai penawaran yang masuk.</li>
                <li>Pejabat Pelaksana Pengadaan membuka Sampul Pertama, untuk selanjutnya diperiksa kelengkapan (ADA/TIDAK) persyaratan dokumen penawaran yang diminta, yang kemudian akan dihasilkan keputusan LENGKAP/TIDAK LENGKAP-nya dokumen penawaran tersebut.</li>
                <li>Apabila persyaratan dokumen penawaran pada Sampul Pertama dinilai TIDAK LENGKAP, maka tidak dilanjutkan ke tahap pembukaan Sampul Kedua.</li>
                <li>Dalam hal penawaran dinyatakan TIDAK LENGKAP pada saat pemeriksaan kelengkapan dan/atau dinyatakan TIDAK MEMENUHI pada saat Evaluasi, penawaran tersebut dinyatakan gugur.</li>
                <li>Pembukaan Penawaran dilakukan di hadapan Peserta Pelelangan yang hadir serta disaksikan minimal 2 (dua) orang saksi dari wakil Peserta Pelelangan, untuk selanjutnya dibacakan serta dicatat dan dijadikan lampiran Berita Acara Pembukaan Penawaran.</li>
                <li>Dalam hal saksi dari wakil Peserta Pelelangan tidak ada, Pejabat Pelaksana Pengadaan dapat menunjuk saksi di luar dari Pejabat Pelaksana Pengadaan.</li>
                <li>Membuat Berita Acara Pembukaan Penawaran (BAPP) yang berisikan hal-hal dan data-data pokok yang penting termasuk informasi yang diperoleh pada saat pembukaan penawaran.</li>
                <li>Menandatangani BAPP bersama 2 (dua) orang saksi dari Peserta Pelelangan yang hadir.</li>
            </ol>

            <h4>2. Evaluasi Dokumen Penawaran</h4>
            <ol>
                <li>Evaluasi Dokumen Penawaran dilakukan terhadap sampul yang masuk.</li>
                <li>Evaluasi terhadap masing-masing persyaratan dilakukan dengan menggunakan metode penilaian <b>SISTEM GUGUR</b>.</li>
                <li>Evaluasi dilakukan berdasarkan data dan informasi yang ada dalam Dokumen Penawaran yang telah diisi oleh Peserta Pelelangan.</li>
                <li>Evaluasi Sampul menghasilkan 2 (dua) kesimpulan, yaitu Memenuhi atau Tidak Memenuhi.</li>
            </ol>

            <p><b>Evaluasi Kualifikasi dinyatakan tidak memenuhi persyaratan atau Gugur apabila:</b></p>

            <p><b>a. Syarat Administrasi</b></p>
            <ol>
                <li>Tidak menyampaikan kelengkapan dokumen yang dipersyaratkan dalam Syarat Administrasi sebagaimana dimaksud dalam RKS ini dan perubahannya (apabila ada) yang dituangkan dalam Berita Acara Penjelasan Lelang.</li>
                <li>Pemilik modal atau pengurus suatu perusahaan Calon Penyedia Barang/Jasa menjadi pemilik modal dan/atau pengurus perusahaan lain sesama Calon Penyedia Barang/Jasa.</li>
                <li>Tidak bisa membuktikan keabsahan dokumen yang disampaikan.</li>
            </ol>

            <p><b>b. Syarat Keuangan</b></p>
            <ol>
                <li>Tidak menyampaikan kelengkapan dokumen yang dipersyaratkan dalam Syarat Keuangan sebagaimana dimaksud dalam RKS ini dan perubahannya (apabila ada) yang dituangkan dalam Berita Acara Penjelasan Lelang.</li>
                <li>Tidak bisa membuktikan keabsahan dokumen yang disampaikan.</li>
            </ol>

            <p><b>c. Syarat Teknis</b></p>
            <ol>
                <li>Tidak menyampaikan kelengkapan dokumen yang dipersyaratkan dalam Syarat Teknis sebagaimana dimaksud dalam RKS ini dan perubahannya (apabila ada) yang dituangkan dalam Berita Acara Penjelasan Lelang.</li>
                <li>Spesifikasi teknis, ruang lingkup pekerjaan, pengalaman kerja, dan hal-hal lainnya yang disampaikan oleh peserta lelang tidak relevan dengan yang dipersyaratkan dalam RKS, TOR dan perubahannya (apabila ada) serta Berita Acara Penjelasan Lelang.</li>
                <li>Tidak bisa membuktikan keabsahan dokumen yang disampaikan pada saat klarifikasi teknik.</li>
            </ol>

            <p><b>d. Evaluasi Harga Penawaran</b></p>
            <ol>
                <li>Tidak menyampaikan kelengkapan dokumen yang dipersyaratkan dalam Syarat Penawaran Harga sebagaimana dimaksud dalam RKS ini dan perubahannya (apabila ada) yang dituangkan dalam Berita Acara Penjelasan Lelang.</li>
                <li>Jaminan Penawaran dinyatakan tidak memenuhi persyaratan jika:
                    <ol>
                        <li>Nilai jaminan kurang dari 1% dari nilai penawaran.</li>
                        <li>Besar jumlahnya jaminan dalam angka dan huruf tidak sama.</li>
                        <li>Nama dan alamat jelas pemberi pekerjaan tidak sesuai dengan yang telah ditentukan.</li>
                        <li>Nama paket pekerjaan yang dijamin tidak sesuai dengan yang telah ditentukan.</li>
                        <li>Jangka waktu berlakunya Jaminan Penawaran kurang dari 120 (seratus dua puluh) hari kalender terhitung sejak tanggal pembukaan penawaran.</li>
                    </ol>
                </li>
                <li>Dalam hal terjadi perbedaan antara harga penawaran yang tercantum dalam Surat Penawaran dengan Rincian Penawaran, maka yang berlaku adalah harga penawaran yang tercantum pada Surat Penawaran bermeterai cukup (Rp 10.000,-).</li>
                <li>Harga penawaran ditulis dalam angka dan huruf. Apabila terdapat perbedaan antara penulisan nilai dalam angka dan huruf, maka nilai penawaran yang diakui adalah nilai dalam tulisan huruf.</li>
                <li>Evaluasi penawaran harga dilakukan dengan metode Sistem Gugur. Penawaran harga akan dibandingkan dengan HPS yang telah ditetapkan. Penawaran harga yang di atas HPS tidak menggugurkan dan tetap akan dievaluasi.</li>
                <li>Proses pengadaan dapat dilanjutkan dengan Klarifikasi dan Negosiasi sesuai ketentuan dalam RKS ini.</li>
                <li>Bilamana dipandang perlu, PT PLN NP dapat meminta Calon Penyedia Barang/Jasa untuk melengkapi data isian formulir kualifikasi tambahan. Apabila tidak dipenuhi maka menjadi risiko Calon Penyedia Barang/Jasa.</li>
                <li>Apabila ditemui data/keterangan yang disampaikan tidak benar dan ada pemalsuan, maka Calon Penyedia Barang/Jasa digugurkan dan dimasukkan dalam daftar hitam (Blacklist) serta tidak diperkenankan ikut serta dalam pengadaan barang/jasa di lingkungan PT PLN NP dan PT PLN (Persero) Group selama 24 (dua puluh empat) bulan.</li>
            </ol>

            <h3>F. NEGOSIASI PENAWARAN DAN KLARIFIKASI</h3>

            <h4>1. Negosiasi Penawaran</h4>
            <ol>
                <li>Negosiasi dilakukan untuk mencapai kesepakatan antara PT PLN NP dengan Penyedia Barang/Jasa dalam teknis, waktu pelaksanaan, dan harga terbaik.</li>
                <li>Negosiasi dilakukan oleh Pelaksana Pengadaan dengan Direktur Utama/pimpinan perusahaan; penerima kuasa dari Direktur Utama/pimpinan perusahaan yang penerima kuasanya tercantum dalam Akta Pendirian atau perubahannya; atau Kepala Cabang perusahaan yang diangkat oleh kantor pusat yang dibuktikan dengan dokumen otentik.</li>
                <li>Negosiasi dilakukan dengan ketentuan sebagai berikut:
                    <ol>
                        <li>Dalam hal seluruh penawaran harga Peserta Lelang &gt; HPS, maka proses dilanjutkan dengan negosiasi kepada Calon Penyedia Barang/Jasa dengan Harga Penawaran terendah pertama yang telah dinyatakan MEMENUHI persyaratan Administrasi, Keuangan, dan Teknik.</li>
                        <li>Apabila proses negosiasi tidak mencapai kesepakatan/tetap &gt; HPS, maka Calon Penyedia Barang/Jasa dengan Harga Penawaran terendah pertama tersebut dinyatakan TIDAK MEMENUHI, selanjutnya akan dilakukan evaluasi terhadap Peserta Lelang dengan Harga Penawaran terendah kedua.</li>
                        <li>Tahapan kondisi di atas juga berlaku untuk Peserta Lelang dengan Harga Penawaran terendah ketiga, keempat, dan seterusnya.</li>
                        <li>Apabila setelah dilakukan negosiasi Harga Penawaran masih tetap di atas HPS, maka Pengadaan dinyatakan GAGAL.</li>
                        <li>Dalam hal seluruh atau sebagian Penawaran Harga &le; HPS, maka tetap akan dilakukan negosiasi kepada Calon Penyedia Barang/Jasa dengan Harga Penawaran terendah pertama.</li>
                    </ol>
                </li>
                <li>Terhadap harga penawaran yang telah dilakukan negosiasi dan telah memenuhi ketentuan di atas, disebut sebagai <b>Harga Akhir</b> dan akan menjadi Harga Kontrak.</li>
            </ol>

            <h4>2. Klarifikasi</h4>
            <ol>
                <li>Terdapat penawaran yang tidak wajar, yaitu dengan nilai penawaran 80% di bawah HPS, maka Pelaksana Pengadaan akan meminta penjelasan/klarifikasi secara tertulis kepada Calon Penyedia Barang/Jasa yang bersangkutan.</li>
                <li>Hasil pelaksanaan Klarifikasi dan Negosiasi dituangkan dalam Berita Acara Klarifikasi dan Negosiasi.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II sections G and H - Penetapan pemenang and sanggah.
     */
    protected function babPenetapanDanSanggah(): string
    {
        return <<<'HTML'
        <section>
            <h3>G. PENETAPAN PEMENANG PELELANGAN</h3>

            <h4>1. Penetapan Pemenang</h4>
            <ol>
                <li>Pemenang dalam pelelangan ini adalah Peserta Pelelangan yang lulus evaluasi serta menyetujui Harga Akhir.</li>
                <li>PT PLN Nusantara Power UP Kendari menetapkan Pemenang pengadaan dan mengeluarkan Surat Penetapan Penyedia Barang/Jasa.</li>
                <li>Apabila terjadi keterlambatan dalam menetapkan Pemenang pengadaan dan mengakibatkan Penawaran/Jaminan Penawaran habis masa berlakunya, maka diminta kepada seluruh Peserta Pelelangan yang memasukkan dokumen penawaran untuk memperpanjang masa berlaku Surat Penawaran dan Jaminan Penawaran.</li>
            </ol>

            <h4>2. Pengumuman Pemenang</h4>
            <p>Hasil penetapan Pemenang Pelelangan akan diumumkan kepada semua Peserta Pelelangan yang telah memasukkan Penawaran.</p>

            <h3>H. MASA SANGGAH DAN JAMINAN SANGGAH</h3>
            <ol>
                <li>Peserta Pelelangan yang berkeberatan atas penetapan Calon Pemenang diberikan kesempatan untuk mengajukan Sanggahan secara tertulis yang berkaitan dengan kesesuaian pelaksanaan pengadaan dengan prosedur atau tata cara pengadaan dalam Dokumen Pelelangan/RKS.</li>
                <li>Sanggahan ditujukan kepada Pejabat yang Berwenang, dalam hal ini Senior Manager PT PLN Nusantara Power UP Kendari, dengan tembusan kepada Kepala Satuan Pengawasan Intern (KSPI).</li>
                <li>Sanggahan yang disampaikan pihak lain di luar peserta tidak akan dijawab, dan Peserta Pelelangan yang menggunakan pihak lain untuk menyampaikan sanggahan dan/atau mempengaruhi pihak PT PLN Nusantara Power UP Kendari akan menjadi catatan itikad tidak baik atas Penyedia Barang/Jasa tersebut.</li>
                <li>Jangka waktu penyampaian sanggahan maksimal <b>3 (tiga) hari kerja</b> sejak diumumkannya Pemenang Pengadaan, dengan memberikan Jaminan Sanggah berupa Bank Garansi yang diterbitkan oleh Bank Umum (tidak termasuk Bank Perkreditan Rakyat dan Perusahaan Asuransi), sebesar <b>2&permil; (dua perseribu)</b> dari nilai Penawaran atau setinggi-tingginya sebesar Rp 50.000.000,- (lima puluh juta rupiah).</li>
                <li>Pejabat yang Berwenang wajib memberikan jawaban sanggah secara tertulis atas substansi masalah yang disanggah disertai bukti-bukti secara proporsional sesuai dengan masalahnya selambat-lambatnya dalam 7 (tujuh) hari kerja sejak tanggal diterimanya pengajuan sanggah.</li>
                <li>Dalam hal sanggahan ditolak oleh Pejabat yang Berwenang, maka Penyedia Barang/Jasa dapat mengajukan Sanggah Banding kepada Direktur Utama PT PLN NP, dengan tembusan kepada Kepala Satuan Pengawasan Intern (KSPI), selambat-lambatnya dalam waktu 3 (tiga) hari kerja sejak diterimanya jawaban sanggah, dengan disertai bukti-bukti terjadinya penyimpangan terhadap ketentuan-ketentuan pengadaan.</li>
                <li>Pejabat yang Berwenang wajib memberikan jawaban tertulis atas Sanggah Banding selambat-lambatnya 7 (tujuh) hari kerja sejak tanggal pengajuan sanggah banding diterima.</li>
                <li>Sanggah/Sanggah Banding hanya diberikan 1 (satu) kali dan jawaban atas Sanggah Banding bersifat final dan mengikat.</li>
                <li>Apabila isi dari Sanggah/Sanggah Banding dinyatakan benar, maka akan dilakukan penilaian kembali atau dilakukan pengumuman pelelangan ulang. Pelaksana Pengadaan wajib mengembalikan Jaminan Sanggah.</li>
                <li>Apabila isi dari Sanggah/Sanggah Banding dinyatakan tidak benar dan cenderung mengada-ada, maka kepada yang bersangkutan tidak diikutsertakan pada proses pelelangan, Jaminan Sanggah akan dicairkan dan sepenuhnya menjadi milik PT PLN NP. Kepada Penyedia Barang/Jasa tersebut dikenakan sanksi Blacklist selama 12 (dua belas) bulan.</li>
                <li>Peserta Pelelangan yang keberatan dan tidak mengajukan sanggahan secara tertulis tetapi justru menyebarkan ke publik dapat dikenakan sanksi Blacklist selama 24 (dua puluh empat) bulan, dan apabila ternyata mengada-ada maka dikenakan sanksi Blacklist selama 60 (enam puluh) bulan.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II sections I, J and K - Penunjukan, jaminan pelaksanaan, gagal.
     */
    protected function babPenunjukanDanJaminan(): string
    {
        return <<<'HTML'
        <section>
            <h3>I. PENUNJUKAN PEMENANG</h3>

            <h4>1. Penunjukan Penyedia Barang/Jasa</h4>
            <ol>
                <li>Pejabat yang Berwenang akan menerbitkan Surat Penunjukan Penyedia Barang/Jasa (SPPBJ) dengan ketentuan:
                    <ol>
                        <li>Setelah tidak ada sanggah/sanggah banding dari Peserta Pelelangan.</li>
                        <li>Sanggah/sanggah banding yang diterima dalam masa sanggah terbukti tidak benar.</li>
                        <li>Masa sanggah berakhir.</li>
                    </ol>
                </li>
                <li>Calon Penyedia Barang/Jasa yang ditunjuk sebagai Penyedia Barang/Jasa wajib menerima keputusan tersebut. Apabila yang bersangkutan mengundurkan diri, maka Jaminan Penawaran yang bersangkutan akan dicairkan dan menjadi milik PT PLN NP, serta akan diberikan sanksi Blacklist selama 60 (enam puluh) bulan.</li>
                <li>Apabila Calon Penyedia Barang/Jasa yang ditunjuk mengundurkan diri dan/atau tidak bersedia menerima penunjukan tersebut, maka Pelaksana Pengadaan akan melakukan evaluasi kepada Peserta Lelang dengan Penawaran Harga terendah kedua sesuai dengan harga yang bersangkutan, dengan ketentuan:
                    <ol>
                        <li>Masa berlaku penawaran dan Jaminan Penawaran milik Peserta Lelang dengan Penawaran Harga terendah kedua masih berlaku, atau sudah diperpanjang masa berlakunya; atau</li>
                        <li>Apabila sudah tidak berlaku, terlebih dahulu memperpanjang masa berlaku penawaran dan menyerahkan Jaminan Penawaran yang baru.</li>
                    </ol>
                </li>
                <li>Apabila Peserta Lelang dengan Penawaran Harga terendah kedua tidak bersedia untuk dilakukan evaluasi, maka akan dilakukan evaluasi kepada Peserta Lelang dengan Penawaran Harga terendah ketiga (bila ada) sesuai dengan harga yang bersangkutan dan melalui tahapan yang sama, dan seterusnya.</li>
                <li>Setelah tahapan tersebut terpenuhi, maka selanjutnya akan dilakukan tahap Klarifikasi dan Negosiasi sesuai dengan ketentuan dalam RKS ini.</li>
            </ol>

            <h4>2. BAHP, Berita Acara Lainnya dan Kerahasiaan Proses</h4>
            <p>Evaluasi penawaran yang disimpulkan dalam Berita Acara Hasil Pelelangan (BAHP) oleh Pejabat Pelaksana Pengadaan bersifat rahasia sampai dengan saat pengumuman pemenang.</p>

            <h3>J. JAMINAN PELAKSANAAN (PERFORMANCE BOND)</h3>
            <ol>
                <li>Jaminan Pelaksanaan dipersyaratkan untuk proses pengadaan barang/jasa melalui metode Lelang Terbuka.</li>
                <li>Asli Jaminan Pelaksanaan harus diserahkan kepada Pelaksana Pengadaan oleh Penyedia Barang/Jasa sebelum penandatanganan Perjanjian atau selambat-lambatnya 14 (empat belas) hari kerja sejak diterbitkannya Surat Penunjukan Penyedia Barang/Jasa (SPPBJ).</li>
                <li>Apabila dalam waktu 14 (empat belas) hari kerja tersebut Penyedia Barang/Jasa tidak menyerahkan atau tidak bersedia menyerahkan Jaminan Pelaksanaan, maka Penyedia Barang/Jasa dianggap mengundurkan diri dari penunjukan, sehingga Jaminan Penawaran akan dicairkan dan menjadi milik PT PLN NP, dan terhadap Penyedia Barang/Jasa tersebut diberikan sanksi Blacklist selama 24 (dua puluh empat) bulan.</li>
                <li>Besaran nilai Jaminan Pelaksanaan adalah <b>minimal 5% (lima persen)</b> dari nilai Perjanjian/Kontrak.</li>
                <li>Jaminan Pelaksanaan (Performance Bond) yang sah adalah berupa Bank Garansi yang diterbitkan oleh Bank Umum (tidak termasuk Bank Perkreditan Rakyat dan Perusahaan Asuransi), dengan tujuan jaminan kepada:
                    <table>
                        <tr><td style="width:24%">Nama</td><td>PT PLN NUSANTARA POWER UP KENDARI</td></tr>
                        <tr><td>Alamat</td><td>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara, 93118</td></tr>
                        <tr><td>Jaminan</td><td>{{nama_pengadaan}}</td></tr>
                    </table>
                </li>
                <li>Jaminan Pelaksanaan yang diterbitkan harus mempunyai syarat-syarat sekurang-kurangnya sebagai berikut:
                    <ol>
                        <li>Judul jaminan adalah &ldquo;Garansi Bank&rdquo; atau &ldquo;Bank Garansi&rdquo;.</li>
                        <li>Nama dan alamat jelas Bank Penerbit (Penjamin).</li>
                        <li>Nama dan alamat jelas Pemberi Pekerjaan (Pemegang Jaminan).</li>
                        <li>Nama dan alamat jelas Penyedia Barang/Jasa (Yang Dijamin).</li>
                        <li>Nama paket pekerjaan yang dijamin.</li>
                        <li>Besar jumlahnya jaminan dalam angka dan huruf.</li>
                        <li>Pernyataan pihak Penjamin bahwa Jaminan Pelaksanaan dapat dicairkan dengan segera sesuai ketentuan dalam Jaminan Pelaksanaan.</li>
                        <li>Masa berlaku surat Jaminan Pelaksanaan.</li>
                        <li>Batas akhir waktu pengajuan tuntutan pencairan surat Jaminan Pelaksanaan oleh Pengguna Barang/Jasa kepada pihak Penjamin.</li>
                        <li>Mengesampingkan ketentuan Pasal 1831 Kitab Undang-undang Hukum Perdata, mengacu ketentuan Pasal 1832 Kitab Undang-undang Hukum Perdata.</li>
                        <li>Tanda tangan pihak Penjamin.</li>
                    </ol>
                </li>
                <li>Jaminan Pelaksanaan harus mempunyai masa berlaku sekurang-kurangnya sejak tanggal Surat Penunjukan Penyedia Barang/Jasa (SPPBJ) sampai dengan minimal 60 (enam puluh) hari kalender setelah batas akhir waktu penyerahan barang/jasa.</li>
                <li>Jaminan Pelaksanaan harus dapat dicairkan tanpa syarat (unconditional) sebesar nilai jaminan dalam waktu paling lambat 14 (empat belas) hari kerja setelah Surat Pernyataan Wanprestasi dari Pelaksana Pengadaan diterima oleh Penerbit Jaminan.</li>
                <li>Pelaksana Pengadaan akan melakukan klarifikasi tertulis terhadap keabsahan Jaminan Pelaksanaan yang diterima.</li>
                <li>Penyedia Barang/Jasa harus bersedia memperpanjang masa berlaku/mengganti Jaminan Pelaksanaan 14 (empat belas) hari sebelum masa berlaku jaminan tersebut habis, apabila penyerahan Barang/Jasa tertunda atau mengalami keterlambatan dari waktu yang telah ditetapkan dalam Perjanjian.</li>
                <li>Dalam hal Penyedia Barang/Jasa tidak bersedia memperpanjang masa berlaku Jaminan Pelaksanaan, maka Perjanjian akan diputus secara sepihak, selanjutnya jaminan tersebut akan dicairkan dan menjadi milik PT PLN NP. Selanjutnya terhadap Penyedia Barang/Jasa diberikan sanksi Blacklist selama 24 (dua puluh empat) bulan.</li>
                <li>Jaminan Pelaksanaan akan dikembalikan setelah diterbitkannya Berita Acara Pemeriksaan Barang/Jasa dan setelah Penyedia Barang/Jasa menyerahkan Jaminan Masa Garansi kepada PT PLN NP.</li>
                <li>Jaminan Pelaksanaan tidak dipersyaratkan untuk PT PLN (Persero) / Anak Perusahaan PT PLN (Persero) / Perusahaan Terafiliasi PT PLN (Persero) / Anak Perusahaan PT PLN NP / Perusahaan Terafiliasi PT PLN NP.</li>
            </ol>

            <h3>K. PELELANGAN GAGAL</h3>
            <p>1. Pengguna Barang/Jasa menyatakan Pelelangan Terbuka Gagal dalam hal:</p>
            <ol>
                <li>Jumlah Penyedia Barang/Jasa yang memasukkan penawaran/dokumen aplikasi kualifikasi kurang dari 2 (dua); atau</li>
                <li>Pemenang pelelangan yang ditunjuk mengundurkan diri; atau</li>
                <li>Negosiasi yang dilakukan tidak berhasil mencapai kesepakatan; atau</li>
                <li>Tidak ada penawaran yang memenuhi persyaratan dalam dokumen pelelangan; atau</li>
                <li>Terjadi perubahan rencana kerja dan mengakibatkan perubahan kebutuhan barang/jasa; atau</li>
                <li>Adanya indikasi kuat terjadinya persaingan usaha yang tidak sehat; atau</li>
                <li>Adanya indikasi terjadinya Korupsi, Kolusi dan Nepotisme (KKN); atau</li>
                <li>Sanggahan dari Penyedia Barang/Jasa dinyatakan benar; atau</li>
                <li>Berdasarkan rekomendasi dari Value for Money Committee atas usulan Pejabat Pelaksana Pengadaan, Pengguna Barang/Jasa, atau pejabat lain yang terkait; atau</li>
                <li>Akibat adanya penetapan pengadilan.</li>
            </ol>
            <p>2. PT PLN Nusantara Power UP Kendari berhak menghentikan proses pelelangan secara sepihak dan/atau berhak melakukan pelelangan ulang dengan metode yang sama atau berbeda.</p>
            <p>3. Dalam hal pelelangan gagal, maka Pejabat Pelaksana Pengadaan Barang/Jasa:</p>
            <ol>
                <li>Menyampaikan pemberitahuan Pengadaan Gagal kepada Calon Penyedia Barang/Jasa.</li>
                <li>Melakukan pengadaan ulang, dengan atau tanpa revisi Dokumen Pelelangan/RKS untuk disesuaikan dengan penyebab Pengadaan Gagal.</li>
                <li>Dalam hal terjadi revisi Dokumen Pelelangan/RKS, maka revisi dilakukan oleh Pejabat Perencana Pengadaan.</li>
                <li>PT PLN Nusantara Power UP Kendari tidak memberikan ganti rugi kepada Calon Penyedia Barang/Jasa apabila penawarannya ditolak atau pengadaan dinyatakan gagal.</li>
                <li>Dalam hal tidak ada yang memasukkan dokumen penawaran, maka Pejabat Pelaksana Pengadaan menyatakan Pengadaan Gagal dan melaporkannya kepada Pejabat Berwenang.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II section L - Syarat-syarat perjanjian.
     */
    protected function babSyaratPerjanjian(): string
    {
        return <<<'HTML'
        <section>
            <h3>L. SYARAT-SYARAT PERJANJIAN</h3>

            <h4>1. Penandatanganan Perjanjian</h4>
            <p>Perjanjian/Kontrak diterbitkan oleh PT PLN Nusantara Power UP Kendari yang ditandatangani oleh Pengguna Barang/Jasa dan memuat kesepakatan harga Barang/Jasa tertentu dalam kurun waktu tertentu dan spesifikasi tertentu, dengan ketentuan sebagai berikut:</p>
            <ol>
                <li>Penandatanganan Perjanjian dilakukan setelah Penyedia Barang/Jasa menyerahkan Jaminan Pelaksanaan dengan ketentuan sebagaimana dimaksud dalam RKS ini.</li>
                <li>Pengguna dan Penyedia Barang/Jasa wajib memeriksa konsep Perjanjian meliputi substansi, bahasa/redaksional, angka, dan huruf serta membubuhkan paraf pada lembar demi lembar dokumen Perjanjian.</li>
                <li>Banyaknya rangkap Perjanjian dibuat sesuai kebutuhan, sekurang-kurangnya 2 (dua) Perjanjian asli. Perjanjian asli pertama untuk Pengguna Barang/Jasa dibubuhi meterai pada bagian yang ditandatangani oleh Penyedia Barang/Jasa, dan Perjanjian asli kedua untuk Penyedia Barang/Jasa dibubuhi meterai pada bagian yang ditandatangani oleh Pengguna Barang/Jasa.</li>
                <li>Dalam hal terjadi penghentian dan pemutusan perjanjian terhadap Penyedia Barang/Jasa, maka Pengguna Barang/Jasa berhak dan berwenang sepenuhnya untuk mengalihkan pekerjaan kepada Penyedia Barang/Jasa lainnya berdasarkan urutan pemenang Pelelangan.</li>
            </ol>

            <h4>2. Waktu Penyelesaian Pekerjaan</h4>
            <ol>
                <li>Jangka waktu penyelesaian pekerjaan mengikuti ketentuan yang diatur dalam TOR (Lampiran 11) dan Perjanjian, dengan target penyelesaian pengadaan <b>{{target_penyelesaian}}</b>.</li>
                <li>Apabila batas akhir kontrak jatuh pada hari libur, maka batas akhir kontrak diperpanjang hingga hari kerja pertama setelah hari libur tersebut, dan tanggal permulaan terhitung sejak diterbitkannya Surat Perjanjian Pelaksanaan Pekerjaan/Kontrak.</li>
                <li>Tempat penyerahan hasil pekerjaan adalah {{unit_tujuan}}, PT PLN Nusantara Power UP Kendari.</li>
                <li>Pada waktu penyerahan barang/jasa harus melampirkan:
                    <ol>
                        <li>Kwitansi 3 (tiga) rangkap, pada lembar asli diberi meterai yang sesuai.</li>
                        <li>Faktur Pajak (diterbitkan setelah semua dokumen penagihan lengkap).</li>
                        <li>Copy Surat Pengukuhan Pengusaha Kena Pajak (PKP).</li>
                        <li>Copy Nomor Pokok Wajib Pajak (NPWP).</li>
                        <li>Copy Perjanjian/Kontrak.</li>
                        <li>Dokumen serah terima dan bukti pelaksanaan sesuai ketentuan TOR.</li>
                        <li>Berita Acara Denda (jika ada).</li>
                        <li>Berita Acara Serah Terima Pekerjaan (BASTP).</li>
                        <li>Penilaian Vendor yang ditandatangani oleh User untuk pekerjaan ini.</li>
                    </ol>
                </li>
                <li>Segala risiko dan biaya yang timbul menjadi beban dan tanggung jawab Penyedia Barang/Jasa.</li>
            </ol>

            <h4>3. Pemeriksaan dan Penerimaan Barang/Jasa</h4>
            <ol>
                <li>Setelah pekerjaan selesai, Pelaksana Pekerjaan mengajukan permintaan secara tertulis kepada Direksi Pekerjaan ({{direksi_pekerjaan}}) untuk penyerahan pekerjaan.</li>
                <li>Selambat-lambatnya dalam waktu 14 (empat belas) hari kerja setelah tanggal penyerahan pekerjaan akan dilakukan pemeriksaan pekerjaan tersebut oleh Tim Pemeriksa Kualitas Barang/Jasa.</li>
                <li>Barang/Jasa dinyatakan diterima jika sesuai dengan spesifikasi teknik yang diminta dan semua performance desain terpenuhi.</li>
                <li>Hasil pemeriksaan dituangkan dalam Berita Acara Pemeriksaan Barang yang ditandatangani oleh Tim Pemeriksa Kualitas Barang/Jasa. Bersamaan dengan penerbitan Berita Acara tersebut, ketentuan terkait Masa Garansi dinyatakan mulai berlaku.</li>
                <li>Penerbitan Berita Acara Pemeriksaan Barang tidak melepaskan tanggung jawab Penyedia Barang/Jasa terhadap kualitas pekerjaan yang diserahkan. Jika pada masa pemeliharaan hasil pekerjaan tidak dapat berfungsi sebagaimana mestinya, maka Penyedia Barang/Jasa harus menyempurnakan hasil pekerjaan dari segala kekurangan sehingga dapat berfungsi dengan baik.</li>
                <li>Dalam hal pemeriksaan dilakukan setelah batas waktu penyerahan pekerjaan dan ternyata pekerjaan tersebut tidak sesuai dengan spesifikasi teknis yang dipersyaratkan sehingga pekerjaan dinyatakan ditolak, maka Penyedia Barang/Jasa akan dikenakan denda keterlambatan terhitung sejak berakhirnya batas waktu penyerahan pekerjaan.</li>
            </ol>

            <h4>4. Cara Pembayaran</h4>
            <ol>
                <li>Pembayaran akan dilakukan setelah Penyedia Barang/Jasa menyerahkan dengan baik seluruh pekerjaan beserta dokumen penyerahannya dan dinyatakan diterima oleh Tim Pemeriksa Kualitas Barang/Jasa, yang dibuktikan dengan penerbitan Berita Acara Pemeriksaan Pekerjaan.</li>
                <li>Permintaan pembayaran ditujukan kepada PT PLN Nusantara Power UP Kendari.</li>
                <li>Pembayaran dilakukan dengan ditransfer ke nomor rekening bank yang ditunjuk oleh Penyedia Barang/Jasa.</li>
                <li>Pembayaran akan dilakukan 100% setelah pekerjaan diterima dengan baik oleh Tim Penerima yang dibuktikan dengan Berita Acara Pemeriksaan Pekerjaan.</li>
                <li>Lampiran Surat Permintaan Pembayaran adalah sama dengan dokumen penyerahan pada butir 2.4 di atas.</li>
                <li>Biaya-biaya yang timbul pada bank yang ditunjuk oleh Penyedia Barang/Jasa sehubungan dengan transaksi pembayaran menjadi beban dan tanggung jawab Penyedia Barang/Jasa.</li>
            </ol>

            <h4>5. Sanksi</h4>
            <p>5.1 Dalam hal terjadi keterlambatan penyelesaian pekerjaan melampaui batas waktu yang ditetapkan, Penyedia Barang/Jasa dikenakan sanksi berupa denda keterlambatan sebagai berikut:</p>
            <table>
                <tr><td colspan="2" style="text-align:center"><b>Denda = 1 per mil &times; V &times; H &times; W</b></td></tr>
                <tr><td style="width:22%">1 per mil</td><td>satu per seribu</td></tr>
                <tr><td>V</td><td>Volume Barang/Jasa yang diserahkan, sesuai satuan dalam Perjanjian</td></tr>
                <tr><td>H</td><td>Harga satuan pekerjaan (termasuk PPN yang berlaku)</td></tr>
                <tr><td>W</td><td>Waktu keterlambatan</td></tr>
            </table>
            <p>dengan maksimum <b>5% (lima persen)</b> dari nilai Perjanjian, kecuali bila keterlambatan dimaksud disebabkan oleh Force Majeure atau alasan yang berhubungan dengan kesalahan PT PLN Nusantara Power UP Kendari. Rincian variabel dan satuan denda mengikuti ketentuan dalam TOR (Lampiran 11) dan Perjanjian.</p>
            <ol start="2">
                <li>Denda keterlambatan atas penyerahan akan langsung dikenakan pada saat pelaksanaan pembayaran.</li>
                <li>Apabila sampai dengan batas akhir waktu penyerahan Penyedia Barang/Jasa belum melakukan penyerahan barang/jasa sebagaimana dimaksud dalam Perjanjian, maka PT PLN Nusantara Power UP Kendari berhak untuk memutus Perjanjian secara sepihak, Jaminan Pelaksanaan akan dicairkan dan sepenuhnya menjadi milik PT PLN NP, serta Penyedia Barang/Jasa dikenakan sanksi Blacklist selama 24 (dua puluh empat) bulan.</li>
                <li>Apabila Penyedia Barang/Jasa masih sanggup untuk memenuhi kewajibannya dan disetujui oleh PT PLN Nusantara Power UP Kendari, maka Penyedia Barang/Jasa wajib memperpanjang masa berlaku Jaminan Pelaksanaan sehingga tidak ada waktu penjaminan yang luang atau terputus, dan kepada Penyedia Barang/Jasa tetap dikenakan sanksi berupa denda keterlambatan.</li>
                <li>Apabila setelah disetujui oleh PT PLN Nusantara Power UP Kendari, Penyedia Barang/Jasa tidak bersedia memperpanjang masa berlaku Jaminan Pelaksanaan, maka PT PLN Nusantara Power UP Kendari berhak memutus Perjanjian secara sepihak, Jaminan Pelaksanaan yang masih berlaku akan dicairkan dan sepenuhnya menjadi milik PT PLN NP, serta Penyedia Barang/Jasa dikenakan sanksi Blacklist selama 24 (dua puluh empat) bulan.</li>
                <li>Persyaratan lain akan diuraikan dalam Perjanjian/Kontrak yang menjadi bagian tak terpisahkan dari Dokumen Pelelangan.</li>
            </ol>

            <h4>6. Force Majeure</h4>
            <ol>
                <li>Kejadian Kahar (Force Majeure) adalah setiap keadaan yang berada di luar kontrol yang wajar, langsung ataupun tidak langsung, dari pihak yang terkena (termasuk tetapi tidak terbatas pada kerusuhan, perang, bencana alam, pemogokan nasional, terorisme, embargo), tetapi jika hanya dan sejauh bahwa:
                    <ol>
                        <li>Situasi tersebut, walaupun telah dilakukan upaya keras yang pantas, tidak dapat dicegah, dihindari atau dipindahkan oleh pihak tersebut.</li>
                        <li>Kejadian tersebut mempengaruhi secara materiil kemampuan pihak yang terkena untuk melaksanakan kewajiban berdasarkan Perjanjian, dan pihak yang terkena telah melakukan seluruh tindakan pencegahan yang pantas, kehati-hatian dan tindakan alternatif yang pantas untuk menghindari akibat dari kejadian tersebut serta untuk mengurangi konsekuensinya.</li>
                        <li>Kejadian tersebut bukan akibat langsung atau tidak langsung dari kegagalan salah satu pihak untuk melaksanakan setiap kewajibannya berdasarkan Perjanjian, dan pihak yang terkena telah mengirim kepada pihak lainnya pemberitahuan seketika yang menjelaskan kejadian tersebut, akibat yang terjadi dan tindakan yang telah dilakukan. Kejadian Kahar tidak termasuk pemogokan, penutupan, atau tindakan industri lainnya oleh personel dari pihak yang terkena atau agen-agennya.</li>
                    </ol>
                </li>
                <li>Dalam hal terjadi Force Majeure, maka Penyedia Barang/Jasa wajib memberitahukan secara tertulis kepada PT PLN Nusantara Power UP Kendari selambat-lambatnya 7 (tujuh) hari kalender terhitung sejak terjadinya peristiwa Force Majeure.</li>
                <li>Apabila dalam jangka waktu tersebut Penyedia Barang/Jasa tidak memberitahukan kejadian Force Majeure, maka keterlambatan penyerahan berdasarkan Perjanjian dianggap bukan sebagai akibat dari Force Majeure.</li>
                <li>Pemberitahuan mengenai Force Majeure harus disertai keterangan dari pihak yang berwenang mengenai peristiwa tersebut, dan Penyedia Barang/Jasa dapat sekaligus mengajukan permohonan perpanjangan waktu penyerahan kepada PT PLN Nusantara Power UP Kendari.</li>
                <li>PT PLN Nusantara Power UP Kendari dalam waktu 7 (tujuh) hari kalender terhitung sejak diterimanya permohonan perpanjangan waktu akan memberikan jawaban secara tertulis mengenai permohonan dimaksud.</li>
                <li>Apabila dalam jangka waktu tersebut PT PLN Nusantara Power UP Kendari tidak memberikan jawaban, maka PT PLN Nusantara Power UP Kendari dianggap telah memberikan persetujuan terhadap permohonan dimaksud.</li>
                <li>Penyedia Barang/Jasa tidak dapat dikenakan sanksi atas keterlambatan penyerahan yang diakibatkan oleh Force Majeure.</li>
                <li>Tindakan yang diambil untuk mengatasi terjadinya Force Majeure akan dilakukan sesuai dengan kesepakatan PT PLN Nusantara Power UP Kendari dengan Penyedia Barang/Jasa.</li>
            </ol>

            <h4>7. Penyelesaian Perselisihan</h4>
            <ol>
                <li>Apabila timbul perselisihan pendapat dalam rangka pelaksanaan Perjanjian, PT PLN Nusantara Power UP Kendari dan Penyedia Barang/Jasa sepakat untuk menyelesaikan dengan musyawarah.</li>
                <li>Segala sengketa, pertentangan atau perselisihan yang timbul dari atau sehubungan dengan Perjanjian, atau pelanggarannya, yang tidak dapat diselesaikan dengan musyawarah (dengan jalan damai), akan diselesaikan melalui <b>Badan Arbitrase Nasional Indonesia (BANI) di Surabaya</b> sesuai dengan prosedur dan tata cara penyelesaian perselisihan yang berlaku di BANI.</li>
                <li>Putusan BANI tidak dapat diganggu gugat dan bersifat terakhir. Putusan tersebut segera diserahkan kepada pengadilan yang mempunyai wewenang hukum (yurisdiksi) untuk melaksanakannya. Para pihak tidak akan mengajukan banding kepada pengadilan atas putusan tersebut. Sambil menunggu penyelesaian atas suatu sengketa, para pihak akan tetap memenuhi kewajibannya berdasarkan Perjanjian.</li>
            </ol>

            <h4>8. Pengalihan Pekerjaan Kepada Pihak Lainnya</h4>
            <ol>
                <li>Penyedia Barang/Jasa tidak diperbolehkan untuk mengalihkan sebagian maupun seluruh hak dan kewajibannya berdasarkan Perjanjian kepada pihak lain tanpa persetujuan tertulis terlebih dahulu dari PT PLN Nusantara Power UP Kendari.</li>
                <li>Dalam hal Penyedia Barang/Jasa akan mengalihkan sebagian maupun seluruh hak dan kewajibannya kepada pihak lain berdasarkan persetujuan tertulis dari PT PLN Nusantara Power UP Kendari, maka seluruh kerugian yang timbul sebagai akibat pengalihan dimaksud menjadi beban dan tanggung jawab Penyedia Barang/Jasa.</li>
                <li>Apabila di kemudian hari diketahui bahwa pekerjaan diserahkan kepada pihak lain tanpa persetujuan dari PT PLN Nusantara Power UP Kendari, maka:
                    <ol>
                        <li>PT PLN Nusantara Power UP Kendari berhak melakukan pemutusan Perjanjian secara sepihak.</li>
                        <li>PT PLN Nusantara Power UP Kendari berhak mengeluarkan Penyedia Barang/Jasa dari daftar rekanan.</li>
                        <li>PT PLN Nusantara Power UP Kendari berhak tidak memperbolehkan Penyedia Barang/Jasa mengikuti pengadaan barang dan/atau jasa di wilayah kerjanya minimal 24 (dua puluh empat) bulan sejak tanggal pemutusan Perjanjian apabila menyerahkan sebagian pekerjaan kepada pihak lain tanpa persetujuan.</li>
                        <li>PT PLN Nusantara Power UP Kendari berhak tidak memperbolehkan Penyedia Barang/Jasa mengikuti pengadaan barang dan/atau jasa di wilayah kerjanya minimal 60 (enam puluh) bulan sejak tanggal pemutusan Perjanjian apabila menyerahkan seluruh pekerjaan kepada pihak lain tanpa persetujuan.</li>
                    </ol>
                </li>
            </ol>

            <h4>9. Keselamatan dan Kesehatan Kerja, Keamanan dan Kebersihan Lingkungan</h4>
            <ol>
                <li>Penyedia Barang/Jasa wajib mematuhi kebijakan dan prosedur Sistem Manajemen Keselamatan dan Kesehatan Kerja (SMK3), Sistem Manajemen Lingkungan (SML) dan Contractor Safety Management System (CSMS) yang telah ditetapkan di lingkungan PT PLN Nusantara Power UP Kendari.</li>
                <li>Penyedia Barang/Jasa dalam melakukan pekerjaan harus memperhatikan ketentuan identifikasi bahaya dan pengendalian risiko (HIRAC).</li>
                <li>Selama pelaksanaan pekerjaan, Penyedia Barang/Jasa harus memasang border line dengan tanda bahaya yang menunjukkan sedang ada pekerjaan, dan semua petugas Penyedia Barang/Jasa harus berada di dalam area border line kecuali bila dibutuhkan oleh pengawas lapangan untuk keperluan administrasi proyek.</li>
                <li>Sebelum memasuki area pekerjaan, semua pekerja dari Penyedia Barang/Jasa harus izin kepada pengawas pekerjaan yang ditunjuk oleh Direksi Pekerjaan untuk menunjukkan area kerja dan mengkoordinasikan dengan operator apabila pekerjaan berkaitan dengan Unit Operasi guna pengisolasian (pemasangan tagging). Semua pekerja harus memakai alat pelindung diri minimum helmet, safety shoes dan sabuk pengaman untuk pekerjaan di atas 2 (dua) meter dari tanah.</li>
                <li>Penyedia Barang/Jasa harus menunjuk penanggung jawab pekerjaan lapangan yang harus berada di lokasi pekerjaan selama pekerjaan berlangsung hingga selesai. Apabila pekerjaan lebih dari satu hari kalender, penanggung jawab pekerjaan dan pekerja harus tetap meminta izin kepada pengawas pekerjaan sebelum memulai pekerjaan.</li>
                <li>Penyedia Barang/Jasa wajib membersihkan area kerja dari sisa-sisa pelaksanaan pekerjaan sehingga tidak mengganggu operasional Unit Pembangkit.</li>
                <li>Penyedia Barang/Jasa wajib mengganti kerusakan peralatan dalam area kerja yang diakibatkan oleh pelaksanaan pekerjaan.</li>
                <li>Setiap pekerjaan yang menggunakan api harus dalam pengawasan petugas safety dan LK-3 dari PT PLN Nusantara Power UP Kendari.</li>
                <li>Setiap pekerjaan yang dilakukan pada area kerja berisiko tinggi wajib menerapkan Buddy System (tidak boleh bekerja atau masuk ke area kerja seorang diri).</li>
                <li>Perhatian khusus harus diberikan untuk menjaga agar bagian dalam bangunan beserta tanah sekitarnya tetap bersih dan bebas dari sampah serta puing-puing. Penyedia Barang/Jasa harus mempekerjakan orang yang memadai dan khusus untuk membersihkan daerah tempat kerjanya terus-menerus setiap hari kerja.</li>
                <li>Semua sampah dan limbah bekas pekerjaan jenis logam maupun non logam harus ditempatkan di tempat pembuangan limbah yang telah ditentukan oleh Direksi Pekerjaan.</li>
                <li>Semua peralatan, bahan dan fasilitas milik Penyedia Barang/Jasa harus dibawa keluar dari lapangan. Penyedia Barang/Jasa harus benar-benar membersihkan pekerjaannya dari timbunan debu, serpihan, minyak, gemuk, percikan las, serta barang lain yang tidak pada tempatnya. Permukaan yang rusak karena tumpukan isolasi, beton, cat, serpihan logam, atau barang lengket lainnya harus diperbaiki oleh Penyedia Barang/Jasa.</li>
                <li>Apabila Penyedia Barang/Jasa gagal memenuhi tuntutan kebersihan yang ditetapkan, atau gagal melakukan pekerjaan pembersihan yang ditugaskan oleh Direksi Pekerjaan, maka Direksi Pekerjaan berhak menunjuk pihak lain untuk melakukan pekerjaan pembersihan yang diperlukan dan Penyedia Barang/Jasa harus mengganti biaya pembersihan tersebut.</li>
                <li>Apabila Penyedia Barang/Jasa terbukti tidak memenuhi ketentuan yang telah ditetapkan, maka PT PLN Nusantara Power UP Kendari berhak untuk memutus Perjanjian secara sepihak.</li>
            </ol>

            <h4>10. Penundaan Penyelesaian Pekerjaan</h4>
            <ol>
                <li>PT PLN Nusantara Power UP Kendari mempunyai hak memerintahkan untuk menunda dan memulai lagi seluruh pekerjaan atau bagian dari pekerjaan tanpa membatalkan persyaratan dalam Perjanjian.</li>
                <li>Perintah untuk menunda atau memulai lagi pekerjaan akan dikeluarkan secara tertulis oleh PT PLN Nusantara Power UP Kendari kepada Penyedia Barang/Jasa. Waktu penyelesaian pekerjaan akan diperpanjang sesuai dengan waktu yang hilang akibat penundaan tersebut.</li>
                <li>Apabila Penyedia Barang/Jasa terlambat menyelesaikan pekerjaan yang disebabkan adanya keadaan kahar (force majeure) atau hal lain yang diinstruksikan oleh Pemberi Kerja, maka kepada Penyedia Barang/Jasa akan diberikan perpanjangan waktu yang menurut pertimbangan PT PLN Nusantara Power UP Kendari cukup untuk kompensasi dari keterlambatan tersebut tanpa tambahan harga dan tanpa dikenakan sanksi denda.</li>
            </ol>

            <h4>11. Pembebasan Tuntutan</h4>
            <ol>
                <li>Penyedia Barang/Jasa menjamin PT PLN Nusantara Power UP Kendari dan personil perusahaan bahwa sejak ditandatanganinya Perjanjian maupun di kemudian hari tidak akan mendapat tuntutan dari pihak lain yang menyatakan mempunyai hak atas hasil pekerjaan sebagaimana dimaksud dalam Perjanjian.</li>
                <li>Apabila di kemudian hari PT PLN Nusantara Power UP Kendari mendapat tuntutan dari pihak lain yang menyatakan mempunyai hak terlebih dahulu atau mempunyai hak atas barang/hasil pekerjaan sebagaimana dimaksud dalam Perjanjian, maka semua biaya yang diperlukan sebagai akibat tuntutan tersebut menjadi beban dan tanggung jawab Penyedia Barang/Jasa.</li>
            </ol>

            <h4>12. Pemutusan Perjanjian</h4>
            <p>12.1 Perjanjian berakhir apabila:</p>
            <ol>
                <li>Berakhirnya Masa Garansi sebagaimana ditentukan dalam Perjanjian.</li>
                <li>Diakhiri berdasarkan kesepakatan para pihak karena adanya peristiwa Force Majeure.</li>
                <li>Diakhiri berdasarkan kesepakatan para pihak dengan adanya pemberitahuan dalam waktu paling lambat 7 (tujuh) hari kalender sebelumnya, disertai kesepakatan kompensasi ganti rugi dan pelunasan kewajiban dari para pihak.</li>
            </ol>
            <p>12.2 <b>Pemutusan tanpa peringatan.</b> Apabila Penyedia Barang/Jasa mengundurkan diri setelah penandatanganan Perjanjian, atau telah dinyatakan pailit oleh Pengadilan Niaga, atau tidak dapat menyelesaikan pekerjaan beserta kelengkapan dokumen dalam batas waktu perpanjangan yang disetujui oleh PT PLN Nusantara Power UP Kendari, maka PT PLN Nusantara Power UP Kendari berhak memutus Perjanjian secara sepihak, baik sebagian atau seluruhnya, tanpa memberikan peringatan tertulis kepada Penyedia Barang/Jasa.</p>
            <p>12.3 <b>Pemutusan dengan peringatan tertulis:</b></p>
            <ol>
                <li>Apabila Penyedia Barang/Jasa tidak dapat menyerahkan pekerjaan dalam batas waktu yang ditentukan, maka PT PLN Nusantara Power UP Kendari akan memberikan peringatan tertulis sebagai Surat Peringatan Pertama.</li>
                <li>Apabila dalam jangka waktu 7 (tujuh) hari kalender setelah tanggal diterbitkannya Surat Peringatan Pertama Penyedia Barang/Jasa tidak memberikan respons/jawaban secara tertulis, maka PT PLN Nusantara Power UP Kendari akan memberikan Surat Peringatan Kedua sekaligus surat peringatan terakhir.</li>
                <li>Apabila dalam jangka waktu 7 (tujuh) hari kalender setelah tanggal diterbitkannya Surat Peringatan Kedua Penyedia Barang/Jasa tidak memberikan respons/jawaban secara tertulis, maka PT PLN Nusantara Power UP Kendari berhak memutus Perjanjian.</li>
                <li>Apabila terjadi pemutusan Perjanjian, PT PLN Nusantara Power UP Kendari berhak mencairkan Jaminan Pelaksanaan yang akan sepenuhnya menjadi hak miliknya, serta Penyedia Barang/Jasa dikenakan sanksi tidak diperbolehkan mengikuti pengadaan Barang/Jasa di wilayah kerja PT PLN NP selama 24 (dua puluh empat) bulan sejak tanggal pemutusan Perjanjian.</li>
                <li>Dalam hal terjadi pemutusan Perjanjian, para pihak sepakat untuk tidak memberlakukan ketentuan Pasal 1266 dan Pasal 1267 Kitab Undang-Undang Hukum Perdata.</li>
            </ol>

            <h4>13. Perubahan-perubahan Pada Perjanjian</h4>
            <ol>
                <li>Setiap perubahan yang terjadi dalam Perjanjian hanya dapat dilakukan atas persetujuan bersama.</li>
                <li>Usulan perubahan harus diajukan secara tertulis oleh pihak yang berkepentingan kepada pihak lainnya sebelum berlakunya perubahan yang diusulkan.</li>
                <li>Setiap perubahan yang telah disepakati ditandatangani oleh masing-masing pihak, yang selanjutnya menjadi Addendum dan merupakan bagian yang tidak terpisahkan dari Perjanjian.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB III - Penutup and the signature block.
     */
    protected function babPenutup(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h2 class="bab-heading">BAB III<br>PENUTUP</h2>

            <p>Perubahan atau penambahan atas hal-hal lain yang belum tercakup dalam RKS ini akan dicantumkan dalam Berita Acara Penjelasan Lelang (Aanwijzing) dan Addendum RKS, yang akan merupakan bagian yang tidak terpisahkan dari RKS ini.</p>

            <p style="margin-top:24pt">Kendari, {{tanggal_dokumen}}</p>
            <p><b>PT PLN NUSANTARA POWER<br>UP KENDARI</b></p>

            <table class="signature">
                <tr>
                    <td class="role">Dibuat Oleh<br>Fungsi Perencana Pengadaan</td>
                    <td class="role">Diperiksa Oleh<br>Team Leader</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name">{{pic_perencana}}</td>
                    <td class="name fill">( Nama Jelas )</td>
                </tr>
            </table>

            <table class="signature">
                <tr>
                    <td class="role">Diketahui Oleh<br>Assistant Manager Pemeliharaan</td>
                    <td class="role">Menyetujui<br>Manager UP Kendari</td>
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
     * Lampiran 1 to 11.
     */
    protected function lampiran(): string
    {
        return <<<'HTML'
        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 1 : Spesifikasi Pekerjaan</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>SPESIFIKASI BARANG/JASA YANG DIMINTAKAN PENAWARAN</h3>
            <p>Pekerjaan: <b>{{nama_pengadaan}}</b> &mdash; {{unit_tujuan}}</p>
            <table>
                <tr><th style="width:6%">No.</th><th>Nama Barang/Jasa dan Spesifikasi</th><th style="width:16%">Quantity</th><th style="width:12%">Satuan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>5</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p class="note">Rincian lengkap spesifikasi mengacu pada Term of Reference (TOR) pada Lampiran 11.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 2 : Spesifikasi Barang/Jasa yang Ditawarkan</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>SPESIFIKASI PEKERJAAN YANG DITAWARKAN</h3>
            <table>
                <tr><th style="width:6%">No.</th><th>Nama Barang/Jasa</th><th style="width:18%">Jumlah</th><th style="width:24%">Waktu Penyerahan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 3 : Contoh Surat Penawaran</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Nomor : <span class="fill">..........................</span><br>Lampiran : <span class="fill">..........................</span><br>Tanggal : <span class="fill">..........................</span></p>
            <p>Kepada<br><b>PELAKSANA PENGADAAN</b><br>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari</p>
            <p>Perihal : Penawaran Administrasi, Keuangan, Teknis, dan Harga</p>
            <p>Yang bertanda tangan di bawah ini : <span class="fill">.............................................</span> (A)<br>
               Dalam hal ini diwakili oleh : <span class="fill">.............................................</span> (B)<br>
               Jabatan dalam perusahaan : <span class="fill">.............................................</span> (C)</p>
            <p>Dengan ini menyatakan:</p>
            <ol>
                <li>Tunduk pada ketentuan-ketentuan pengadaan yang berlaku di PT PLN NP.</li>
                <li>Bersedia melaksanakan pekerjaan <b>{{nama_pengadaan}}</b> sesuai dengan syarat-syarat yang tercantum dalam:
                    <ol>
                        <li>RKS Nomor <span class="fill">..................</span> tanggal <span class="fill">..................</span> tentang <span class="fill">..................</span></li>
                        <li>Berita Acara Penjelasan Nomor <span class="fill">..................</span> tanggal <span class="fill">..................</span></li>
                    </ol>
                </li>
                <li>Waktu penyerahan adalah <span class="fill">.......</span> (<span class="fill">..................</span>) bulan, terhitung sejak tanggal Surat Penunjukan.</li>
                <li>Harga penawaran:
                    <table>
                        <tr><td style="width:60%">Harga Barang/Jasa</td><td class="fill">&nbsp;</td></tr>
                        <tr><td>Pajak Pertambahan Nilai (PPN)</td><td class="fill">&nbsp;</td></tr>
                        <tr><td><b>Jumlah penawaran</b></td><td class="fill">&nbsp;</td></tr>
                        <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                    </table>
                </li>
                <li>Rincian penawaran harga setiap Barang/Jasa seperti terlampir.</li>
                <li>Asli Jaminan Penawaran dari Bank <span class="fill">..............................................</span></li>
                <li>Penawaran tersebut mengikat dalam jangka waktu 90 (sembilan puluh) hari kalender terhitung sejak tanggal pembukaan surat penawaran dan dapat diperpanjang lagi bila diperlukan.</li>
                <li>Terlampir kami sampaikan data kelengkapan dokumen penawaran.</li>
            </ol>
            <p>Demikian penawaran ini, atas perhatiannya kami ucapkan terima kasih.</p>
            <table class="signature">
                <tr><td class="role">PT <span class="fill">..................................</span></td></tr>
                <tr><td class="space">(D) Tanda tangan penawar dan stempel perusahaan<br>(asli di atas meterai Rp 10.000,-)</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; (E) Jabatan</td></tr>
            </table>
            <p class="note">Keterangan: A = Nama dan alamat perusahaan; B = Nama yang mewakili perusahaan; C = Jabatan yang mewakili perusahaan; D = Tanda tangan dan stempel perusahaan; E = Jabatan. Butir B&ndash;E adalah pejabat yang diatur kewenangannya berdasarkan Akta Pendirian Perusahaan dan perubahannya.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 4 : Contoh Daftar Rincian Harga Penawaran</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>DAFTAR RINCIAN HARGA PENAWARAN</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Nama Pekerjaan</th><th style="width:9%">Jumlah</th><th style="width:9%">Satuan</th><th style="width:14%">Harga Satuan</th><th style="width:14%">Jumlah (Rp)</th><th style="width:14%">Waktu Penyerahan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>SUB TOTAL</b></td><td class="fill">&nbsp;</td><td></td></tr>
                <tr><td colspan="5"><b>PPN</b></td><td class="fill">&nbsp;</td><td></td></tr>
                <tr><td colspan="5"><b>TOTAL HARGA PENAWARAN</b></td><td class="fill">&nbsp;</td><td></td></tr>
                <tr><td colspan="7"><b>TERBILANG:</b> <span class="fill">..............................................................................</span></td></tr>
            </table>
            <p class="note">Paraf dan stempel perusahaan.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 5 : Contoh Surat Pernyataan</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Kepada<br><b>PELAKSANA PENGADAAN</b><br>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Kel. Mataiwoi, Kec. Wua-Wua, Kota Kendari</p>
            <p>Perihal : Surat Pernyataan</p>
            <p>Yang bertanda tangan di bawah ini:<br>Nama : <span class="fill">..................................</span><br>Mewakili : PT <span class="fill">..................................</span><br>Jabatan : <span class="fill">..................................</span></p>
            <p>Sehubungan dengan pelelangan <b>{{nama_pengadaan}}</b> sesuai RKS tersebut di atas, dengan ini kami menyatakan hal-hal sebagai berikut:</p>
            <ol>
                <li>Bahwa perusahaan kami sanggup mematuhi dan memenuhi semua ketentuan yang ditetapkan dalam Dokumen Pelelangan.</li>
                <li>Bahwa perusahaan kami sanggup memenuhi Persyaratan Teknis/Term of Reference (TOR).</li>
                <li>Bahwa perusahaan kami tidak dalam keadaan bangkrut.</li>
                <li>Bahwa direktur perusahaan kami tidak dalam pengawasan pengadilan dan tidak sedang menjalani sanksi pidana.</li>
                <li>Perusahaan kami tidak akan menuntut ganti rugi dalam bentuk apapun jika pelelangan ini dinyatakan batal atau penawaran ditolak.</li>
                <li>Apabila dalam masa pemeliharaan ternyata jasa yang dikerjakan terdapat cacat/kerusakan karena penggunaan barang bermutu rendah atau kesalahan pembuatan/pemasangan dan bukan karena kesalahan operasi, maka kami sanggup untuk memperbaiki atau mengganti part rusak dengan yang baru.</li>
                <li>Perusahaan kami yang sedang mengikuti pelelangan ini tidak mempunyai hubungan/sangkut paut dengan perusahaan lain yang sedang bermasalah dengan PT PLN NP.</li>
                <li>Apabila data/pernyataan yang kami sampaikan dalam penawaran ternyata ada yang palsu, maka kami bersedia dikenakan sanksi tidak diperkenankan untuk mengikuti pengadaan barang/jasa di lingkungan PT PLN NP dan PT PLN (Persero) Group selama 24 (dua puluh empat) bulan.</li>
                <li>Bertanggung jawab penuh dan sekaligus membebaskan PT PLN NP dari segala tuntutan atas pelanggaran Hak Kekayaan Intelektual (HKI), hak paten, merek terdaftar, desain, hak cipta atau hak atas kekayaan intelektual lainnya.</li>
                <li>Perusahaan kami tidak sedang menjalani sanksi Blacklist di lingkungan PT PLN NP dan PT PLN (Persero) Group.</li>
            </ol>
            <p>Demikian Surat Pernyataan ini dibuat dengan sebenarnya, untuk digunakan sebagaimana mestinya.</p>
            <table class="signature">
                <tr><td class="role">PT <span class="fill">..................................</span></td></tr>
                <tr><td class="space">Meterai Rp 10.000,- &mdash; Tanda tangan dan stempel perusahaan</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; Jabatan</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 6 : Contoh Pakta Integritas</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>PAKTA INTEGRITAS</h3>
            <p>Saya yang bertanda tangan di bawah ini menyatakan:</p>
            <ol>
                <li>Tidak akan melakukan praktek KKN.</li>
                <li>Akan melakukan praktek persaingan yang sehat dalam proses pengadaan.</li>
                <li>Akan melaporkan kepada pihak yang berwajib/berwenang apabila mengetahui ada indikasi KKN dan atau praktek persaingan yang tidak sehat dalam proses pengadaan.</li>
                <li>Dalam proses pengadaan ini berjanji akan melaksanakan secara bersih, transparan, dan profesional dalam arti akan mengerahkan segala kemampuan dan sumber daya secara optimal untuk memberikan hasil kerja terbaik mulai dari penawaran, pelaksanaan dan penyelesaian pekerjaan/kegiatan ini.</li>
                <li>Meningkatkan penggunaan produksi dalam negeri dengan memperbesar TKDN sesuai ketentuan yang berlaku dan menggunakan produk berstandar.</li>
                <li>Dalam keadaan tertentu akan mengikutsertakan usaha mikro, usaha kecil dan koperasi kecil sesuai kompetensi teknis yang dimiliki untuk bagian pekerjaan yang bukan pekerjaan utama.</li>
                <li>Dalam melakukan pengadaan akan selalu berpegang pada konsep ramah lingkungan.</li>
                <li>Apabila saya melanggar hal-hal yang telah saya nyatakan dalam Pakta Integritas ini, saya bersedia dikenakan sanksi moral, sanksi administrasi serta dituntut ganti rugi dan pidana sesuai dengan ketentuan peraturan perundang-undangan yang berlaku.</li>
            </ol>
            <table class="signature">
                <tr><td class="role">Kendari, <span class="fill">..........................</span> {{tahun}}<br>Nama Penyedia Barang/Jasa</td></tr>
                <tr><td class="space">Tanda tangan dan stempel perusahaan<br>(asli di atas meterai Rp 10.000,-)</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; Jabatan</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 7 : Contoh Daftar Referensi Pengalaman Pekerjaan</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>DAFTAR REFERENSI PENGALAMAN/PEKERJAAN SEJENIS</h3>
            <table>
                <tr>
                    <th style="width:5%">No</th>
                    <th>Uraian</th>
                    <th style="width:20%">Data Teknik<br><span class="note">jenis/tipe, kapasitas, dsb</span></th>
                    <th style="width:20%">Data Pemakai<br><span class="note">nama, alamat, kontak person</span></th>
                    <th style="width:20%">Kontrak/Perjanjian<br><span class="note">nomor, tanggal, tahun operasi</span></th>
                    <th style="width:10%">Ket</th>
                </tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 8 : Ketentuan Blacklist</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>KETENTUAN BLACKLIST</h3>
            <p>Hal-hal yang dapat menyebabkan Penyedia Barang/Jasa masuk dalam Blacklist atau daftar hitam perusahaan adalah:</p>

            <h4>Selama 6 bulan</h4>
            <ol>
                <li>Tidak memperbaharui persyaratan sebagai Penyedia yang telah habis masa berlakunya lebih dari 1 bulan.</li>
                <li>Penyedia yang mendaftar untuk ikut pelelangan namun tidak memasukkan Dokumen Penawaran tanpa alasan yang profesional.</li>
                <li>Penyedia yang terdaftar dalam DPP tidak memberikan respons atau merespons dengan alasan yang tidak profesional pada saat diundang untuk mengikuti pelelangan selama 3 (tiga) kali tidak berturut-turut.</li>
            </ol>

            <h4>Selama 12 bulan</h4>
            <ol>
                <li>Pelanggaran ketiga atas alasan sebagaimana dikenakan sanksi 6 (enam) bulan.</li>
                <li>Apabila sanggahan tidak benar dan cenderung mengada-ada.</li>
                <li>Peserta yang lulus kualifikasi dan diundang untuk memasukkan penawaran namun tidak memasukkan Dokumen Penawaran.</li>
                <li>Peserta pengadaan menyatakan tidak mampu melaksanakan pengadaan sesuai Dokumen Pengadaan atau tidak bersedia menambah nilai jaminan pelaksanaannya.</li>
            </ol>

            <h4>Selama 24 bulan</h4>
            <ol>
                <li>Pelanggaran keempat atas alasan sebagaimana dikenakan sanksi 6 (enam) bulan.</li>
                <li>Pelanggaran kedua atas alasan sebagaimana dikenakan sanksi 12 (dua belas) bulan.</li>
                <li>Melakukan kecurangan pada saat pengumuman lelang, misalnya dengan menghalangi tersebarnya pengumuman.</li>
                <li>Melakukan kecurangan dalam proses pelelangan, termasuk melakukan persekongkolan (konspirasi) dengan pihak lain atau menghalang-halangi pihak lain terlibat dalam pengadaan.</li>
                <li>Berusaha mempengaruhi Pejabat Pengadaan/Pelaksana Pengadaan/Pejabat yang Berwenang dalam bentuk dan cara apapun, baik langsung maupun tidak langsung, guna memenuhi keinginannya yang bertentangan dengan ketentuan dan prosedur yang telah ditetapkan dalam Dokumen Pengadaan/Kontrak dan/atau ketentuan peraturan perundang-undangan.</li>
                <li>Memalsukan persyaratan sebagai Penyedia.</li>
                <li>Penyedia Barang/Jasa yang berada dalam satu kekuatan pengaruh pemilik modal dan/atau kepengurusan sehingga mengurangi/menghambat/memperkecil dan/atau meniadakan persaingan yang sehat dan/atau merugikan orang lain.</li>
                <li>Penyedia Barang/Jasa yang keberatan atas proses pelelangan dan tidak mengajukan sanggahan secara tertulis tetapi menyebarkan ke publik dan ternyata informasi tersebut benar.</li>
                <li>Penyedia memalsukan data tingkat komponen dalam negeri atau standardisasi produk.</li>
                <li>Tidak mengutamakan usaha mikro, usaha kecil atau koperasi kecil sebagaimana disyaratkan dalam Kontrak.</li>
                <li>Mengundurkan diri pada saat akan ditetapkan sebagai pemenang lelang, atau tidak mau ditunjuk sebagai pemenang, atau tidak bersedia menandatangani kontrak dengan alasan yang profesional.</li>
                <li>Penyedia Barang/Jasa yang lalai/tidak bersedia memperbaiki cacat mutu/kerusakan karena mutu pada masa pemeliharaan/garansi.</li>
                <li>Mensubkontrakkan sebagian pekerjaan spesialis kepada yang bukan spesialis.</li>
                <li>Penyedia Barang/Jasa lalai atau tidak menyelesaikan kontrak, atau lalai tidak memenuhi ketentuan dalam kontrak sehingga dikenai sanksi pemutusan kontrak.</li>
            </ol>

            <h4>Selama 60 bulan</h4>
            <ol>
                <li>Pelanggaran kelima atas alasan sebagaimana dikenakan sanksi 6 (enam) bulan.</li>
                <li>Pelanggaran ketiga atas alasan sebagaimana dikenakan sanksi 12 (dua belas) bulan.</li>
                <li>Pelanggaran kedua atas alasan sebagaimana dikenakan sanksi 24 (dua puluh empat) bulan.</li>
                <li>Calon Pemenang dan 2 (dua) Peserta Lelang dengan Penawaran Harga terendah kedua, ketiga, dan seterusnya melakukan penipuan atau pemalsuan informasi kualifikasi maupun pemalsuan dokumen kelengkapan penawaran.</li>
                <li>Mengundurkan diri pada saat akan ditetapkan sebagai pemenang lelang I/II/III, atau tidak mau ditunjuk sebagai pemenang, atau tidak bersedia menandatangani kontrak dengan alasan yang tidak profesional.</li>
                <li>Penyedia Barang/Jasa yang keberatan atas proses pelelangan dan tidak mengajukan sanggahan secara tertulis tetapi menyebarkan ke publik dan ternyata informasi tersebut tidak benar atau mengada-ada.</li>
                <li>Penyedia melanggar Hak Kekayaan Intelektual.</li>
                <li>Mensubkontrakkan seluruh pekerjaan.</li>
            </ol>

            <p><b>Ketentuan Blacklist di atas tidak berlaku apabila:</b></p>
            <ol>
                <li>Kesalahan atau kelalaian Penyedia disebabkan oleh Perusahaan.</li>
                <li>Bertentangan dengan Keputusan Pengadilan.</li>
            </ol>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 9 : Contoh Surat Pernyataan Minat</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>SURAT PERNYATAAN MINAT UNTUK MENGIKUTI PENGADAAN</h3>
            <p>Yang bertanda tangan di bawah ini:</p>
            <p>Nama : <span class="fill">..................................</span><br>
               Jabatan : <span class="fill">..................................</span><br>
               Mewakili : PT <span class="fill">..................................</span><br>
               Alamat : <span class="fill">..................................</span><br>
               Telepon / Fax : <span class="fill">..................................</span><br>
               Email : <span class="fill">..................................</span></p>
            <p>Menyatakan dengan sebenarnya bahwa setelah mengetahui pengadaan yang akan dilaksanakan oleh Kantor Pusat/Unit PT PLN NP Tahun Anggaran {{tahun}}, maka dengan ini saya menyatakan berminat untuk mengikuti proses <b>{{nama_pengadaan}}</b> sampai selesai.</p>
            <p>Demikian pernyataan ini dibuat dengan penuh kesadaran dan tanggung jawab.</p>
            <table class="signature">
                <tr><td class="role">Kendari, <span class="fill">..........................</span> {{tahun}}<br>Nama Penyedia Barang/Jasa</td></tr>
                <tr><td class="space">Meterai Rp 10.000,- &mdash; Tanda tangan dan cap perusahaan</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; Jabatan</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 10 : Daftar Penerbit Jaminan Terseleksi Pengadaan Barang/Jasa</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Berikut adalah Daftar Penerbit Jaminan Terseleksi (DPJT) pengadaan Barang/Jasa di lingkungan PT PLN (Persero), yang meliputi pemberi Jaminan Penawaran, Jaminan Pelaksanaan dan Jaminan Garansi atau Pemeliharaan:</p>
            <table>
                <tr><th style="width:6%">No</th><th style="width:40%">Klasifikasi Batasan Nilai Kontrak</th><th>Bank</th></tr>
                <tr>
                    <td>1</td>
                    <td>Tidak ada batasan nilai kontrak</td>
                    <td>Bank Rakyat Indonesia<br>Bank Mandiri<br>Bank Negara Indonesia<br>Bank Central Asia<br>Bank Danamon Indonesia<br>CIMB Niaga</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Nilai kontrak kurang dari dan atau sama dengan Rp 1 triliun</td>
                    <td>Bank MUFG<br>Bank OCBC NISP<br>Bank Permata<br>Bank UOB Indonesia<br>Bank Bukopin<br>Bank BRI Syariah</td>
                </tr>
            </table>
            <p class="note">Daftar penerbit jaminan mengikuti ketetapan terakhir yang berlaku di PT PLN (Persero) / PT PLN Nusantara Power.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 11 : Term of Reference (TOR)</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Term of Reference (TOR) pekerjaan <b>{{nama_pengadaan}}</b> dilampirkan sebagai dokumen tersendiri dan merupakan bagian yang tidak terpisahkan dari RKS ini.</p>
            <table>
                <tr><td style="width:32%">Nomor Pengadaan</td><td>{{nomor_pengadaan}}</td></tr>
                <tr><td>Unit Tujuan</td><td>{{unit_tujuan}}</td></tr>
                <tr><td>Direksi Pekerjaan</td><td>{{direksi_pekerjaan}}</td></tr>
                <tr><td>PIC Perencana</td><td>{{pic_perencana}}</td></tr>
                <tr><td>PIC Pelaksana</td><td>{{pic_pelaksana}}</td></tr>
                <tr><td>Status Progres</td><td>{{status_progres}}</td></tr>
                <tr><td>Target Penyelesaian</td><td>{{target_penyelesaian}}</td></tr>
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
