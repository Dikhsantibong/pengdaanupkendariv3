<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$doc = App\Models\ProcurementDocument::find(98);
$renderer = app(App\Services\DocumentPdfRenderer::class);

// Let's test with margin-bottom: 3px and font-size: 9.2pt to 10pt
for ($fs = 8.5; $fs <= 10.0; $fs += 0.1) {
    $fsStr = number_format($fs, 1);
    $html = <<<HTML
<style>@page { size: A4 portrait; margin: 15mm 15mm 18mm 15mm; }</style>
<section style="font-family: Arial, Helvetica, sans-serif; font-size: {$fsStr}pt; line-height: 1.2; color: #000;">
    <div style="text-align: center; margin-bottom: 6px;">
        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 34px; width: auto; display: inline-block;">
    </div>

    <div style="text-align: center; margin-bottom: 6px;">
        <div style="font-weight: bold; font-size: 10.5pt; text-decoration: underline; letter-spacing: 0.5px;">SYARAT UMUM</div>
        <div style="font-weight: bold; font-size: 9pt; margin-top: 2px;">Surat Pesanan (SP) Nomor {{nomor_pengadaan}} tanggal {{tanggal_dokumen}}</div>
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">A. UMUM</div>
        <ol style="margin: 0; padding-left: 18px; text-align: justify;">
            <li style="margin-bottom: 1px;">Pemberi Pekerjaan (PIHAK PERTAMA) adalah PT PLN NUSANTARA POWER UPDK KENDARI dan Pelaksana Pekerjaan (PIHAK KEDUA) adalah {{nama_mitra}}</li>
            <li style="margin-bottom: 1px;">Surat Pesanan (SP) ini berlaku sampai dengan batas waktu berakhir, kecuali ditentukan lain sesuai kesepakatan Pemberi Pekerjaan (PIHAK PERTAMA) dan Pelaksana Pekerjaan (PIHAK KEDUA).</li>
            <li style="margin-bottom: 1px;">Pekerjaan yang dilaksanakan/barang yang dijual oleh Pelaksana Pekerjaan (PIHAK KEDUA) adalah pekerjaan/barang yang sah menurut hukum serta bebas dari tuntutan pihak lain dan penyitaan dari yang berwajib.</li>
            <li style="margin-bottom: 1px;">Sebagai tanda persetujuan atas syarat pelaksanaan pekerjaan pada halaman ini, agar Pelaksana Pekerjaan (PIHAK KEDUA) menandatangani Surat Pesanan (SP) ini di atas materai Rp. 10.000,00 dan menyerahkan kembali kepada Pemberi Pekerjaan (PIHAK PERTAMA).</li>
        </ol>
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">B. JAMINAN DAN KEWAJIBAN PELAKSANAAN PEKERJAAN (PIHAK KEDUA)</div>
        <ol style="margin: 0; padding-left: 18px; text-align: justify;">
            <li style="margin-bottom: 1px;">Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa barang yang dijual sesuai dengan spesifikasi yang ditetapkan, dalam kondisi baru, belum pernah dipakai, bisa berfungsi dengan baik, bebas dari cacat yang terlihat atau tersembunyi serta bebas dari pelanggaran atas kekayaan intelektual.</li>
            <li style="margin-bottom: 1px;">Apabila barang tidak sesuai dengan spesifikasi yang ditetapkan, maka Pemberi Pekerjaan (PIHAK PERTAMA) berhak meminta penggantian.</li>
            <li style="margin-bottom: 1px;">Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa barang yang dijual bebas dari segala macam kerusakan sampai diterbitkannya Berita Acara Penerimaan Material yang dilengkapi Foto Dokumentasi.</li>
            <li style="margin-bottom: 1px;">Pelaksana Pekerjaan (PIHAK KEDUA) menjamin bahwa barang yang dijual bebas dari segala macam kerusakan selama masa garansi, kecuali:
                <ol type="a" style="margin: 1px 0; padding-left: 16px;">
                    <li style="margin-bottom: 1px;">Rusak atau cacat yang diakibatkan dari modifikasi oleh PIHAK PERTAMA terhadap hasil Pekerjaan sebagaimana dimaksud pada Surat Pesanan ini tanpa persetujuan terlebih dahulu dari PIHAK KEDUA;</li>
                    <li style="margin-bottom: 1px;">Rusak atau cacat yang diakibatkan dari pemasangan/perawatan/pengoperasian dan service yang dilakukan tidak menurut pedoman pengoperasian dan/atau buku-buku instruksi yang relevan dari PIHAK KEDUA/pabrikan;</li>
                    <li style="margin-bottom: 1px;">Rusak atau cacat yang timbul oleh sebab-sebab yang diakibatkan oleh PIHAK PERTAMA dan/atau pihak ketiga;</li>
                    <li style="margin-bottom: 1px;">Rusak atau cacat yang disebabkan oleh Force Majeure.</li>
                </ol>
            </li>
            <li style="margin-bottom: 1px;">Kerusakan barang selama masa garansi menjadi tanggung - jawab Pelaksana Pekerjaan (PIHAK KEDUA).</li>
            <li style="margin-bottom: 1px;">Pada waktu penyerahan barang pelaksanaan pekerjaan (PIHAK KEDUA) melengkapi dokumen material safety data sheet (MSDS) Atau COA.</li>
        </ol>
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">C. PEMERIKSAAN DAN SERAH TERIMA BARANG</div>
        <ol style="margin: 0; padding-left: 18px; text-align: justify;">
            <li style="margin-bottom: 1px;">Setelah pekerjaan selesai dilaksanakan foto barang dan surat jalan yang akan digunakan sebagai dasar bagi Pelaksana Pekerjaan (PIHAK KEDUA) untuk melakukan penagihan kepada Pemberi Pekerjaan (PIHAK PERTAMA).</li>
            <li style="margin-bottom: 1px;">Pekerjaan dinyatakan selesai setelah serah terima pekerjaan yang dibuktikan dengan foto pekerjaan dan ditandatanganinya Berita Acara Serah Terima Pekerjaan kedua belah pihak yang diterbitkan oleh Pelaksana Pekerjaan (PIHAK KEDUA).</li>
        </ol>
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">D. PEMBAYARAN</div>
        <ol style="margin: 0; padding-left: 18px; text-align: justify;">
            <li style="margin-bottom: 1px;">Penagihan dapat dilakukan Pelaksana Pekerjaan (PIHAK KEDUA) dengan mengirimkan dokumen penagihan sebagai berikut :
                <ol type="a" style="margin: 1px 0; padding-left: 16px; list-style-type: lower-alpha;">
                    <li style="margin-bottom: 1px;">Surat Permohonan Pembayaran ditujukan kepada Manager UP Kendari;</li>
                    <li style="margin-bottom: 1px;">Kuitansi tagihan, 3 (tiga) rangkap (1 rangkap bermaterai)</li>
                    <li style="margin-bottom: 1px;">Invoice, 3 (tiga) rangkap</li>
                    <li style="margin-bottom: 1px;">Faktur Pajak (e-Faktur), 1 (satu) rangkap disertakan setelah ada konfirmasi dari bag pengadaan;</li>
                    <li style="margin-bottom: 1px;">Berita Acara Serah Terima Pekerjaan</li>
                    <li style="margin-bottom: 1px;">BA Keterlambatan (jika ada);</li>
                    <li style="margin-bottom: 1px;">Copy Surat Pesanan (SP);</li>
                    <li style="margin-bottom: 1px;">Copy NPWP dan PKP, 1 (satu) rangkap;</li>
                    <li style="margin-bottom: 1px;">Copy Surat Jalan</li>
                </ol>
            </li>
            <li style="margin-bottom: 1px;">Pembayaran dari PIHAK PERTAMA kepada PIHAK KEDUA dilakukan dengan pemindahbukuan/transfer setiap hari kerja melalui :
                <div style="padding-left: 12px; margin-top: 1px;">
                    <span style="display: inline-block; width: 130px;">a. Bank</span>: {{nama_bank}}<br>
                    <span style="display: inline-block; width: 130px;">b. Nomor Rekening</span>: {{nomor_rekening}}<br>
                    <span style="display: inline-block; width: 130px;">c. Atas Nama</span>: {{nama_pemilik_rekening}}
                </div>
            </li>
            <li style="margin-bottom: 1px;">Apabila terjadi keterlambatan penyerahan barang sesuai waktu yang telah ditentukan dalam Surat Pesanan ini maka PIHAK KEDUA dikenakan denda keterlambatan sebesar 1 ‰ (satu per mil) per hari kalender dari nilai item barang yang terlambat dengan batas maksimum denda keterlambatan sebesar 5% (lima persen) dari nilai item barang yang terlambat.</li>
            <li style="margin-bottom: 1px;">Penyerahan dokumen pembayaran dari PIHAK KEDUA kepada PIHAK PERTAMA paling lambat tanggal 15 (lima belas) setiap bulan berjalan dengan batas waktu tanggal invoice maksimal 3 (tiga) bulan terhitung sejak tanggal penerbitan Berita Acara Penyelesaian Pekerjaan / Bon Penerimaan Barang.</li>
            <li style="margin-bottom: 1px;">Apabila PIHAK KEDUA terlambat menyerahkan dokumen pembayaran mengakibatkan PPN tidak bisa dikreditkan oleh PIHAK PERTAMA maka PIHAK KEDUA akan dikenakan sanksi denda keterlambatan sesuai dengan point 3 Surat Pesanan ini.</li>
        </ol>
    </div>

    <div style="page-break-before: always; text-align: center; margin-bottom: 6px; margin-top: 0;">
        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 34px; width: auto; display: inline-block;">
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">E. PEMUTUSAN ATAU PEMBATALAN SURAT PESANAN (SP)</div>
        <ol style="margin: 0; padding-left: 18px; text-align: justify;">
            <li style="margin-bottom: 1px;">Pemberi Pekerjaan (PIHAK PERTAMA) dapat memutuskan atau membatalkan Surat Pesanan (SP) ini secara sepihak tanpa kewajiban apapun terhadap Pelaksana Pekerjaan (PIHAK KEDUA) apabila Pelaksana Pekerjaan (PIHAK KEDUA) terbukti melanggar ketentuan dalam Surat Pesanan (SP).</li>
            <li style="margin-bottom: 1px;">Dalam hal terjadi pemutusan atau pembatalan Surat Pesanan (SP) sebagaimana dimaksud dalam huruf E angka 1 di atas, Pelaksana Pekerjaan (PIHAK KEDUA) akan dikenakan sanksi tidak diperbolehkan untuk mengikuti pengadaan barang/jasa di wilayah kerja Pemberi Pekerjaan (PIHAK PERTAMA) selama minimal 1 (satu) tahun terhitung sejak tanggal pemutusan atau pembatalan Surat Pesanan (SP). Pelaksanaan Pemutusan Surat Pesanan (SP) akan dilakukan secara tertulis dari Pemberi Pekerjaan (PIHAK PERTAMA) kepada Pelaksana Pekerjaan (PIHAK KEDUA).</li>
            <li style="margin-bottom: 1px;">Dalam hal terjadi pemutusan atau pembatalan Surat Pesanan (SP) secara sepihak, maka Pemberi Pekerjaan (PIHAK PERTAMA) dan Pelaksana Pekerjaan (PIHAK KEDUA) sepakat untuk tidak memberlakukan ketentuan-ketentuan Pasal 1266 dan 1267 Kitab Undang-Undang Hukum Perdata.</li>
        </ol>
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">F. PENYELESAIAN PERSELISIHAN</div>
        <ol style="margin: 0; padding-left: 18px; text-align: justify;">
            <li style="margin-bottom: 1px;">Apabila terjadi perselisihan pendapat dalam rangka pelaksanaan Surat Perintah Kerja ini maka, PARA PIHAK sepakat untuk menyelesaikan dengan musyawarah.</li>
            <li style="margin-bottom: 1px;">Segala sengketa, pertentangan atau perselisihan yang terjadi dari atau sehubungan dengan Surat Perintah Kerja ini, atau pelanggarannya yang tidak dapat diselesaikan dengan cara negosiasi, mediasi atau konsolidasi, akan diselesaikan secara Arbitrase melalui Badan Arbitrase Nasional Indonesia (BANI) di Surabaya.</li>
        </ol>
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">G. ASURANSI</div>
        <div style="text-align: justify;">Asuransi untuk barang/jasa menjadi tanggungan Pelaksana Pekerjaan (PIHAK KEDUA) dengan ketentuan yang berlaku di Pelaksana Pekerjaan.</div>
    </div>

    <div style="margin-bottom: 4px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">H. LAIN-LAIN</div>
        <div style="text-align: justify;">Hal-hal yang belum diatur dalam Surat Pesanan (SP) ini akan diatur tersendiri.</div>
    </div>
</section>
HTML;

    $doc->rendered_body = $html;
    $pdf = $renderer->render($doc);
    preg_match_all('/\/Type\s*\/Page\b/', $pdf, $m);
    if (count($m[0]) === 2) {
        echo "MATCH: FS {$fsStr}pt => 2 PAGES!\n";
    }
}
