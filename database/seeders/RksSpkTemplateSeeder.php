<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The official UP Kendari RKS template for procurements carried out with SPK.
 *
 * It is stored as an ordinary editable template row, so an administrator can
 * still revise it or publish a new version from the Data Master screen.
 */
class RksSpkTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the SPK specific RKS template.
     */
    public function run(): void
    {
        $documentType = DocumentType::query()->where('code', 'rks')->first();
        $method = ProcurementMethod::query()->where('code', 'spk')->first();

        if ($documentType === null || $method === null) {
            $this->command->warn('Jenis dokumen RKS atau metode SPK belum ada, template dilewati.');

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
                'name' => 'RKS Resmi UP Kendari - Metode SPK',
                'body' => $body,
                'placeholders' => $this->placeholdersIn($body),
                'is_active' => true,
            ],
        );

        $this->command->info('Template RKS untuk metode SPK berhasil dipasang.');
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
            $this->babPenyiapanPenawaran(),
            $this->babEvaluasi(),
            $this->babPenetapanDanPenunjukan(),
            $this->babSyaratPerjanjian(),
            $this->babKepatuhan(),
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
                DOKUMEN RENCANA KERJA DAN SYARAT-SYARAT (RKS)<br>
                SURAT PERINTAH KERJA (SPK)
            </p>

            <div class="doc-meta">
                <table>
                    <tr><td>NOMOR</td><td>:</td><td class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</td></tr>
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
     * Contents listing.
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
                <li>BAB II INSTRUKSI KEPADA PESERTA
                    <ul>
                        <li>A. Umum</li>
                        <li>B. Rencana Kerja dan Syarat-Syarat (RKS)</li>
                        <li>C. Penyiapan Dokumen Penawaran</li>
                        <li>D. Pemasukan Dokumen Penawaran</li>
                        <li>E. Pembukaan dan Evaluasi Penawaran</li>
                        <li>F. Negosiasi Penawaran dan Klarifikasi</li>
                        <li>G. Penetapan Pemenang</li>
                        <li>H. Masa Sanggah dan Jaminan Sanggah</li>
                        <li>I. Penunjukan Pemenang</li>
                        <li>J. Jaminan Pelaksanaan (Performance Bond)</li>
                        <li>K. Penghentian Proses Pengadaan Barang/Jasa</li>
                        <li>L. Pengadaan Barang/Jasa Gagal</li>
                        <li>M. Syarat-Syarat Perjanjian</li>
                    </ul>
                </li>
                <li>BAB III KEPATUHAN TERHADAP HUKUM DAN ANTI PENYUAPAN</li>
                <li>BAB IV PENUTUP</li>
                <li>LAMPIRAN 1 s.d. 13</li>
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
                <li>Peraturan Direksi PT PLN Nusantara Power Nomor 0034.P/DIR/2025 tanggal 14 November 2025 tentang Kebijakan Strategis Pengadaan Barang/Jasa PT PLN Nusantara Power.</li>
                <li>Peraturan Direksi PT PLN Nusantara Power Nomor 0035.P/DIR/2025 tanggal 14 November 2025 tentang Kebijakan Pelaksana Pengadaan Barang/Jasa Lainnya PT PLN Nusantara Power.</li>
                <li>Keputusan Direksi PT Pembangkitan Jawa-Bali Nomor 0002.K/010/DIR/2021 tanggal 22 Juni 2021 tentang Aplikasi Smart Digital Supply Chain PT Pembangkitan Jawa-Bali.</li>
                <li>Peraturan Direksi PT PLN Nusantara Power Nomor 0009.P/019/DIR/2023 tanggal 28 April 2023 tentang Batasan Kewenangan Pengambilan Keputusan PT PLN Nusantara Power.</li>
            </ol>

            <h3>B. Pengertian dan Istilah</h3>
            <p>Dalam dokumen ini dipergunakan pengertian, istilah dan singkatan sebagai berikut:</p>
            <ol>
                <li><b>PT PLN NP</b> adalah Perusahaan perseroan PT PLN Nusantara Power yang dalam hal ini sebagai Pengguna Barang/Jasa.</li>
                <li><b>APLN</b> adalah Anggaran PT PLN NP yang ditetapkan dalam Rencana Kerja dan Anggaran Perusahaan (RKAP) yang telah disahkan oleh RUPS, termasuk anggaran untuk pekerjaan mendesak atau keadaan Darurat (Emergency) yang belum ditetapkan dalam RKAP.</li>
                <li><b>Barang</b> adalah benda dalam berbagai bentuk dan uraian, meliputi antara lain bahan baku, barang setengah jadi, barang jadi/peralatan yang spesifikasinya ditetapkan oleh Pengguna Barang/Jasa.</li>
                <li><b>Daftar Hitam (Blacklist)</b> adalah sanksi yang diberikan PT PLN NP kepada Penyedia Barang/Jasa berupa tidak diperbolehkannya Penyedia Barang/Jasa tersebut mengikuti pengadaan Barang/Jasa di wilayah kerja PT PLN NP Group, dalam jangka waktu tertentu, sebagai akibat dari Wanprestasi atas Perjanjian yang dibuat sebelumnya.</li>
                <li><b>Dokumen Penawaran</b> adalah surat penawaran beserta seluruh dokumen lampirannya yang disiapkan oleh Penyedia Barang/Jasa.</li>
                <li><b>Due Diligence</b> adalah bagian dari Penilaian Kualifikasi untuk melakukan verifikasi langsung, lebih detail, dan komprehensif atas dokumen kualifikasi, kompetensi dan kemampuan usaha Penyedia Barang/Jasa yang memasukkan aplikasi untuk kategori Leverage, Critical/Bottleneck, dan Strategis.</li>
                <li><b>e-Procurement PLN NP</b> adalah aplikasi berbasis website yang menyediakan akses bagi perusahaan calon Penyedia Barang/Jasa PT PLN Nusantara Power untuk melakukan transaksi pengadaan barang/jasa secara daring melalui alamat website https://smartscm.plnnusantarapower.co.id.</li>
                <li><b>Harga Perkiraan Sendiri/HPS</b> adalah perhitungan harga perkiraan dari suatu Barang/Jasa yang dihitung berdasarkan biaya pokok produksi atau estimasi biaya pokok pekerjaan yang disesuaikan dengan kondisi ekonomi terkini dan faktor-faktor lain yang berfungsi untuk melihat kewajaran harga penawaran.</li>
                <li><b>Jadwal Pengadaan</b> adalah rincian waktu proses pengadaan Barang/Jasa.</li>
                <li><b>Klarifikasi</b> adalah kegiatan meminta penjelasan oleh Pejabat Pelaksana Pengadaan kepada Penyedia Barang/Jasa atas substansi penawaran yang kurang jelas dalam rangka evaluasi penawaran, dengan syarat tidak boleh mengubah harga maupun substansi penawaran.</li>
                <li><b>Negosiasi</b> adalah kegiatan untuk pembahasan aspek teknis, harga dan waktu pelaksanaan antara Fungsi Pelaksana Pengadaan dengan Penyedia Barang/Jasa.</li>
                <li><b>Pakta Integritas</b> adalah surat pernyataan yang berisi ikrar untuk mencegah dan tidak melakukan persekongkolan baik vertikal maupun horizontal, serta bertindak dengan penuh kehati-hatian sesuai dengan ketentuan peraturan yang berlaku dalam pelaksanaan Pengadaan Barang/Jasa.</li>
                <li><b>Pejabat Pelaksana Pengadaan</b> adalah pejabat struktural yang bertugas dan bertanggung jawab dalam pelaksanaan Pengadaan Barang/Jasa.</li>
                <li><b>Pejabat Perencana Pengadaan</b> adalah pejabat struktural yang bertugas dan bertanggung jawab dalam perencanaan Pengadaan Barang/Jasa.</li>
                <li><b>Pejabat Pengadaan Berwenang</b> adalah Direksi, Pejabat Struktural satu tingkat di bawah Direksi dan General Manager / Kepala Badan yang mempunyai kewenangan sebagaimana diatur lebih lanjut dalam Peraturan Direksi tentang Kewenangan Pengadaan Barang/Jasa.</li>
                <li><b>Pengguna Barang/Jasa</b> adalah bagian yang berwenang menggunakan, mengelola dan memelihara aset perusahaan agar senantiasa dapat dipergunakan dan dimanfaatkan dengan sebaik-baiknya guna menunjang aktivitas perusahaan, dalam hal ini Direksi, General Manajer, atau Pejabat yang diberi kuasa sebagai pemberi tugas kepada pelaksana pengadaan Barang/Jasa.</li>
                <li><b>Penyedia Barang/Jasa</b> adalah badan usaha yang berbentuk Perseroan Terbatas (PT), Badan Usaha Milik Negara (BUMN), atau badan hukum publik lainnya, Badan Usaha Milik Daerah (BUMD), Lembaga, Konsorsium, Joint Operation, Koperasi, Firma, Commanditaire Vennootschap (CV), persekutuan perdata (Maatschap), badan usaha luar negeri dan/atau perorangan yang kegiatan usahanya menyediakan Barang/Jasa.</li>
                <li><b>Perjanjian/Kontrak</b> adalah perikatan dalam bentuk tertulis antara Pengguna Barang/Jasa dengan Penyedia Barang/Jasa.</li>
                <li><b>Vendor Management System (VMS)</b> adalah aplikasi website yang menyediakan akses bagi Calon Penyedia untuk melakukan registrasi sebagai Daftar Penyedia Perusahaan (DPP), Daftar Penyedia Terseleksi (DPT) dan melakukan pembaharuan data Perusahaan.</li>
            </ol>

            <h3>C. Undangan kepada Calon Penyedia Barang/Jasa</h3>
            <p>PT PLN NP dengan ini bermaksud mengundang Calon Penyedia Barang/Jasa yang berkompeten dalam pelaksanaan <b>{{nama_pengadaan}}</b> pada {{unit_tujuan}} untuk berpartisipasi dalam pengadaan dengan metode <b>{{metode_pengadaan}}</b>.</p>
            <p>Peserta wajib menyampaikan Dokumen Penawaran sesuai dengan ketentuan yang tercantum dalam Rencana Kerja dan Syarat-syarat (RKS) ini. Dokumen Penawaran yang tidak memenuhi syarat dan ketentuan tersebut tidak dapat diikutsertakan dalam proses selanjutnya.</p>
        </section>
        HTML;
    }

    /**
     * BAB II part A and B.
     */
    protected function babInstruksiUmum(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h2 class="bab-heading">BAB II<br>INSTRUKSI KEPADA PESERTA</h2>

            <h3>A. UMUM</h3>

            <h4>1. Lingkup Pekerjaan</h4>
            <p>Lingkup Pekerjaan meliputi <b>{{nama_pengadaan}}</b> pada {{unit_tujuan}}, dengan detail spesifikasi dan persyaratan teknis yang harus dipenuhi sebagaimana diatur dalam Term of Reference (TOR), Lampiran 12.</p>

            <h4>2. Sumber Dana</h4>
            <p>Sumber dana untuk keperluan pelaksanaan {{nama_pengadaan}} adalah menggunakan {{sumber_anggaran_keterangan}} ({{sumber_anggaran}}) PT PLN Nusantara Power.</p>
            <table>
                <tr><th style="width:32%">Nomor PRK</th><td>{{nomor_prk}}</td></tr>
                <tr><th>Nomor PR/RO</th><td>{{nomor_pr_ro}}</td></tr>
                <tr><th>Referensi Pengadaan</th><td>{{nomor_pengadaan}}</td></tr>
            </table>

            <h4>3. Etika Pengadaan</h4>
            <ol type="a">
                <li>Melaksanakan tugas secara tertib, disertai rasa tanggung jawab untuk mencapai sasaran kelancaran dan ketepatan tercapainya tujuan Pengadaan Barang/Jasa.</li>
                <li>Bekerja secara profesional dan mandiri atas dasar kejujuran serta menjaga kerahasiaan Dokumen Rencana Kerja dan Syarat-Syarat yang seharusnya dirahasiakan untuk mencegah terjadinya penyimpangan dalam proses Pengadaan Barang/Jasa.</li>
                <li>Tidak saling mempengaruhi baik langsung maupun tidak langsung untuk mencegah dan menghindari terjadinya persaingan tidak sehat.</li>
                <li>Menerima dan bertanggung jawab atas segala keputusan yang ditetapkan sesuai dengan kesepakatan para pihak.</li>
                <li>Menghindari dan mencegah terjadinya pertentangan kepentingan (conflict of interest) para pihak yang terkait dalam proses Pengadaan Barang/Jasa baik langsung maupun tidak langsung yang merugikan kepentingan Pengguna Barang/Jasa.</li>
                <li>Menghindari dan mencegah terjadinya pemborosan dan kebocoran keuangan perusahaan dalam Pengadaan Barang/Jasa.</li>
                <li>Menghindari dan mencegah penyalahgunaan wewenang dan atau kolusi dengan tujuan untuk kepentingan pribadi, golongan atau pihak lain yang secara langsung atau tidak langsung merugikan perusahaan.</li>
                <li>Tidak menerima, tidak menawarkan dan tidak menjanjikan untuk memberi atau menerima hadiah, imbalan berupa apa saja kepada siapapun yang diketahui atau patut diduga berkaitan dengan Pengadaan Barang/Jasa.</li>
            </ol>

            <h4>4. Syarat Peserta</h4>
            <ol type="a">
                <li>Peserta merupakan Perusahaan Badan Usaha termasuk BUMN atau BUMD atau Badan Usaha Milik Swasta atau Badan Hukum, atau Instansi Pemerintah/Badan Layanan Umum yang kegiatan usahanya menyediakan barang/jasa.</li>
                <li>Peserta adalah Penyedia Barang/Jasa yang tidak sedang menjalani sanksi Blacklist di lingkungan PT PLN NP dan PT PLN (Persero) Group.</li>
                <li>Calon Peserta tidak dalam pengawasan pengadilan, tidak bangkrut, kegiatan usahanya tidak sedang dihentikan dan/atau Direksi yang bertindak untuk dan atas nama perusahaan tidak sedang menjalani sanksi pidana.</li>
                <li>Calon Peserta telah memenuhi kewajiban perpajakan tahun terakhir.</li>
                <li>Bersedia untuk menandatangani Pakta Integritas.</li>
            </ol>

            <h4>5. Dilarang Ikut Sebagai Peserta</h4>
            <ol type="a">
                <li>Mereka yang dinyatakan pailit.</li>
                <li>Mereka yang keikutsertaannya akan bertentangan dengan kepentingan tugasnya (conflict of interest).</li>
                <li>Mereka yang keikutsertaannya dalam satu pengadaan berada dalam satu kesatuan pengaruh pemilik modal dan atau kepengurusan dengan Peserta lain (kecuali BUMN/BUMD) sehingga dapat diperkirakan akan terjadi pengaturan/kerja sama di antara para Peserta atau terjadinya persaingan yang tidak wajar/sehat.</li>
            </ol>

            <h4>6. Pemenuhan Tingkat Komponen Dalam Negeri (TKDN)</h4>
            <p>Peserta wajib semaksimal mungkin memenuhi Tingkat Komponen Dalam Negeri (TKDN) sebagaimana diatur dalam Peraturan Menteri Perindustrian Republik Indonesia Nomor 05/M-IND/PER/2/2017 tentang Perubahan Atas Peraturan Menteri Perindustrian Nomor 54/M-IND/PER/3/2012 tentang Pedoman Penggunaan Produk Dalam Negeri untuk pembangunan infrastruktur ketenagalistrikan.</p>

            <h3>B. RENCANA KERJA DAN SYARAT-SYARAT (RKS)</h3>

            <h4>1. Isi Dokumen Rencana Kerja dan Syarat-Syarat</h4>
            <ol type="a">
                <li>Dokumen Rencana Kerja dan Syarat-Syarat terdiri dari:
                    <ol>
                        <li>Instruksi kepada Peserta.</li>
                        <li>Persiapan Penawaran.</li>
                        <li>Syarat-syarat Perjanjian.</li>
                        <li>Jaminan Penawaran dan Jaminan Pelaksanaan.</li>
                        <li>Lampiran-lampiran.</li>
                    </ol>
                </li>
                <li>Peserta berkewajiban memeriksa keseluruhan isi Dokumen RKS. Kelalaian menyampaikan Dokumen Penawaran yang tidak memenuhi persyaratan yang ditetapkan dalam Dokumen RKS ini sepenuhnya merupakan risiko Peserta.</li>
            </ol>

            <h4>2. Bahasa Rencana Kerja dan Syarat-Syarat</h4>
            <p>Dokumen RKS beserta seluruh korespondensi tertulis dalam proses pengadaan menggunakan Bahasa Indonesia.</p>

            <h4>3. Waktu dan Tempat Pendaftaran Peserta dan Pengambilan RKS</h4>
            <table>
                <tr><th style="width:32%">Hari/Tanggal</th><td>Sesuai Jadwal</td></tr>
                <tr><th>Waktu</th><td>Sesuai Jadwal</td></tr>
                <tr><th>Tempat</th><td>Melalui website e-procurement PT PLN NP</td></tr>
            </table>
            <p class="note">Catatan: Dokumen pengadaan terlampir pada website https://smartscm.plnnusantarapower.co.id. Jangka waktu pendaftaran hingga H-1 dari tanggal Pemasukan Dokumen Penawaran.</p>

            <h4>4. Pemberian Penjelasan RKS (Aanwijzing)</h4>
            <table>
                <tr><th style="width:32%">Hari/Tanggal</th><td>Sesuai Jadwal</td></tr>
                <tr><th>Waktu</th><td>Sesuai Jadwal</td></tr>
                <tr><th>Cara Penjelasan</th><td>Menggunakan aplikasi vicon PLN NP (Lampiran 13)</td></tr>
            </table>
            <ol>
                <li>Peserta (pendaftar atau wakilnya berdasarkan surat kuasa bermeterai cukup) yang mengikuti penjelasan harus mengirimkan softcopy surat kuasa/tugas dan copy tanda pengenal kepada Pejabat Pelaksana Pengadaan.</li>
                <li>Semua permintaan penjelasan terhadap isi Dokumen RKS hanya dapat dilakukan dalam forum Penjelasan (Aanwijzing).</li>
                <li>Ketidakhadiran Peserta pada saat forum Penjelasan (Aanwijzing) tidak dijadikan dasar untuk menggugurkan penawaran.</li>
                <li>Peserta yang tidak hadir pada forum Penjelasan dianggap mengetahui dan menyetujui semua hasil yang telah ditetapkan dalam Berita Acara Penjelasan (BAP).</li>
                <li>Hasil pemberian penjelasan dituangkan dalam Berita Acara Penjelasan (BAP) yang ditandatangani Pejabat Pelaksana Pengadaan dan minimal 1 (satu) wakil dari Peserta yang hadir/online.</li>
                <li>Semua perubahan dalam Dokumen RKS sebagai hasil penjelasan dan/atau jawaban atas pertanyaan Penyedia Barang/Jasa harus dituangkan dalam Addendum RKS.</li>
                <li>Berita Acara Penjelasan (BAP) merupakan bagian tidak terpisahkan dari Dokumen RKS.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II part C and D.
     */
    protected function babPenyiapanPenawaran(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h3>C. PENYIAPAN DOKUMEN PENAWARAN</h3>

            <h4>1. Biaya dalam Penyiapan Dokumen Penawaran</h4>
            <ol type="a">
                <li>Peserta menanggung semua biaya dalam penyiapan dan penyampaian Dokumen Penawaran.</li>
                <li>PT PLN NP tidak bertanggung jawab atas kerugian apapun yang ditanggung oleh Peserta.</li>
            </ol>

            <h4>2. Bahasa Dokumen Penawaran</h4>
            <ol type="a">
                <li>Semua Dokumen Penawaran harus menggunakan Bahasa Indonesia.</li>
                <li>Dokumen penunjang yang terkait dengan Dokumen Penawaran dapat menggunakan Bahasa Indonesia atau bahasa asing.</li>
                <li>Dokumen penunjang yang berbahasa asing perlu disertai penjelasan dalam Bahasa Indonesia. Dalam hal terjadi perbedaan penafsiran maka yang berlaku adalah penjelasan dalam Bahasa Indonesia.</li>
            </ol>

            <h4>3. Dokumen Penawaran</h4>
            <ol type="a">
                <li>Calon Penyedia Barang/Jasa menyampaikan Dokumen Penawaran berupa softcopy yang diunggah di website https://smartscm.plnnusantarapower.co.id (nama file disesuaikan dengan judul dokumen) dan hardcopy yang dikirim ke alamat PT PLN Nusantara Power UP Kendari, Jalan Chairil Anwar No. 2, Kelurahan Mataiwoi, Kecamatan Wua-Wua, Kota Kendari, Sulawesi Tenggara 93118.</li>
                <li>Informasi yang tercantum dalam penawaran Peserta bersifat rahasia.</li>
                <li>Dokumen Penawaran yang memerlukan pengesahan harus ditandatangani setidaknya oleh Pejabat Eksekutif Perusahaan yang berwenang di atas meterai.</li>
                <li>Metode penawaran dilakukan dengan sistem Satu Tahap Satu Sampul. Peserta harus menyerahkan syarat Administrasi, Keuangan, Teknis dan Penawaran Harga dalam satu sampul.</li>
                <li>Dokumen Syarat Administrasi, Keuangan, Teknis, dan Penawaran Harga sebagaimana dimaksud pada butir d di atas meliputi:
                    <ol>
                        <li><b>Syarat Administrasi</b> terdiri dari:
                            <ol type="a">
                                <li>Surat Pernyataan Minat untuk Mengikuti Pengadaan (sesuai Lampiran 9).</li>
                                <li>Pakta Integritas (sesuai Lampiran 6).</li>
                                <li>Asli Surat Pernyataan (sesuai Lampiran 5), di antaranya kesanggupan mematuhi dan memenuhi semua ketentuan yang ditetapkan dalam Dokumen RKS; pernyataan perusahaan tidak dalam keadaan bangkrut, Direktur tidak dalam pengawasan pengadilan dan tidak sedang menjalani sanksi pidana; serta pernyataan tidak akan menuntut ganti rugi dalam bentuk apapun jika proses Pengadaan dihentikan, dinyatakan gagal, atau terjadi pemutusan Perjanjian/Kontrak oleh Pengguna Barang/Jasa.</li>
                                <li>Copy Nomor Induk Berusaha (NIB) dengan kode KBLI yang sesuai dan masih berlaku efektif.</li>
                                <li>Terdaftar sebagai Daftar Penyedia Perusahaan (DPP) PT PLN NP, dibuktikan dengan melampirkan copy sertifikat DPP.</li>
                                <li>Surat keterangan domisili kantor yang berkedudukan di Kota Kendari atau sekitarnya yang diterbitkan oleh pemerintah setempat.</li>
                            </ol>
                        </li>
                        <li><b>Syarat Keuangan</b> terdiri dari laporan keuangan tahun terakhir yang telah diaudit oleh auditor independen/akuntan publik atau hasil rating/pemeringkatan keuangan dari lembaga pemeringkat keuangan yang kredibel.</li>
                        <li><b>Syarat Teknis</b> terdiri dari:
                            <ol type="a">
                                <li>Asli Dokumen Spesifikasi Barang/Jasa yang ditawarkan (sesuai Lampiran 2), sesuai dengan spesifikasi barang/jasa yang dimintakan penawaran (sesuai Lampiran 1).</li>
                                <li>Copy sertifikat CSMS yang diterbitkan oleh PLN Nusantara Power dengan kriteria level risiko minimal Tinggi.</li>
                                <li>Daftar pengalaman pelaksanaan pekerjaan sejenis beserta deskripsi pekerjaan yang pernah/sedang dilaksanakan pada PLN Nusantara Power (sesuai Lampiran 7).</li>
                            </ol>
                        </li>
                        <li><b>Dokumen Penawaran</b> terdiri dari Surat Penawaran Harga dan total harga penawaran (sesuai Lampiran 3) serta daftar rincian harga penawaran (sesuai Lampiran 4).</li>
                    </ol>
                </li>
            </ol>
            <p class="note">Catatan: Apabila ada hal-hal yang kurang jelas dan/atau meragukan terhadap surat/data administrasi yang bersangkutan, maka Pelaksana Pengadaan dapat melakukan klarifikasi/konfirmasi dengan pihak terkait/institusi yang menerbitkannya. Bila diperlukan, PT PLN NP UP Kendari akan melakukan Due Diligence terkait ketentuan yang dipersyaratkan bagi Peserta meliputi keabsahan dokumen, kesiapan peralatan, dan hal-hal lain yang terkait. Adapun biaya yang timbul menjadi tanggung jawab PT PLN NP.</p>

            <h4>4. Harga Penawaran</h4>
            <ol type="a">
                <li>Harga penawaran adalah harga Pengadaan Barang/Jasa pada site Unit Pengguna Barang/Jasa.</li>
                <li>Surat Penawaran harus ditandatangani oleh Pimpinan Perusahaan atau penerima kuasa dari Pimpinan Perusahaan, atau Kepala Cabang Perusahaan yang diangkat oleh Kantor Pusat dan dibuktikan dengan dokumen otentik, bertanggal, bermeterai Rp 10.000,- (sepuluh ribu rupiah).</li>
                <li>Harga penawaran ditulis dalam angka dan huruf. Apabila terdapat perbedaan antara penulisan nilai dalam angka dan huruf maka nilai penawaran yang diakui adalah nilai dalam tulisan huruf.</li>
                <li>Peserta mencantumkan harga satuan Barang/Jasa untuk tiap pembayaran dalam Daftar Kuantitas dan Harga.</li>
                <li>Harga penawaran sudah termasuk pajak-pajak yang berlaku.</li>
            </ol>

            <h4>5. Mata Uang Penawaran dan Cara Pembayaran</h4>
            <ol type="a">
                <li>Semua harga dalam penawaran harus dalam bentuk mata uang Rupiah (IDR).</li>
                <li>Cara pembayaran atas pelaksanaan pengadaan Barang/Jasa akan diuraikan sesuai ketentuan dalam RKS ini.</li>
            </ol>

            <h4>6. Dokumen Penawaran Tidak Sah atau Gugur</h4>
            <ol type="a">
                <li>Pada saat pembukaan penawaran tidak memenuhi persyaratan administrasi, keuangan dan teknis yang ditentukan sesuai dengan Dokumen RKS ini.</li>
                <li>Softcopy dan hardcopy harga penawaran tidak ditandatangani oleh Peserta di atas meterai Rp 10.000,- dan tidak diberi cap perusahaan.</li>
                <li>Softcopy jaminan penawaran tidak diunggah pada website https://smartscm.plnnusantarapower.co.id dan/atau tidak menyerahkan asli jaminan penawaran sesuai batasan waktu yang telah ditetapkan pada RKS ini.</li>
                <li>Masa berlaku jaminan penawaran tidak sesuai dengan persyaratan yang telah ditetapkan pada RKS ini.</li>
                <li>Dokumen Penawaran yang belum bermeterai cukup, belum bertanggal, belum ditandatangani dan belum diberi cap perusahaan akan dianggap sah apabila kekurangannya dapat dipenuhi sebelum batas waktu pemasukan dokumen penawaran. Bila kekurangan tersebut tidak dapat dipenuhi, maka dokumen penawaran tersebut dinyatakan tidak sah.</li>
            </ol>

            <h4>7. Masa Berlaku Penawaran</h4>
            <p>Masa berlaku penawaran adalah selama 90 (sembilan puluh) hari kalender.</p>

            <h4>8. Jaminan Penawaran (Bid Bond)</h4>
            <p>Jaminan Penawaran tidak dipersyaratkan untuk Perusahaan BUMN/PT PLN (Persero)/Anak Perusahaan PT PLN (Persero)/Perusahaan Terafiliasi PT PLN (Persero)/Anak Perusahaan PT PLN Nusantara Power/Perusahaan Terafiliasi PT PLN Nusantara Power, serta untuk pengadaan yang menggunakan metode Penunjukan Langsung/Repeat Order.</p>

            <h3>D. PEMASUKAN DOKUMEN PENAWARAN</h3>

            <h4>1. Penyampaian Dokumen Penawaran</h4>
            <ol type="a">
                <li>Pemasukan penawaran:
                    <ol>
                        <li>Peserta menyampaikan Dokumen Penawaran yang dilengkapi dengan nomor dan tanggal Surat Penawaran.</li>
                        <li>Tanggal Surat Penawaran harus dalam rentang waktu pemasukan penawaran.</li>
                        <li>Surat Penawaran harus ditandatangani oleh Pimpinan Perusahaan atau penerima kuasa dari Pimpinan Perusahaan atau Kepala Cabang Perusahaan yang diangkat oleh Kantor Pusat dan dibuktikan dengan dokumen otentik, bertanggal, bermeterai Rp 10.000,- dan distempel.</li>
                    </ol>
                </li>
                <li>Penyampaian dokumen penawaran dilakukan dengan diunggah ke website https://smartscm.plnnusantarapower.co.id pada paket pengadaan {{nama_pengadaan}}, serta mengirimkan dokumen hardcopy ke alamat PT PLN Nusantara Power UP Kendari, Jalan Chairil Anwar No. 2, Kelurahan Mataiwoi, Kecamatan Wua-Wua, Kota Kendari, Provinsi Sulawesi Tenggara 93118, hingga batas waktu penyampaian dokumen penawaran.</li>
            </ol>

            <h4>2. Batas Waktu Penyampaian Dokumen Penawaran</h4>
            <table>
                <tr><th style="width:32%">Hari/Tanggal</th><td>Sesuai Jadwal</td></tr>
                <tr><th>Waktu</th><td>Sesuai Jadwal</td></tr>
            </table>

            <h4>3. Perubahan dan Keterlambatan Dokumen Penawaran</h4>
            <ol type="a">
                <li>Perubahan penawaran dapat dilakukan sebelum batas akhir waktu pemasukan penawaran.</li>
                <li>Penarikan penawaran tidak dapat dilakukan setelah batas akhir waktu pemasukan penawaran. Apabila dilakukan maka Jaminan Penawaran dicairkan dan menjadi milik PT PLN NP.</li>
                <li>Dokumen Penawaran yang diterima setelah batas waktu pemasukan penawaran tidak diikutsertakan.</li>
                <li>Peserta yang mendaftar namun tidak memasukkan Dokumen Penawaran tanpa alasan profesional dikenakan sanksi.</li>
                <li>PT PLN NP tidak memberikan ganti rugi kepada Peserta bila penawarannya ditolak atau proses penunjukan dinyatakan gagal/batal.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II part E and F.
     */
    protected function babEvaluasi(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h3>E. PEMBUKAAN DAN EVALUASI PENAWARAN</h3>

            <h4>1. Pembukaan Penawaran</h4>
            <table>
                <tr><th style="width:32%">Hari/Tanggal/Waktu</th><td>Sesuai Jadwal</td></tr>
                <tr><th>Tempat</th><td>Kantor PLN NP UP Kendari</td></tr>
            </table>
            <p>Pembukaan penawaran dengan metode Satu Tahap Satu Sampul:</p>
            <ol>
                <li>Pejabat Pengadaan memeriksa kelengkapan (ADA/TIDAK) dokumen penawaran yang terunggah pada website dan dokumen hardcopy yang dikirimkan, yang kemudian menghasilkan keputusan LENGKAP/TIDAK LENGKAP atas dokumen penawaran tersebut.</li>
                <li>Apabila terdapat persyaratan dokumen penawaran yang tidak dapat diakses atau dibuka pada saat pembukaan dokumen penawaran (corrupt), maka dokumen hardcopy dapat menjadi acuan pembukaan dokumen.</li>
                <li>Apabila persyaratan Dokumen Penawaran dinilai TIDAK LENGKAP, maka penawaran tersebut tidak dilanjutkan ke tahap evaluasi atau dinyatakan GUGUR.</li>
                <li>Pembukaan Penawaran dilakukan dengan menggunakan website https://smartscm.plnnusantarapower.co.id dan aplikasi vicon PT PLN NP (jika diperlukan).</li>
                <li>Pembukaan Penawaran dilakukan di hadapan Calon Penyedia Barang/Jasa yang hadir/online serta disaksikan minimal 2 (dua) orang saksi dari wakil Calon Penyedia Barang/Jasa, untuk selanjutnya dibacakan serta dicatat dan dijadikan lampiran Berita Acara Pembukaan Penawaran.</li>
                <li>Membuat Berita Acara Pembukaan Penawaran (BAPP) yang berisikan hal-hal dan data pokok yang penting termasuk informasi yang diperoleh pada saat pembukaan penawaran.</li>
                <li>Pelaksana Pengadaan menandatangani BAPP dan disepakati minimal bersama 2 (dua) orang saksi dari Calon Penyedia Barang/Jasa yang hadir/online.</li>
                <li>Dalam hal saksi dari wakil Calon Penyedia Barang/Jasa tidak ada, Pejabat Pelaksana Pengadaan dapat menunjuk saksi di luar Pejabat Pelaksana Pengadaan untuk menandatangani BAPP.</li>
            </ol>

            <h4>2. Evaluasi Dokumen Penawaran</h4>
            <ol type="a">
                <li>Dalam proses pengadaan dengan metode Satu Tahap Satu Sampul ini, Evaluasi Dokumen Penawaran dilakukan sesuai urutan: Administrasi, Keuangan, Teknis dan Penawaran Harga.</li>
                <li>Evaluasi terhadap masing-masing persyaratan pada butir a dilakukan dengan menggunakan metode SISTEM GUGUR. Penilaian menghasilkan dua kesimpulan, yaitu memenuhi kualifikasi (LULUS) atau tidak memenuhi kualifikasi (GUGUR).</li>
                <li>Evaluasi dilakukan berdasarkan data dan informasi yang ada dalam Dokumen Penawaran (softfile dan hardcopy) yang telah diunggah dan dikirimkan oleh Peserta. Apabila terdapat persyaratan dokumen penawaran yang tidak dapat diakses atau dibuka pada saat evaluasi (corrupt), maka dokumen hardcopy menjadi acuan evaluasi.</li>
                <li>Apabila terdapat perbedaan dokumen antara softfile dan hardcopy maka persyaratan tersebut dinilai tidak memenuhi (GUGUR).</li>
                <li>Tahapan Evaluasi Dokumen Penawaran meliputi:
                    <ol>
                        <li><b>Evaluasi Syarat Administrasi.</b> Menghasilkan 2 (dua) kesimpulan, yaitu Memenuhi Syarat Administrasi atau Tidak Memenuhi Syarat Administrasi. Syarat Administrasi dinyatakan GUGUR apabila tidak menyampaikan kelengkapan dokumen yang dipersyaratkan; tidak bisa membuktikan keabsahan dokumen yang disampaikan; pemilik modal atau pengurus suatu perusahaan Calon Penyedia menjadi pemilik modal dan/atau pengurus perusahaan lain sesama Calon Penyedia; atau dokumen penawaran dan surat pernyataan tidak ditandatangani oleh pejabat yang berwenang.</li>
                        <li><b>Evaluasi Syarat Keuangan.</b> Dilakukan apabila penawaran memenuhi/lulus Syarat Administrasi. Dinyatakan GUGUR apabila tidak menyampaikan kelengkapan dokumen yang dipersyaratkan atau tidak bisa membuktikan keabsahan dokumen yang disampaikan.</li>
                        <li><b>Evaluasi Syarat Teknis.</b> Dilakukan apabila penawaran dinyatakan memenuhi/lulus Syarat Administrasi dan Keuangan. Faktor yang dievaluasi antara lain spesifikasi teknis, jumlah, waktu penyerahan, jenis pekerjaan dan syarat teknik sesuai dengan dokumen teknik. Dinyatakan GUGUR apabila kelengkapan dokumen tidak memenuhi; tidak bersedia menandatangani surat pernyataan teknis; spesifikasi teknis yang ditawarkan tidak sesuai dengan yang dipersyaratkan dalam TOR; waktu penyerahan melebihi waktu yang ditentukan pada RKS; atau tidak bisa membuktikan keabsahan dokumen pada saat klarifikasi/due diligence.</li>
                        <li><b>Evaluasi Penawaran Harga.</b> Dilakukan terhadap peserta yang dinyatakan memenuhi/lulus Syarat Administrasi, Keuangan dan Teknis. Penawaran dinyatakan gugur apabila tidak menyampaikan kelengkapan dokumen yang dipersyaratkan; jangka waktu berlakunya surat penawaran kurang dari 90 (sembilan puluh) hari; atau Jaminan Penawaran tidak memenuhi persyaratan, yaitu tidak diterbitkan oleh Bank Mandiri, BNI atau BRI; memiliki masa berlaku kurang dari 120 (seratus dua puluh) hari kalender terhitung sejak tanggal pembukaan penawaran; nama dan alamat penyedia tidak sesuai dengan surat keterangan domisili perusahaan; nilai jaminan kurang dari 1% dari nilai penawaran; besar jumlah jaminan dalam angka dan huruf tidak sama; nama dan alamat pemberi pekerjaan tidak sesuai; atau nama paket pekerjaan yang dijamin tidak sesuai dengan yang telah ditentukan.</li>
                    </ol>
                </li>
                <li>Dalam hal terjadi perbedaan antara harga penawaran yang tercantum dalam Surat Penawaran dengan Rincian Penawaran, maka yang berlaku adalah harga penawaran yang tercantum pada Surat Penawaran bermeterai cukup.</li>
                <li>Evaluasi penawaran harga dilakukan dengan metode Sistem Gugur; penawaran harga akan dibandingkan dengan HPS yang telah ditetapkan. Penawaran harga di atas HPS tidak menggugurkan dan tetap akan dievaluasi.</li>
                <li>Bilamana dipandang perlu, PT PLN NP dapat meminta Calon Penyedia Barang/Jasa untuk melengkapi data isian formulir kualifikasi tambahan. Apabila tidak dipenuhi maka menjadi risiko Calon Penyedia Barang/Jasa.</li>
                <li>Apabila ditemui data/keterangan yang disampaikan tidak benar dan ada pemalsuan, maka Calon Penyedia Barang/Jasa digugurkan dan dimasukkan dalam daftar hitam (Blacklist) serta tidak diperkenankan ikut serta dalam Pengadaan Barang/Jasa di lingkungan PT PLN NP Group selama 24 (dua puluh empat) bulan.</li>
            </ol>

            <h3>F. NEGOSIASI PENAWARAN DAN KLARIFIKASI</h3>

            <h4>1. Negosiasi Penawaran</h4>
            <ol type="a">
                <li>Klarifikasi dan negosiasi penawaran dilakukan melalui website https://smartscm.plnnusantarapower.co.id.</li>
                <li>Negosiasi dilakukan untuk mencapai kesepakatan antara PT PLN NP dengan Penyedia Barang/Jasa dalam teknis, waktu pelaksanaan, dan harga terbaik, dimulai dari urutan Peserta dengan Penawaran Harga terendah.</li>
                <li>Negosiasi dilakukan oleh Pelaksana Pengadaan dengan Direktur Utama/pimpinan perusahaan, penerima kuasa dari Direktur Utama/pimpinan perusahaan, atau Kepala Cabang perusahaan yang diangkat oleh kantor pusat yang dibuktikan dengan softcopy dokumen otentik yang dikirimkan kepada email Pejabat Pelaksana Pengadaan.</li>
                <li>Negosiasi dilakukan dengan ketentuan sebagai berikut:
                    <ol>
                        <li>Dalam hal semua penawaran Peserta di atas HPS, maka proses dilanjutkan dengan negosiasi, dimulai dari Peserta dengan Penawaran Harga terendah yang telah dinyatakan MEMENUHI persyaratan Administrasi, Keuangan, dan Teknik.</li>
                        <li>Apabila proses negosiasi tidak mencapai kesepakatan/tetap di atas HPS, maka Calon Penyedia dengan Penawaran Harga terendah tersebut dinyatakan TIDAK MEMENUHI, selanjutnya dilakukan evaluasi terhadap Peserta dengan Harga Penawaran terendah kedua dengan ketentuan telah dinyatakan MEMENUHI persyaratan Administrasi, Keuangan, dan Teknik.</li>
                        <li>Tahapan tersebut juga berlaku untuk Peserta dengan Harga Penawaran terendah ketiga, keempat, dan seterusnya.</li>
                        <li>Apabila proses negosiasi kepada seluruh Peserta tidak mencapai kesepakatan, maka pengadaan dinyatakan GAGAL.</li>
                        <li>Dalam hal semua atau sebagian penawaran Peserta di bawah HPS, maka tetap dilakukan negosiasi kepada Peserta dengan Penawaran Harga terendah yang telah dinyatakan MEMENUHI persyaratan Administrasi, Keuangan, dan Teknik.</li>
                    </ol>
                </li>
                <li>Terhadap harga penawaran yang telah dinegosiasi dan memenuhi ketentuan pada butir d di atas disebut sebagai Harga Akhir dan akan menjadi Harga Kontrak.</li>
            </ol>

            <h4>2. Dilakukan Klarifikasi Apabila</h4>
            <ol type="a">
                <li>Dalam hal penawaran di bawah 80% (delapan puluh persen) dari HPS, maka harus dilakukan klarifikasi secara tertulis kepada Peserta. Apabila Peserta tersebut ditunjuk sebagai pemenang pengadaan, maka Peserta dapat diminta untuk menaikkan Jaminan Pelaksanaan menjadi sebesar persentase Jaminan Pelaksanaan (yang diatur dalam Dokumen RKS) terhadap HPS.</li>
                <li>Hasil pelaksanaan klarifikasi dan negosiasi dituangkan dalam Berita Acara Klarifikasi dan Negosiasi yang ditandatangani fungsi pelaksana pengadaan dan disepakati oleh calon Penyedia Barang/Jasa.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II part G to L.
     */
    protected function babPenetapanDanPenunjukan(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h3>G. PENETAPAN PEMENANG</h3>
            <ol type="a">
                <li>Pemenang dalam pengadaan ini adalah Peserta yang lolos evaluasi dan menyetujui Harga Akhir.</li>
                <li>PT PLN NP menetapkan pemenang pengadaan dan mengeluarkan Surat Penetapan Penyedia Barang/Jasa.</li>
                <li>Apabila terjadi keterlambatan dalam menetapkan pemenang pengadaan dan mengakibatkan penawaran habis masa berlakunya, maka diminta kepada Peserta untuk memperpanjang masa berlaku Surat Penawaran.</li>
                <li>Hasil penetapan pemenang akan diumumkan secara parsial kepada Peserta yang telah memasukkan penawaran.</li>
            </ol>

            <h3>H. MASA SANGGAH DAN JAMINAN SANGGAH</h3>
            <ol>
                <li>Peserta yang berkeberatan atas penetapan Calon Pemenang diberikan kesempatan untuk mengajukan sanggahan secara tertulis yang berkaitan dengan kesesuaian pelaksanaan pengadaan dengan prosedur atau tata cara pengadaan dalam Dokumen RKS.</li>
                <li>Sanggahan ditujukan kepada Pejabat yang Berwenang, dalam hal ini Kepala Divisi Supply Chain Management PT PLN NP Kantor Pusat, dengan tembusan kepada Kepala Satuan Pengawasan Intern (KSPI).</li>
                <li>Sanggahan yang disampaikan pihak lain di luar peserta tidak akan dijawab. Peserta yang menggunakan pihak lain untuk menyampaikan sanggahan dan/atau mempengaruhi pihak PT PLN NP akan menjadi catatan itikad tidak baik atas Penyedia Barang/Jasa tersebut.</li>
                <li>Jangka waktu penyampaian sanggahan maksimal 2 (dua) hari kerja sejak diumumkannya pemenang pengadaan.</li>
                <li>Pejabat yang Berwenang wajib memberikan jawaban secara tertulis atas substansi masalah yang disanggah disertai bukti secara proporsional selambat-lambatnya dalam 7 (tujuh) hari kerja sejak tanggal diterimanya pengajuan sanggah.</li>
                <li>Dalam hal sanggahan ditolak, maka Penyedia Barang/Jasa dapat mengajukan Sanggah Banding kepada Direktur Utama PT PLN NP dengan tembusan kepada Kepala Satuan Pengawasan Intern, selambat-lambatnya dalam waktu 3 (tiga) hari kerja sejak diterimanya jawaban sanggah, disertai bukti terjadinya penyimpangan terhadap ketentuan pengadaan.</li>
                <li>Pejabat yang Berwenang wajib memberikan jawaban tertulis atas Sanggah Banding selambat-lambatnya 7 (tujuh) hari kerja sejak tanggal pengajuan sanggah banding diterima.</li>
                <li>Sanggah/Sanggah Banding hanya diberikan 1 (satu) kali dan jawaban atas Sanggah Banding bersifat final dan mengikat.</li>
                <li>Apabila isi dari Sanggah/Sanggah Banding dinyatakan benar, maka akan dilakukan penilaian kembali atau pengumuman pelelangan ulang, dan Pelaksana Pengadaan wajib mengembalikan Jaminan Sanggah.</li>
                <li>Apabila isi dari Sanggah/Sanggah Banding dinyatakan tidak benar dan cenderung mengada-ada, maka yang bersangkutan tidak diikutsertakan pada proses pengadaan, Jaminan Sanggah dicairkan dan sepenuhnya menjadi milik PT PLN NP, serta dikenakan sanksi Blacklist selama 12 (dua belas) bulan.</li>
                <li>Peserta yang keberatan dan tidak mengajukan sanggahan secara tertulis tetapi menyebarkan ke publik dapat dikenakan sanksi Blacklist selama 24 (dua puluh empat) bulan, dan apabila ternyata mengada-ada maka dikenakan sanksi Blacklist selama 60 (enam puluh) bulan.</li>
            </ol>

            <h3>I. PENUNJUKAN PEMENANG</h3>
            <ol type="a">
                <li>Pejabat yang Berwenang akan menerbitkan Surat Penunjukan Penyedia Barang/Jasa (SPPBJ) dengan ketentuan: setelah tidak ada sanggah/sanggah banding dari Peserta; sanggah/sanggah banding yang diterima dalam masa sanggah terbukti tidak benar; dan masa sanggah berakhir.</li>
                <li>Peserta yang ditunjuk sebagai Penyedia Barang/Jasa wajib menerima keputusan tersebut. Apabila yang bersangkutan mengundurkan diri, maka Jaminan Penawaran akan dicairkan dan menjadi milik PT PLN NP, serta diberikan sanksi Blacklist selama 60 (enam puluh) bulan.</li>
                <li>Apabila Peserta yang ditunjuk mengundurkan diri dan/atau tidak bersedia, maka akan ditetapkan Peserta dengan harga penawaran terendah kedua (bila ada) mengacu pada harga pemenang urutan pertama, dengan ketentuan telah dinyatakan MEMENUHI persyaratan Administrasi, Keuangan dan Teknik; penetapan telah mendapat persetujuan Pengguna Barang/Jasa; serta masa berlaku penawaran dan Jaminan Penawaran masih berlaku atau telah diperpanjang.</li>
                <li>Apabila Peserta dengan harga penawaran terendah kedua tidak bersedia, maka akan ditetapkan Peserta dengan harga penawaran terendah ketiga dan seterusnya sesuai ketentuan di atas.</li>
                <li>Evaluasi penawaran yang disimpulkan dalam Berita Acara Hasil Pelelangan (BAHP) oleh Pejabat Pelaksana Pengadaan bersifat rahasia sampai dengan saat pengumuman pemenang.</li>
            </ol>

            <h3>J. JAMINAN PELAKSANAAN (PERFORMANCE BOND)</h3>
            <p>Jaminan Pelaksanaan tidak dipersyaratkan untuk PT PLN (Persero)/Anak Perusahaan PT PLN (Persero)/Perusahaan Terafiliasi PT PLN (Persero)/Anak Perusahaan PT PLN Nusantara Power/Perusahaan Terafiliasi PT PLN Nusantara Power.</p>

            <h3>K. PENGHENTIAN PROSES PENGADAAN BARANG/JASA</h3>
            <ol>
                <li>Penghentian proses Pengadaan Barang/Jasa dapat dilakukan oleh Pengguna Barang/Jasa antara lain karena: adanya perubahan kebijakan Pemerintah terkait pelaksanaan Pengadaan Barang/Jasa; adanya gangguan/kerusakan sistem yang menyebabkan terhambatnya proses pengadaan; adanya perubahan kebijakan strategis Perusahaan; alokasi pendanaan Pengadaan Barang/Jasa tidak mencukupi; terjadi indikasi adanya persekongkolan vertikal atau horizontal; berdasarkan pertimbangan tidak menguntungkan Perusahaan atau tidak memenuhi prinsip Good Corporate Governance (GCG); atau setelah dilakukan pengadaan ulang masih tetap tidak ada penawaran yang memenuhi persyaratan.</li>
                <li>PT PLN NP berhak menghentikan proses pengadaan secara sepihak dan/atau berhak melakukan proses pengadaan dengan metode yang sama atau berbeda.</li>
            </ol>

            <h3>L. PENGADAAN BARANG/JASA GAGAL</h3>
            <ol>
                <li>Pengguna Barang/Jasa menyatakan pengadaan gagal dalam hal: terjadi perubahan rencana kerja yang mengakibatkan perubahan kebutuhan Barang/Jasa; tidak ada penawaran yang memenuhi persyaratan dalam RKS; tidak ada Peserta yang memasukkan penawaran; negosiasi tidak berhasil mencapai kesepakatan; adanya indikasi kuat terjadinya persaingan usaha yang tidak sehat; adanya indikasi terjadinya Korupsi, Kolusi dan Nepotisme (KKN); sanggahan dari Peserta ternyata benar; berdasarkan rekomendasi dari Value for Money Committee; atau akibat adanya penetapan pengadilan.</li>
                <li>Dalam hal pengadaan gagal, Pejabat Pelaksana Pengadaan menyampaikan pemberitahuan Pengadaan Gagal kepada Peserta dan melakukan pengadaan ulang, dengan atau tanpa revisi Dokumen RKS untuk disesuaikan dengan penyebab pengadaan gagal.</li>
                <li>Dalam hal terjadi revisi Dokumen RKS, maka revisi dilakukan oleh Pejabat Perencana Pengadaan.</li>
                <li>Pengguna Barang/Jasa tidak memberikan ganti rugi kepada Peserta apabila penawarannya ditolak atau pengadaan dinyatakan gagal.</li>
                <li>Dalam hal Peserta tidak memasukkan dokumen penawaran, maka Pejabat Pelaksana Pengadaan menyatakan Pengadaan Gagal dan melaporkannya kepada Pengguna Barang/Jasa.</li>
            </ol>
        </section>
        HTML;
    }

    /**
     * BAB II part M.
     */
    protected function babSyaratPerjanjian(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h3>M. SYARAT-SYARAT PERJANJIAN</h3>

            <h4>1. Penandatanganan Perjanjian</h4>
            <p>Perjanjian/Kontrak diterbitkan oleh PT PLN NP yang ditandatangani oleh Pengguna Barang/Jasa dan memuat kesepakatan harga satuan Barang/Jasa tertentu dalam kurun waktu tertentu dan spesifikasi tertentu, dengan ketentuan sebagai berikut:</p>
            <ol type="a">
                <li>Penandatanganan Perjanjian dilakukan setelah Penyedia Barang/Jasa menyerahkan Jaminan Pelaksanaan dengan ketentuan sebagaimana dimaksud dalam RKS ini.</li>
                <li>Pengguna dan Penyedia Barang/Jasa wajib memeriksa konsep Perjanjian meliputi substansi, bahasa/redaksional, angka, dan huruf serta membubuhkan paraf pada lembar demi lembar dokumen Perjanjian.</li>
                <li>Banyaknya rangkap Perjanjian dibuat sesuai kebutuhan, sekurang-kurangnya 2 (dua) Perjanjian asli. Perjanjian asli pertama untuk Pengguna Barang/Jasa dibubuhi meterai pada bagian yang ditandatangani oleh Penyedia Barang/Jasa, dan Perjanjian asli kedua untuk Penyedia Barang/Jasa dibubuhi meterai pada bagian yang ditandatangani oleh Pengguna Barang/Jasa.</li>
                <li>Dalam hal terjadi penghentian dan pemutusan perjanjian terhadap Penyedia Barang/Jasa, maka Pengguna Barang/Jasa berhak dan berwenang sepenuhnya untuk mengalihkan pekerjaan kepada Penyedia Barang/Jasa lainnya berdasarkan urutan pemenang.</li>
            </ol>

            <h4>2. Definisi</h4>
            <ol type="a">
                <li><b>Barang/Jasa</b> adalah pekerjaan dengan detail spesifikasi teknik sebagaimana diatur pada Term of Reference (TOR).</li>
                <li><b>Berita Acara Pemeriksaan Barang</b> adalah Berita Acara yang memuat hasil pemeriksaan dan/atau pengujian yang dilaksanakan oleh Panitia Pemeriksa Kualitas Barang/Jasa terhadap barang/material yang diserahkan Penyedia Barang/Jasa.</li>
                <li><b>Berita Acara Penyelesaian Pekerjaan</b> adalah berita acara yang memuat hasil pemeriksaan dan/atau pengujian yang dilaksanakan oleh Panitia Pemeriksa Kualitas Barang/Jasa terhadap hasil jasa yang telah diselesaikan oleh Penyedia Barang/Jasa.</li>
                <li><b>Hari Kalender</b> adalah semua hari dalam sebulan termasuk akhir pekan dan hari libur nasional yang ditetapkan oleh Pemerintah Indonesia.</li>
                <li><b>Hari Kerja</b> adalah hari Senin sampai dengan Jumat dikurangi hari libur nasional yang ditetapkan oleh Pemerintah Indonesia.</li>
                <li><b>Direksi Pekerjaan</b> adalah {{direksi_pekerjaan}}.</li>
                <li><b>Site</b> adalah area kerja Pengguna Barang/Jasa atau tempat yang ditunjuk Pengguna Barang/Jasa, dalam hal ini {{unit_tujuan}}.</li>
                <li><b>Panitia Pemeriksa Kualitas Barang/Jasa</b> adalah satuan kerja dari Pengguna Barang/Jasa yang bertugas memeriksa kualitas barang dan/atau jasa yang telah dikirim/diserahkan oleh Penyedia Barang/Jasa.</li>
                <li><b>(N/A)</b> adalah Not Available (tidak ada/tidak dipersyaratkan).</li>
                <li><b>Perjanjian</b> adalah Perjanjian ini termasuk perubahan, tambahan dan lampiran-lampirannya.</li>
            </ol>

            <h4>3. Harga</h4>
            <ol type="a">
                <li>Harga kontrak adalah harga penawaran setelah dilakukan negosiasi, di mana harga tersebut sudah mencakup pajak-pajak dan beban biaya atas persyaratan yang telah ditentukan dalam Perjanjian. Nilai Harga Perkiraan untuk pekerjaan ini adalah {{nilai_hpe}} ({{nilai_hpe_terbilang}}).</li>
                <li>Harga satuan pekerjaan adalah tetap atau tidak berubah (fixed price) sampai dengan Pengguna Barang/Jasa dan Penyedia Barang/Jasa selesai melaksanakan hak dan kewajibannya berdasarkan Perjanjian ini.</li>
                <li>Penyedia Barang/Jasa tidak dapat menuntut perubahan harga pekerjaan dan/atau tambahan biaya walaupun terjadi kenaikan harga yang berhubungan dengan pelaksanaan Perjanjian ini, kecuali atas penetapan Pemerintah.</li>
            </ol>

            <h4>4. Waktu Pelaksanaan</h4>
            <ol type="a">
                <li>Jangka waktu pelaksanaan Kontrak/Perjanjian ditetapkan selama <span class="fill">..........</span> (<span class="fill">..........................</span>) bulan, terhitung sejak tanggal Surat Perintah Kerja diterbitkan, dengan target penyelesaian {{target_penyelesaian}}.</li>
                <li>Kriteria diterimanya pekerjaan sesuai dengan poin Quality Acceptance pada Term of Reference (TOR), Lampiran 12.</li>
                <li>Pada saat penyerahan pekerjaan harus dilakukan dengan penerbitan Berita Acara Serah Terima Pekerjaan (BASTP). Sebagai pelengkap, Penyedia Barang/Jasa wajib menyampaikan Laporan Hasil Pekerjaan berupa hardcopy sebanyak 2 (dua) buah dan softcopy, Berita Acara dan SLA (Service Level Agreement), copy dokumen Kontrak/Perjanjian dan amandemennya (jika ada), serta penilaian vendor yang ditandatangani oleh PT PLN NP UP Kendari.</li>
            </ol>

            <h4>5. Dokumen Pelaksanaan Pekerjaan</h4>
            <p>Dalam pelaksanaan pekerjaan, Penyedia Barang/Jasa diwajibkan membuat Laporan Hasil Pekerjaan yang selanjutnya diserahkan kepada Pengguna Barang/Jasa untuk mendapatkan persetujuan, dengan rincian dokumen sesuai kebutuhan pekerjaan sebagaimana diatur dalam Term of Reference (TOR).</p>

            <h4>6. Cara Pembayaran</h4>
            <ol type="a">
                <li>Pembayaran akan dilakukan setelah Penyedia Barang/Jasa menyerahkan dengan baik seluruh pekerjaan beserta dokumen penyerahannya dan dinyatakan diterima oleh Panitia Pemeriksa Barang/Jasa yang dibuktikan dengan diterbitkannya Berita Acara Penyelesaian Pekerjaan/Berita Acara Serah Terima Pekerjaan.</li>
                <li>Permintaan pembayaran ditujukan kepada PT PLN Nusantara Power UP Kendari.</li>
                <li>Pembayaran dilakukan dengan ditransfer ke nomor rekening bank yang ditunjuk oleh Penyedia Barang/Jasa.</li>
                <li>Biaya-biaya yang timbul pada bank yang ditunjuk oleh Penyedia Barang/Jasa sehubungan dengan transaksi pembayaran menjadi beban dan tanggung jawab Penyedia Barang/Jasa.</li>
            </ol>

            <h4>7. Masa Garansi dan Jaminan Masa Garansi</h4>
            <p>Ketentuan masa garansi dan jaminan masa garansi mengikuti pengaturan pada Term of Reference (TOR) dan Perjanjian/Kontrak pekerjaan ini.</p>

            <h4>8. Sanksi</h4>
            <ol type="a">
                <li>Sanksi denda merupakan sanksi finansial yang dikenakan kepada Penyedia Barang/Jasa karena terjadinya cidera janji (wanprestatie) yang tercantum dalam Perjanjian.</li>
                <li>Sanksi denda berlaku untuk masing-masing judul pengadaan dan tidak terikat satu sama lain.</li>
                <li>Dalam hal terjadi keterlambatan penyerahan Barang/Jasa berikut dokumen penyerahannya secara lengkap melampaui batas waktu yang ditentukan dalam Dokumen RKS ini, maka Penyedia Barang/Jasa dikenakan sanksi berupa denda keterlambatan sebesar 1&permil; (satu per mil) dari nilai Surat Perjanjian/Kontrak untuk setiap hari keterlambatan dengan maksimum 5% (lima persen) dari nilai Perjanjian, kecuali bila keterlambatan dimaksud disebabkan oleh Force Majeure atau alasan yang berhubungan dengan kesalahan Pengguna Barang/Jasa.</li>
                <li>Denda keterlambatan atas penyerahan barang/jasa akan langsung dikenakan pada saat pelaksanaan pembayaran.</li>
                <li>Apabila sampai dengan batas akhir waktu penyerahan Penyedia Barang/Jasa belum melakukan penyerahan, maka Pengguna Barang/Jasa akan memberikan Surat Peringatan Pertama. Apabila dalam 7 (tujuh) hari kalender setelah surat peringatan pertama Penyedia Barang/Jasa tidak memberikan respons secara tertulis, maka Pengguna Barang/Jasa akan memberikan Surat Peringatan Kedua sekaligus yang terakhir. Apabila dalam 7 (tujuh) hari kalender setelah diterimanya surat peringatan kedua Penyedia Barang/Jasa tidak memberikan respons secara tertulis, maka Pengguna Barang/Jasa berhak memutus sebagian atau seluruh Perjanjian secara sepihak dan berhak menjatuhkan sanksi Blacklist selama 24 (dua puluh empat) bulan.</li>
                <li>Apabila Penyedia Barang/Jasa masih menyatakan kesanggupan untuk memenuhi kewajibannya walaupun telah melebihi jangka waktu penyerahan dan Pengguna Barang/Jasa menyetujui, maka Penyedia Barang/Jasa tetap diperkenankan melanjutkan proses penyerahan dengan konsekuensi tetap dikenakan denda keterlambatan.</li>
                <li>Apabila setelah mendapatkan persetujuan Pengguna Barang/Jasa, Penyedia Barang/Jasa belum dapat melakukan penyerahan atau keterlambatan melebihi batas waktu maksimum denda keterlambatan (50 hari kalender), maka Pengguna Barang/Jasa berhak melakukan pemutusan Perjanjian secara sepihak tanpa Surat Peringatan dan menjatuhkan sanksi Blacklist selama minimum 24 (dua puluh empat) bulan.</li>
            </ol>

            <h4>9. Pengakhiran dan Pemutusan Perjanjian/Kontrak</h4>
            <ol type="a">
                <li>Pengakhiran Perjanjian dapat terjadi apabila berakhirnya jangka waktu penyerahan pekerjaan dengan terpenuhinya hak dan kewajiban Para Pihak; diakhiri berdasarkan kesepakatan Para Pihak karena adanya peristiwa Force Majeure; atau diakhiri berdasarkan kesepakatan Para Pihak dengan pemberitahuan paling lambat 30 (tiga puluh) hari kalender sebelumnya disertai kesepakatan kompensasi ganti rugi dan pelunasan kewajiban.</li>
                <li>Apabila terjadi pengakhiran Perjanjian sebagaimana dimaksud pada butir a, maka Jaminan Pelaksanaan akan dikembalikan oleh PT PLN NP UP Kendari kepada Penyedia Barang/Jasa.</li>
                <li>PT PLN NP UP Kendari dapat memutus Perjanjian secara sepihak tanpa didahului Surat Peringatan apabila Penyedia Barang/Jasa mengundurkan diri setelah penandatanganan Perjanjian; telah dinyatakan pailit oleh Pengadilan Niaga; terbukti melakukan pelanggaran atas hak paten, merek terdaftar, desain, hak cipta atau HAKI lainnya; sanksi/denda keterlambatan sudah melampaui besarnya Jaminan Pelaksanaan atau maksimum denda keterlambatan; terbukti melakukan tindakan yang mengakibatkan ketidakwajaran dalam pelaksanaan Perjanjian termasuk penipuan, persekongkolan, penyuapan, korupsi, kecurangan dan pemalsuan; melakukan pengalihan pekerjaan kepada pihak lain tanpa persetujuan Pengguna Barang/Jasa; atau tidak bersedia melakukan perpanjangan/penggantian Jaminan Pelaksanaan.</li>
                <li>Pemutusan Perjanjian dengan peringatan tertulis dilakukan melalui Surat Peringatan Pertama (SP-1), dan apabila dalam 7 (tujuh) hari kalender tidak ada respons maka diterbitkan Surat Peringatan Kedua (SP-2) sekaligus terakhir. Apabila dalam 7 (tujuh) hari kalender setelah SP-2 tetap tidak ada respons, maka Pengguna Barang/Jasa berhak menerbitkan Usulan Pemutusan Perjanjian dan Usulan Penjatuhan Sanksi Blacklist kepada Kepala Divisi Supply Chain Management PT PLN NP Kantor Pusat.</li>
                <li>Apabila terjadi pemutusan Perjanjian secara sepihak, maka PT PLN NP UP Kendari berhak melakukan pencairan Jaminan Pelaksanaan yang sepenuhnya menjadi milik PT PLN NP, dan berhak menjatuhkan sanksi Blacklist kepada Penyedia Barang/Jasa selama minimum 24 (dua puluh empat) bulan.</li>
                <li>Dalam hal terjadi pemutusan Perjanjian, Para Pihak sepakat untuk tidak memberlakukan ketentuan Pasal 1266 dan Pasal 1267 Kitab Undang-Undang Hukum Perdata.</li>
            </ol>

            <h4>10. Kewajiban Perpajakan</h4>
            <ol type="a">
                <li>Pajak-pajak yang menjadi kewajiban Penyedia Barang/Jasa sehubungan dengan pekerjaan ini wajib ditanggung oleh Penyedia Barang/Jasa dan Penyedia Barang/Jasa bertanggung jawab sepenuhnya atas pelunasan pajak dimaksud.</li>
                <li>Penyedia Barang/Jasa wajib menyimpan segala dokumen permintaan pembayaran seperti tagihan, faktur pajak, faktur pajak pengganti, bukti faktur pajak batal dan pelaporannya, serta bukti pembayaran pajak yang dilakukan.</li>
                <li>Penyedia Barang/Jasa berkewajiban menerbitkan faktur pajak sebagai syarat pembayaran secara benar. Apabila terdapat kerusakan dan/atau kesalahan pengisian sehingga tidak memuat keterangan yang lengkap, jelas dan benar, maka wajib diterbitkan faktur pajak pengganti atau faktur pajak baru serta dilakukan pembatalan dan pelaporan sesuai ketentuan yang berlaku.</li>
                <li>Apabila terdapat data atau informasi dari Kantor Pelayanan Pajak mengenai transaksi PT PLN NP UP Kendari dengan Penyedia Barang/Jasa, maka PT PLN NP UP Kendari berhak meminta klarifikasi dan Penyedia Barang/Jasa wajib memberikan keterangan tertulis beserta pembuktiannya paling lama 7 (tujuh) hari kalender setelah diterbitkan Surat Permintaan Klarifikasi.</li>
                <li>Apabila PT PLN NP UP Kendari menerima ketetapan dan/atau tagihan pajak yang menyebabkan kerugian akibat kelalaian/kesalahan administrasi perpajakan Penyedia Barang/Jasa, maka kerugian tersebut menjadi beban dan tanggung jawab Penyedia Barang/Jasa.</li>
            </ol>

            <h4>11. Risiko dan Tanggung Jawab</h4>
            <p>Semua risiko terhadap kerusakan atau kehilangan Barang tetap berada pada Penyedia Barang/Jasa dan tidak akan beralih kepada Pemberi Kerja sampai dengan tempat tujuan pengiriman dan dilakukan serah terima yang dibuktikan dengan diterbitkannya Berita Acara Pemeriksaan Barang.</p>

            <h4>12. Pengalihan Pekerjaan Kepada Pihak Lain</h4>
            <ol type="a">
                <li>Penyedia Barang/Jasa tidak diperbolehkan mengalihkan sebagian maupun seluruh hak dan kewajibannya berdasarkan Perjanjian ini kepada pihak lain tanpa persetujuan tertulis terlebih dahulu dari Pemberi Kerja.</li>
                <li>Dalam hal pengalihan dilakukan berdasarkan persetujuan tertulis dari Pemberi Kerja, maka seluruh kerugian yang timbul sebagai akibat pengalihan pekerjaan menjadi beban dan tanggung jawab Penyedia Barang/Jasa.</li>
            </ol>

            <h4>13. Penundaan Penyelesaian Pekerjaan</h4>
            <ol type="a">
                <li>Pemberi Kerja mempunyai hak memerintahkan penundaan dan memulai kembali seluruh atau sebagian pelaksanaan pekerjaan tanpa membatalkan ketentuan yang ada dalam Perjanjian.</li>
                <li>Perintah untuk melakukan penundaan dan memulai kembali pelaksanaan pekerjaan dikeluarkan secara tertulis oleh Pemberi Kerja kepada Penyedia Barang/Jasa.</li>
                <li>Waktu penyelesaian pekerjaan akan ditambah sesuai dengan waktu yang hilang akibat penundaan pelaksanaan pekerjaan (suspend), yang dibuktikan dengan dokumen yang dapat dipertanggungjawabkan.</li>
            </ol>

            <h4>14. Keselamatan dan Kesehatan Kerja (K3), Keamanan dan Kebersihan Lingkungan</h4>
            <ol type="a">
                <li>Penyedia Barang/Jasa harus memenuhi persyaratan Keselamatan dan Kesehatan Kerja (K3) yang diatur oleh Menteri Tenaga Kerja RI.</li>
                <li>Penyedia Barang/Jasa wajib mematuhi kebijakan dan prosedur Sistem Manajemen Keselamatan dan Kesehatan Kerja (SMK3) dan Sistem Manajemen Lingkungan (SML) yang telah ditetapkan di area kerja PLN NP UP Kendari.</li>
                <li>Penyedia Barang/Jasa wajib mengasuransikan semua pekerjanya yang akan melaksanakan pekerjaan di area kerja. Selama pelaksanaan pekerjaan, Pengguna Barang/Jasa tidak bertanggung jawab terhadap kejadian kecelakaan kerja yang terjadi pada pekerja Penyedia Barang/Jasa.</li>
                <li>Penyedia Barang/Jasa wajib mengganti kerusakan peralatan dalam area kerja yang diakibatkan oleh pelaksanaan pekerjaan.</li>
                <li>Penyedia Barang/Jasa wajib mematuhi poin-poin Aspek K3L dan Keamanan sebagaimana diatur dalam Term of Reference (TOR), Lampiran 12.</li>
                <li>Apabila Penyedia Barang/Jasa terbukti tidak memenuhi ketentuan pada pasal ini, maka Pengguna Barang/Jasa akan memutus Perjanjian secara sepihak dan Penyedia Barang/Jasa dikenakan sanksi Blacklist selama minimum 24 (dua puluh empat) bulan.</li>
            </ol>

            <h4>15. Keadaan Kahar (Force Majeure)</h4>
            <ol type="a">
                <li>Keadaan Kahar adalah setiap keadaan yang berada di luar kontrol yang wajar dan langsung dari pihak yang terkena, termasuk tetapi tidak terbatas pada kerusuhan, perang, bencana alam, bencana non-alam, bencana sosial, endemik, epidemik, pandemik, pemogokan nasional, terorisme dan embargo, sepanjang situasi tersebut tidak dapat dicegah, dihindari atau dipindahkan; mempengaruhi secara materiil kemampuan pihak yang terkena; bukan akibat kegagalan salah satu pihak; dan telah diberitahukan kepada pihak lainnya.</li>
                <li>Pihak yang terkena Force Majeure wajib memberitahukan secara tertulis kepada pihak lainnya selambat-lambatnya 7 (tujuh) hari kalender terhitung sejak terjadinya peristiwa Force Majeure.</li>
                <li>Apabila pemberitahuan tertulis tidak disampaikan dan hal tersebut berdampak pada keterlambatan penyerahan hasil pekerjaan, maka keterlambatan dimaksud dianggap bukan sebagai akibat Force Majeure.</li>
                <li>Pemberitahuan mengenai Force Majeure harus disertai keterangan dari pihak yang berwenang, sekaligus pihak yang terkena dapat mengajukan permohonan perpanjangan waktu penyelesaian pekerjaan.</li>
                <li>Pihak yang tidak terkena Force Majeure dalam waktu 7 (tujuh) hari kalender sejak diterimanya pemberitahuan akan memberikan jawaban secara tertulis. Apabila tidak memberikan jawaban, maka dianggap telah memberikan persetujuan.</li>
                <li>Apabila pihak yang terkena Force Majeure adalah Penyedia Barang/Jasa, maka Penyedia Barang/Jasa tidak dapat dikenakan sanksi atas keterlambatan penyelesaian pekerjaan yang diakibatkan oleh Force Majeure.</li>
                <li>Apabila Force Majeure berlanjut sampai dengan 180 (seratus delapan puluh) hari kalender, maka salah satu pihak berhak mengakhiri Perjanjian dengan pemberitahuan tertulis yang efektif berlaku 14 (empat belas) hari kerja setelah tanggal pemberitahuan.</li>
                <li>Dalam hal terjadi pengakhiran Perjanjian yang telah disepakati Para Pihak, maka Para Pihak tidak dapat mengajukan tuntutan hukum dan tidak memberikan sanksi/denda keterlambatan maupun sanksi Blacklist, kecuali kewajiban pembayaran atas pekerjaan yang telah dilaksanakan sampai sebelum terjadinya Force Majeure.</li>
            </ol>

            <h4>16. Penyelesaian Perselisihan</h4>
            <ol type="a">
                <li>Apabila timbul perselisihan dalam rangka pelaksanaan Perjanjian ini, Para Pihak sepakat untuk menyelesaikan dengan musyawarah.</li>
                <li>Perjanjian ini tunduk pada Peraturan Perundang-undangan Republik Indonesia.</li>
                <li>Segala sengketa yang tidak dapat diselesaikan dengan musyawarah akan diselesaikan melalui Badan Arbitrase Nasional Indonesia (BANI) di Jakarta sesuai dengan prosedur dan tata cara yang berlaku di BANI.</li>
                <li>Keputusan BANI tidak dapat diganggu gugat dan bersifat terakhir. Para Pihak tidak akan mengajukan banding kepada pengadilan atas keputusan tersebut.</li>
                <li>Sambil menunggu penyelesaian atas suatu sengketa, Para Pihak akan tetap memenuhi kewajibannya berdasarkan Perjanjian ini.</li>
            </ol>

            <h4>17. Pekerjaan Tambah dan Pekerjaan Kurang</h4>
            <ol type="a">
                <li>Pekerjaan tambah dan/atau pekerjaan kurang hanya dianggap sah apabila ada perintah secara tertulis dari Pengguna Barang/Jasa.</li>
                <li>Perubahan nilai perjanjian akibat pekerjaan tambah mengacu kepada ketentuan Petunjuk Teknis Pengadaan Barang dan Jasa Pengguna Barang/Jasa.</li>
                <li>Perubahan nilai Perjanjian atas pekerjaan tambah dan/atau kurang akan dituangkan dalam Addendum Perjanjian. Lingkup pekerjaan tambah/kurang yang sudah diatur dalam Perjanjian dihitung menggunakan harga satuan sebagaimana tercantum dalam Perjanjian; yang belum diatur ditentukan berdasarkan kesepakatan Para Pihak.</li>
                <li>Addendum Perjanjian tidak dapat dijadikan dasar perubahan waktu penyerahan barang dan jasa, kecuali ada persetujuan tertulis dari PT PLN NP UP Kendari.</li>
                <li>Apabila pekerjaan kurang mengakibatkan pengurangan volume pekerjaan, maka pengurangan dimaksud tidak dapat dipakai oleh Penyedia Barang/Jasa sebagai dasar tuntutan ganti rugi atau tuntutan hilangnya keuntungan.</li>
            </ol>

            <h4>18. Perubahan-Perubahan</h4>
            <ol type="a">
                <li>Para Pihak sepakat bahwa setiap perubahan dalam Perjanjian ini hanya dapat dilakukan atas persetujuan bersama.</li>
                <li>Usulan perubahan harus diajukan secara tertulis oleh pihak yang berkepentingan kepada pihak lainnya sebelum berlakunya perubahan yang diusulkan.</li>
                <li>Perubahan yang telah disepakati dan ditandatangani oleh Para Pihak dituangkan ke dalam Addendum dan merupakan bagian yang tidak terpisahkan dari Perjanjian.</li>
            </ol>

            <h4>19. Syarat Lainnya</h4>
            <p>Persyaratan lain akan diuraikan dalam Perjanjian/Kontrak yang menjadi bagian tak terpisahkan dari Dokumen Rencana Kerja dan Syarat-Syarat.</p>
        </section>
        HTML;
    }

    /**
     * BAB III.
     */
    protected function babKepatuhan(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h2 class="bab-heading">BAB III<br>KEPATUHAN TERHADAP HUKUM DAN ANTI PENYUAPAN</h2>

            <ol>
                <li>Para Pihak menyepakati bahwa pada saat melaksanakan Perjanjian ini berdasarkan pada prinsip itikad baik, tidak saling mempengaruhi baik langsung maupun tidak langsung, menerima serta bertanggung jawab atas segala keputusan yang ditetapkan sesuai dengan kesepakatan Para Pihak, menghindari serta mencegah terjadinya pertentangan kepentingan (conflict of interest), menghindari serta mencegah penyalahgunaan wewenang dan/atau kolusi dengan tujuan untuk keuntungan pribadi, golongan atau pihak lain, dan tidak menerima, tidak menawarkan atau tidak menjanjikan untuk memberi atau menerima hadiah atau imbalan berupa apa saja kepada siapapun yang diketahui atau patut diduga berkaitan dengan pelaksanaan Perjanjian ini.</li>
                <li>Para Pihak menyepakati bahwa dalam pelaksanaan Perjanjian ini wajib patuh dan selalu mengambil tindakan yang cukup untuk memastikan subkontraktor, agen, atau pihak lain yang menjadi subjek kendali agar patuh terhadap setiap hukum Indonesia yang berlaku, tidak terbatas pada Undang-Undang Nomor 31 Tahun 1999 juncto Undang-Undang Nomor 20 Tahun 2001 tentang Pemberantasan Tindak Pidana Korupsi dan Undang-Undang Nomor 8 Tahun 2010 tentang Pencegahan dan Pemberantasan Tindak Pidana Pencucian Uang.</li>
                <li>Para Pihak dengan ini menjamin untuk tidak akan membayar, menawarkan, menjanjikan atau setuju untuk membayar, secara langsung atau tidak langsung, sehubungan dengan pelaksanaan Perjanjian ini atau setiap potensi proyek, setiap kontribusi, biaya, komisi politik, atau pembayaran yang tidak semestinya atau manfaat lain kepada pegawai atau perwakilan pemerintah atau perseorangan swasta yang mengakibatkan timbulnya pembayaran yang tidak semestinya.</li>
                <li>Para Pihak selanjutnya menjamin untuk tidak, secara langsung atau tidak langsung, sehubungan dengan Perjanjian ini dan bisnis yang timbul karenanya, menawarkan, membayar, berjanji untuk membayar, atau mengizinkan pemberian uang atau hal lainnya yang bernilai kepada pegawai atau perwakilan pemerintah atau pihak manapun, dengan tujuan mempengaruhi tindakan atau keputusan dari pegawai tersebut dalam kapasitasnya, termasuk keputusan untuk tidak melaksanakan tugas kedinasannya; atau mendorong pegawai tersebut untuk menggunakan pengaruhnya kepada institusi pemerintah untuk mempengaruhi tindakan atau keputusan apapun dari institusi pemerintah tersebut.</li>
                <li>Para Pihak menyepakati bahwa tidak akan melakukan tindakan-tindakan yang mengakibatkan terjadinya ketidakwajaran dalam pelaksanaan Perjanjian ini termasuk namun tidak terbatas pada tindakan penipuan, persekongkolan, penyuapan, korupsi, kecurangan, pemalsuan dan tindakan lain yang bertentangan dengan peraturan yang berlaku dan tidak sesuai dengan etika bisnis yang baik.</li>
            </ol>

            <p>Apabila Penyedia Barang/Jasa terbukti tidak memenuhi ketentuan sebagaimana dimaksud dalam bab ini, maka Pengguna Barang/Jasa berhak memutus Perjanjian ini secara sepihak. Jaminan Garansi berupa Bank Garansi (apabila terjadi saat masa garansi) akan dicairkan dan sepenuhnya menjadi milik Pengguna Barang/Jasa, serta Penyedia Barang/Jasa dikenakan sanksi Blacklist selama minimum 24 (dua puluh empat) bulan sejak tanggal penjatuhan sanksi.</p>
        </section>
        HTML;
    }

    /**
     * BAB IV and the signature block.
     */
    protected function babPenutup(): string
    {
        return <<<'HTML'
        <section class="bab">
            <h2 class="bab-heading">BAB IV<br>PENUTUP</h2>

            <p>Perubahan atau penambahan atas hal-hal lain yang belum tercakup dalam RKS ini akan dicantumkan dalam Berita Acara Penjelasan (Aanwijzing) dan Addendum RKS yang merupakan bagian yang tidak terpisahkan dari RKS ini.</p>

            <p style="margin-top:24pt; text-align:right;">Kendari, {{tanggal_dokumen}}</p>
            <p style="text-align:right; font-weight:bold;">PT PLN NUSANTARA POWER<br>UP KENDARI</p>

            <table class="signature">
                <tr>
                    <td class="role">Diperiksa Oleh<br>TL Inventori Kontrol &amp; Gudang</td>
                    <td class="role">Dibuat Oleh<br>PIC Perencana</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">..................................</td>
                    <td class="name">{{pic_perencana}}</td>
                </tr>
            </table>

            <table class="signature">
                <tr>
                    <td class="role">Mengesahkan<br>Manager UP Kendari</td>
                    <td class="role">Disetujui<br>{{direksi_pekerjaan}}</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">..................................</td>
                    <td class="name fill">..................................</td>
                </tr>
            </table>
        </section>
        HTML;
    }

    /**
     * The attachment forms.
     */
    protected function lampiran(): string
    {
        return <<<'HTML'
        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 1 : Spesifikasi Barang/Jasa yang Dimintakan Penawaran</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>SPESIFIKASI BARANG/JASA YANG DIMINTAKAN PENAWARAN</h3>
            <table>
                <tr><th style="width:8%">No.</th><th>Nama Barang dan Spesifikasi</th><th style="width:12%">Qty</th><th style="width:12%">Stn</th></tr>
                <tr><td>1</td><td>{{nama_pengadaan}} &mdash; {{unit_tujuan}}</td><td>1</td><td>LOT</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 2 : Spesifikasi Barang/Jasa yang Ditawarkan</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>SPESIFIKASI BARANG/JASA YANG DITAWARKAN</h3>
            <table>
                <tr><th style="width:8%">No</th><th>Nama Pekerjaan</th><th style="width:15%">Jumlah</th><th style="width:25%">Waktu Pelaksanaan Pekerjaan</th></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            </table>
            <p class="note">Keterangan: Penawaran yang disampaikan harus mencantumkan detail spesifikasi pekerjaan.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 3 : Contoh Surat Penawaran</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Nomor : <span class="fill">..........................</span> &nbsp;&nbsp; Tanggal : <span class="fill">..........................</span><br>Lampiran : <span class="fill">..........................</span></p>
            <p>Kepada<br><b>DIVISI SUPPLY CHAIN MANAGEMENT<br>PT PLN NUSANTARA POWER UP KENDARI</b><br>Jalan Chairil Anwar No. 2, Wua-Wua, Kendari</p>
            <p>Perihal : Penawaran Administrasi, Keuangan, Teknis, dan Harga</p>
            <p>Yang bertanda tangan di bawah ini : <span class="fill">.......................................................... (A)</span><br>
               Dalam hal ini diwakili oleh : <span class="fill">.......................................................... (B)</span><br>
               Jabatan dalam perusahaan : <span class="fill">.......................................................... (C)</span></p>
            <p>Dengan ini menyatakan :</p>
            <ol>
                <li>Tunduk pada ketentuan-ketentuan pengadaan yang berlaku di PT PLN NP.</li>
                <li>Bersedia melaksanakan pekerjaan {{nama_pengadaan}} sesuai dengan syarat-syarat yang tercantum dalam RKS Nomor <span class="fill">..................</span> tanggal <span class="fill">..................</span> dan Berita Acara Penjelasan Nomor <span class="fill">..................</span> tanggal <span class="fill">..................</span>.</li>
                <li>Waktu penyerahan adalah <span class="fill">..........</span> (<span class="fill">..................</span>) bulan terhitung sejak tanggal Surat Penunjukan.</li>
                <li>Harga Penawaran:
                    <table>
                        <tr><th style="width:60%">Harga Barang/Jasa</th><td class="fill">&nbsp;</td></tr>
                        <tr><th>Pajak Pertambahan Nilai (PPN)</th><td class="fill">&nbsp;</td></tr>
                        <tr><th>Jumlah Penawaran</th><td class="fill">&nbsp;</td></tr>
                        <tr><th>Terbilang</th><td class="fill">&nbsp;</td></tr>
                    </table>
                </li>
                <li>Rincian penawaran harga setiap Barang/Jasa seperti terlampir.</li>
                <li>Penawaran tersebut mengikat dalam jangka waktu 90 (sembilan puluh) hari kalender terhitung sejak tanggal pembukaan surat penawaran dan dapat diperpanjang bila diperlukan.</li>
                <li>Terlampir kami sampaikan data kelengkapan dokumen penawaran.</li>
            </ol>
            <p>Demikian penawaran ini, atas perhatiannya kami ucapkan terima kasih.</p>
            <table class="signature">
                <tr><td class="role">PT <span class="fill">..................................</span> (D)</td></tr>
                <tr><td class="space"></td></tr>
                <tr><td class="name fill">( Nama Jelas ) (E)</td></tr>
            </table>
            <p class="note">Keterangan: A = Nama dan Alamat Perusahaan; B = Nama yang mewakili Perusahaan; C = Jabatan yang mewakili Perusahaan; D = Tanda tangan penawar dan stempel perusahaan (asli di atas meterai Rp 10.000,-); E = Jabatan. Butir B&ndash;E adalah pejabat yang diatur kewenangannya berdasarkan Akta Pendirian Perusahaan dan perubahannya.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 4 : Contoh Daftar Rincian Harga</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>DAFTAR RINCIAN HARGA PENAWARAN</h3>
            <table>
                <tr><th>No</th><th>Nama Barang/Jasa</th><th>Jumlah</th><th>Satuan</th><th>Harga Satuan</th><th>Jumlah (Rp)</th><th>Waktu Penyerahan</th></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><th colspan="5">SUB TOTAL</th><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><th colspan="5">PPN <span class="fill">......</span>%</th><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><th colspan="5">TOTAL HARGA PENAWARAN</th><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><th colspan="5">TERBILANG</th><td colspan="2">&nbsp;</td></tr>
            </table>
            <p style="text-align:right">Paraf dan Stempel Perusahaan</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 5 : Contoh Surat Pernyataan</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Kepada<br><b>PELAKSANA PENGADAAN / MANAJER PELAKSANA PENGADAAN BARANG/JASA PEMBANGKIT<br>DIV. SUPPLY CHAIN MANAGEMENT</b><br>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Wua-Wua, Kendari</p>
            <p>Perihal : Surat Pernyataan</p>
            <p>Yang bertanda tangan di bawah ini:<br>Nama : <span class="fill">..................................</span><br>Mewakili : PT <span class="fill">..................................</span><br>Jabatan : <span class="fill">..................................</span></p>
            <p>Sehubungan dengan pengadaan {{nama_pengadaan}} sesuai RKS tersebut di atas, dengan ini kami menyatakan hal-hal sebagai berikut:</p>
            <ol>
                <li>Perusahaan kami sanggup mematuhi dan memenuhi semua ketentuan yang ditetapkan dalam Dokumen Rencana Kerja dan Syarat-Syarat.</li>
                <li>Perusahaan kami sanggup memenuhi Persyaratan Teknis/Term of Reference (TOR).</li>
                <li>Perusahaan kami tidak dalam keadaan bangkrut.</li>
                <li>Direktur perusahaan kami tidak dalam pengawasan pengadilan dan tidak sedang menjalani sanksi pidana.</li>
                <li>Perusahaan kami tidak akan menuntut ganti rugi dalam bentuk apapun jika pengadaan ini dinyatakan batal atau penawaran ditolak.</li>
                <li>Barang/jasa yang akan diserahkan sesuai dengan spesifikasi teknik yang diminta dan dijamin dapat berfungsi dengan baik.</li>
                <li>Apabila dalam masa garansi ternyata barang tidak memenuhi fungsi yang dipersyaratkan atau terdapat cacat/kerusakan bukan karena kesalahan operasi, maka kami sanggup memperbaiki atau mengganti bagian yang rusak dengan yang baru.</li>
                <li>Perusahaan kami tidak mempunyai hubungan/sangkut paut dengan perusahaan lain yang sedang bermasalah dengan PT PLN NP.</li>
                <li>Apabila data/pernyataan yang kami sampaikan ternyata ada yang palsu, maka kami bersedia dikenakan sanksi tidak diperkenankan mengikuti pengadaan Barang/Jasa di lingkungan PT PLN NP Group selama 24 (dua puluh empat) bulan.</li>
                <li>Bertanggung jawab penuh dan sekaligus membebaskan PT PLN NP dari segala tuntutan atas pelanggaran Hak Kekayaan Intelektual.</li>
                <li>Perusahaan kami tidak sedang menjalani sanksi Blacklist di lingkungan PT PLN NP Group.</li>
            </ol>
            <p>Demikian Surat Pernyataan ini dibuat dengan sebenarnya, untuk digunakan sebagaimana mestinya.</p>
            <table class="signature">
                <tr><td class="role">Kendari, <span class="fill">..........................</span> {{tahun}}<br>Nama Penyedia Barang/Jasa</td></tr>
                <tr><td class="space">Tanda tangan dan stempel perusahaan<br>(asli di atas meterai Rp 10.000,-)</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; Jabatan</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 6 : Contoh Pakta Integritas</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>PAKTA INTEGRITAS</h3>
            <p>Saya yang bertanda tangan di bawah ini menyatakan:</p>
            <ol>
                <li>Tidak akan melakukan praktek KKN.</li>
                <li>Akan melakukan praktek persaingan yang sehat dalam proses pengadaan.</li>
                <li>Akan melaporkan kepada pihak yang berwajib/berwenang apabila mengetahui ada indikasi KKN dan/atau praktek persaingan yang tidak sehat dalam proses pengadaan.</li>
                <li>Dalam proses pengadaan ini berjanji akan melaksanakan secara bersih, transparan, dan profesional dalam arti akan mengerahkan segala kemampuan dan sumber daya secara optimal untuk memberikan hasil kerja terbaik mulai dari penawaran, pelaksanaan dan penyelesaian pekerjaan.</li>
                <li>Meningkatkan penggunaan produksi dalam negeri dengan memperbesar TKDN sesuai ketentuan yang berlaku dan menggunakan produk berstandar.</li>
                <li>Dalam keadaan tertentu akan mengikutsertakan usaha mikro, usaha kecil dan koperasi kecil sesuai kompetensi teknis yang dimiliki untuk bagian pekerjaan yang bukan pekerjaan utama.</li>
                <li>Dalam melakukan pengadaan akan selalu berpegang pada konsep ramah lingkungan.</li>
                <li>Apabila saya melanggar hal-hal yang telah saya nyatakan dalam Pakta Integritas ini, saya bersedia dikenakan sanksi moral, sanksi administrasi serta dituntut ganti rugi dan pidana sesuai dengan ketentuan peraturan perundang-undangan yang berlaku.</li>
            </ol>
            <table class="signature">
                <tr><td class="role"><span class="fill">.................. (nama kota)</span>, <span class="fill">.......... (tanggal bulan tahun)</span><br>Nama Penyedia Barang/Jasa &mdash; Jabatan</td></tr>
                <tr><td class="space">Tanda tangan dan stempel perusahaan<br>(asli di atas meterai Rp 10.000,-)</td></tr>
                <tr><td class="name fill">( Nama Jelas )</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 7 : Contoh Daftar Referensi Pengalaman Pekerjaan</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>DAFTAR REFERENSI PENGALAMAN/PEKERJAAN SEJENIS</h3>
            <table>
                <tr><th>No</th><th>Uraian</th><th>Data Teknik<br>(jenis/type, kapasitas, dsb)</th><th>Data Pemakai<br>(nama, alamat, kontak person)</th><th>Kontrak<br>(nomor, tanggal, tahun operasi)</th><th>Ket</th></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 8 : Ketentuan Blacklist</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>KETENTUAN BLACKLIST</h3>
            <p>Hal-hal yang dapat menyebabkan Penyedia Barang/Jasa masuk dalam Blacklist atau daftar hitam perusahaan adalah:</p>
            <h4>Selama 6 bulan</h4>
            <ol>
                <li>Tidak memperbaharui persyaratan sebagai Penyedia yang telah habis masa berlakunya lebih dari 1 bulan.</li>
                <li>Penyedia yang mendaftar untuk ikut pengadaan namun tidak memasukkan Dokumen Penawaran tanpa alasan yang profesional.</li>
                <li>Penyedia yang terdaftar dalam DPP tidak memberikan respons atau merespons dengan alasan yang tidak profesional pada saat diundang untuk mengikuti pengadaan selama 3 (tiga) kali tidak berturut-turut.</li>
            </ol>
            <h4>Selama 12 bulan</h4>
            <ol>
                <li>Pelanggaran ketiga atas alasan sebagaimana dikenakan sanksi 6 (enam) bulan.</li>
                <li>Apabila sanggahan tidak benar dan cenderung mengada-ada.</li>
                <li>Peserta yang lulus kualifikasi dan diundang untuk memasukkan penawaran namun tidak memasukkan Dokumen Penawaran.</li>
                <li>Peserta menyatakan tidak mampu melaksanakan pengadaan sesuai Dokumen Pengadaan atau tidak bersedia menambah nilai jaminan pelaksanaannya.</li>
            </ol>
            <h4>Selama 24 bulan</h4>
            <ol>
                <li>Pelanggaran keempat atas alasan sanksi 6 (enam) bulan, atau pelanggaran kedua atas alasan sanksi 12 (dua belas) bulan.</li>
                <li>Melakukan kecurangan pada saat pengumuman pengadaan, misalnya dengan menghalangi tersebarnya pengumuman.</li>
                <li>Melakukan kecurangan dalam proses pengadaan, termasuk persekongkolan dengan pihak lain atau menghalang-halangi pihak lain terlibat dalam pengadaan.</li>
                <li>Berusaha mempengaruhi Pejabat Pengadaan/Pelaksana Pengadaan/Pejabat yang Berwenang dalam bentuk dan cara apapun guna memenuhi keinginannya yang bertentangan dengan ketentuan dan prosedur yang telah ditetapkan.</li>
                <li>Memalsukan persyaratan sebagai Penyedia.</li>
                <li>Penyedia yang berada dalam satu kekuatan pengaruh pemilik modal dan/atau kepengurusan sehingga mengurangi/menghambat/meniadakan persaingan yang sehat dan/atau merugikan orang lain.</li>
                <li>Penyedia yang keberatan atas proses pengadaan dan tidak mengajukan sanggahan secara tertulis tetapi menyebarkan ke publik dan ternyata informasi tersebut benar.</li>
                <li>Penyedia memalsukan data tingkat komponen dalam negeri atau standarisasi produk.</li>
                <li>Tidak mengutamakan Usaha Mikro, Usaha Kecil atau Koperasi Kecil sebagaimana disyaratkan dalam Kontrak.</li>
                <li>Mengundurkan diri pada saat akan ditetapkan sebagai pemenang atau tidak bersedia menandatangani kontrak dengan alasan yang profesional.</li>
                <li>Penyedia yang lalai/tidak bersedia memperbaiki cacat mutu/kerusakan karena mutu pada masa pemeliharaan/garansi.</li>
                <li>Mensubkontrakkan sebagian pekerjaan spesialis kepada yang bukan spesialis.</li>
                <li>Penyedia lalai atau tidak menyelesaikan kontrak sehingga dikenai sanksi pemutusan kontrak.</li>
            </ol>
            <h4>Selama 60 bulan</h4>
            <ol>
                <li>Pelanggaran kelima atas alasan sanksi 6 (enam) bulan, pelanggaran ketiga atas alasan sanksi 12 (dua belas) bulan, atau pelanggaran kedua atas alasan sanksi 24 (dua puluh empat) bulan.</li>
                <li>Calon Pemenang dan Peserta dengan penawaran harga terendah kedua, ketiga dan seterusnya melakukan penipuan atau pemalsuan informasi kualifikasi maupun pemalsuan dokumen kelengkapan penawaran.</li>
                <li>Mengundurkan diri pada saat akan ditetapkan sebagai pemenang atau tidak bersedia menandatangani kontrak dengan alasan yang tidak profesional.</li>
                <li>Penyedia yang keberatan atas proses pengadaan dan tidak mengajukan sanggahan secara tertulis tetapi menyebarkan ke publik dan ternyata informasi tersebut tidak benar atau mengada-ada.</li>
                <li>Penyedia melanggar Hak Kekayaan Intelektual.</li>
                <li>Mensubkontrakkan seluruh pekerjaan.</li>
            </ol>
            <p>Ketentuan Blacklist di atas tidak berlaku apabila kesalahan atau kelalaian Penyedia disebabkan oleh Perusahaan, atau bertentangan dengan keputusan pengadilan.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 9 : Contoh Surat Pernyataan Minat</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <h3>SURAT PERNYATAAN MINAT UNTUK MENGIKUTI PENGADAAN</h3>
            <p>Yang bertanda tangan di bawah ini:</p>
            <table>
                <tr><th style="width:28%">Nama</th><td class="fill">&nbsp;</td></tr>
                <tr><th>Jabatan</th><td class="fill">&nbsp;</td></tr>
                <tr><th>Mewakili</th><td class="fill">&nbsp;</td></tr>
                <tr><th>Alamat</th><td class="fill">&nbsp;</td></tr>
                <tr><th>Telepon / Fax</th><td class="fill">&nbsp;</td></tr>
                <tr><th>Email</th><td class="fill">&nbsp;</td></tr>
            </table>
            <p>Menyatakan dengan sebenarnya bahwa setelah mengetahui pengadaan yang akan dilaksanakan oleh PT PLN NP UP Kendari Tahun Anggaran {{tahun}}, maka dengan ini saya menyatakan berminat untuk mengikuti proses pengadaan {{nama_pengadaan}} sampai selesai.</p>
            <p>Demikian pernyataan ini dibuat dengan penuh kesadaran dan tanggung jawab.</p>
            <table class="signature">
                <tr><td class="role">Kendari, <span class="fill">..........................</span> {{tahun}}<br>Nama Penyedia Barang/Jasa</td></tr>
                <tr><td class="space">Meterai Rp 10.000,- &mdash; Tanda tangan dan cap perusahaan</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; Jabatan</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 10 : Contoh Surat Pernyataan Kesanggupan Memenuhi Persyaratan Teknis / TOR</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Kepada<br><b>PELAKSANA PENGADAAN / MANAJER PELAKSANA PENGADAAN</b><br>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Wua-Wua, Kendari</p>
            <p>Perihal : Surat Pernyataan Kesanggupan Memenuhi Persyaratan Teknis/TOR</p>
            <p>Yang bertanda tangan di bawah ini:<br>Nama : <span class="fill">..................................</span><br>Mewakili : PT <span class="fill">..................................</span><br>Jabatan : <span class="fill">..................................</span></p>
            <p>Sehubungan dengan pengadaan {{nama_pengadaan}} sesuai RKS tersebut di atas, dengan ini kami menyatakan bahwa kami sanggup memenuhi Persyaratan Teknis/Term of Reference (TOR).</p>
            <p>Demikian Surat Pernyataan ini dibuat dengan sebenarnya, untuk digunakan sebagaimana mestinya.</p>
            <table class="signature">
                <tr><td class="role">PT <span class="fill">..................................</span></td></tr>
                <tr><td class="space">Tanda tangan dan stempel perusahaan<br>(asli di atas meterai Rp 10.000,-)</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; Jabatan</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 11 : Contoh Surat Pernyataan Mematuhi Aturan K3L</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Kepada<br><b>PELAKSANA PENGADAAN / MANAJER PELAKSANA PENGADAAN BARANG/JASA PEMBANGKIT</b><br>PT PLN Nusantara Power UP Kendari<br>Jl. Chairil Anwar No. 2, Wua-Wua, Kendari</p>
            <p>Perihal : Surat Pernyataan</p>
            <p>Yang bertanda tangan di bawah ini:<br>Nama : <span class="fill">..................................</span><br>Mewakili : PT <span class="fill">..................................</span><br>Jabatan : <span class="fill">..................................</span></p>
            <p>Sehubungan dengan pengadaan {{nama_pengadaan}} sesuai RKS tersebut di atas, dengan ini kami menyatakan akan mematuhi segala aturan Keselamatan, Keamanan Kerja dan Lingkungan (K3L) yang ada di lingkungan PT PLN Nusantara Power UP Kendari, unit {{unit_tujuan}}.</p>
            <p>Demikian Surat Pernyataan ini dibuat dengan sebenarnya, untuk digunakan sebagaimana mestinya.</p>
            <table class="signature">
                <tr><td class="role">Kendari, <span class="fill">..........................</span> {{tahun}}<br>Nama Penyedia Barang/Jasa</td></tr>
                <tr><td class="space">Meterai Rp 10.000,- &mdash; Tanda tangan</td></tr>
                <tr><td class="name fill">( Nama Jelas ) &mdash; Jabatan</td></tr>
            </table>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 12 : Term of Reference (TOR)</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <p>Term of Reference (TOR) pekerjaan {{nama_pengadaan}} dilampirkan sebagai dokumen tersendiri dan merupakan bagian yang tidak terpisahkan dari RKS ini.</p>
        </section>

        <section class="lampiran">
            <table class="lampiran-head">
                <tr><td><b>Lampiran 13 : Cara Pemakaian Aplikasi VICON PLN NP</b></td>
                    <td style="text-align:right">RKS No. : <span class="fill">............ .RKS-PJ/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td></tr>
            </table>
            <ol>
                <li>Buka web browser, kemudian isikan alamat https://zoom.us.</li>
                <li>Pilih atau klik pada tombol &ldquo;Join a Meeting&rdquo;.</li>
                <li>Masukkan Meeting ID yang sudah dibagikan kemudian klik tombol &ldquo;Join&rdquo;.</li>
                <li>Masukkan password untuk meeting pada kolom Meeting Password dan nama Anda pada kolom Your Name, kemudian klik tombol &ldquo;Join&rdquo;.</li>
                <li>Setelah berhasil bergabung, klik pada ikon headphone di kiri bawah &ldquo;Join Audio&rdquo; kemudian klik tombol &ldquo;Join Audio by Computer&rdquo;.</li>
                <li>Dari pengaturan browser dapat diklik &ldquo;Allow&rdquo; saat diminta akses Microphone dan Camera.</li>
                <li>Untuk pengaturan audio (mic dan speaker), klik tombol panah ke atas di bagian kiri layar dan pilih microphone serta speaker yang ingin digunakan.</li>
            </ol>
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
