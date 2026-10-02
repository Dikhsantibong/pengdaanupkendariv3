<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the interim standard templates. These are placeholders for the official
 * UP Kendari templates and can be replaced by an administrator without any code
 * change - only the template body and its placeholders are stored here.
 */
class DocumentTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed one active standard template per document type.
     */
    public function run(): void
    {
        foreach ($this->templates() as $code => $body) {
            $documentType = DocumentType::query()->where('code', $code)->first();

            if ($documentType === null) {
                continue;
            }

            // The general fallback: no procurement method attached.
            DocumentTemplate::query()->updateOrCreate(
                [
                    'document_type_id' => $documentType->id,
                    'procurement_method_id' => null,
                    'version' => 1,
                ],
                [
                    'name' => "Template Standar {$documentType->name}",
                    'body' => $body,
                    'placeholders' => $this->placeholdersIn($body),
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * The standard template body for each document type code.
     *
     * @return array<string, string>
     */
    protected function templates(): array
    {
        $identity = <<<'HTML'
        <table>
            <tr><th style="width:32%">Nomor Pengadaan</th><td>{{nomor_pengadaan}}</td></tr>
            <tr><th>Nama Pekerjaan</th><td>{{nama_pengadaan}}</td></tr>
            <tr><th>Direksi Pekerjaan</th><td>{{direksi_pekerjaan}}</td></tr>
            <tr><th>Unit Tujuan</th><td>{{unit_tujuan}}</td></tr>
            <tr><th>Metode Pengadaan</th><td>{{metode_pengadaan}}</td></tr>
            <tr><th>Sumber Anggaran</th><td>{{sumber_anggaran}}</td></tr>
            <tr><th>Nomor PR/RO</th><td>{{nomor_pr_ro}}</td></tr>
            <tr><th>Nomor PRK</th><td>{{nomor_prk}}</td></tr>
            <tr><th>Nilai HPE / Anggaran</th><td>{{nilai_hpe}}</td></tr>
            <tr><th>Status Progres</th><td>{{status_progres}}</td></tr>
        </table>
        HTML;

        $signature = <<<'HTML'
        <table class="signature">
            <tr>
                <td>Diperiksa oleh,<br>Direksi Pekerjaan<br><br><br><br><b>{{direksi_pekerjaan}}</b></td>
                <td>Kendari, {{tanggal_dokumen}}<br>Disusun oleh,<br><br><br><br><b>{{pic_perencana}}</b></td>
            </tr>
        </table>
        HTML;

        $kop = <<<'HTML'
        <table class="lampiran-head" style="width: 100%; margin-bottom: 16px; border: none; border-collapse: collapse;">
            <tr>
                <td style="vertical-align: middle; border: none; padding: 0;">
                    <img src="/logo/sidebar-logo.png" alt="PT PLN Nusantara Power" style="height: 38px; width: auto; margin-bottom: 4px;"><br>
                    <b>PT PLN NUSANTARA POWER<br>UP KENDARI</b>
                </td>
                <td style="text-align: right; vertical-align: middle; border: none; padding: 0;">
                    Nomor: {{nomor_pengadaan}}<br>Tanggal: {{tanggal_dokumen}}
                </td>
            </tr>
        </table>
        HTML;

        return [
            'nota-dinas-usulan' => <<<HTML
            {$kop}
            <h1>Nota Dinas Usulan Pengadaan</h1>
            <p>Nomor: {{nomor_prk}}</p>
            <h2>A. Identitas Pengadaan</h2>
            {$identity}
            <h2>B. Latar Belakang</h2>
            <p>Sehubungan dengan kebutuhan operasional pada {{unit_tujuan}}, bersama ini diusulkan pelaksanaan
            pekerjaan <b>{{nama_pengadaan}}</b> dengan nilai anggaran sebesar {{nilai_hpe}}.</p>
            <h2>C. Kelengkapan Dokumen Perencanaan</h2>
            {{checklist_perencanaan}}
            {$signature}
            HTML,

            'tor' => $this->torTemplate(),

            'rab' => <<<HTML
            {$kop}
            <h1>Rencana Anggaran Biaya (RAB)</h1>
            <h2>A. Identitas Pekerjaan</h2>
            {$identity}
            <h2>B. Rekapitulasi Anggaran</h2>
            <table>
                <tr><th>No</th><th>Uraian</th><th>Jumlah</th></tr>
                <tr><td>1</td><td>{{nama_pengadaan}}</td><td>{{nilai_hpe}}</td></tr>
                <tr><th colspan="2">Total Anggaran</th><th>{{nilai_hpe}}</th></tr>
            </table>
            {$signature}
            HTML,

            'hpe' => <<<HTML
            {$kop}
            <h1>Harga Perkiraan Engineer (HPE)</h1>
            <h2>A. Identitas Pekerjaan</h2>
            {$identity}
            <h2>B. Nilai Harga Perkiraan Engineer</h2>
            <p>Nilai HPE untuk pekerjaan {{nama_pengadaan}} ditetapkan sebesar <b>{{nilai_hpe}}</b>
            ({{nilai_hpe_terbilang}}) sudah termasuk pajak yang berlaku.</p>
            {$signature}
            HTML,

            'upb' => <<<HTML
            {$kop}
            <h1>Usulan Pengadaan Barang/Jasa (UPB)</h1>
            <h2>A. Identitas Pengadaan</h2>
            {$identity}
            <h2>B. Kelengkapan Dokumen</h2>
            {{checklist_perencanaan}}
            {$signature}
            HTML,

            'rks' => <<<HTML
            {$kop}
            <h1>Rencana Kerja dan Syarat-Syarat (RKS)</h1>
            <h2>A. Identitas Pekerjaan</h2>
            {$identity}
            <h2>B. Syarat Umum</h2>
            <p>Penyedia wajib memenuhi seluruh ketentuan administrasi, teknis, dan K3 yang berlaku di lingkungan
            PLN Nusantara Power UP Kendari.</p>
            <h2>C. Syarat Teknis</h2>
            <p>Spesifikasi teknis pekerjaan {{nama_pengadaan}} mengacu pada TOR yang diterbitkan oleh
            {{direksi_pekerjaan}}.</p>
            <h2>D. Syarat Administrasi</h2>
            {{checklist_perencanaan}}
            {$signature}
            HTML,

            'berita-acara' => <<<HTML
            {$kop}
            <h1>Berita Acara Pengadaan</h1>
            <p>Pada hari ini, {{tanggal_dokumen}}, telah dilaksanakan proses pengadaan sebagai berikut:</p>
            {$identity}
            <h2>Progres Pelaksanaan</h2>
            {{checklist_pelaksanaan}}
            <table class="signature">
                <tr>
                    <td>Mengetahui,<br>Team Leader Pengadaan<br><br><br><br><b>..........................</b></td>
                    <td>Kendari, {{tanggal_dokumen}}<br>PIC Pelaksana<br><br><br><br><b>{{pic_pelaksana}}</b></td>
                </tr>
            </table>
            HTML,

            'kontrak' => <<<HTML
            {$kop}
            <h1>Perjanjian Kontrak Pekerjaan</h1>
            <p>Nomor: {{nomor_pengadaan}}</p>
            <p>Perjanjian ini dibuat pada {{tanggal_dokumen}} antara PT PLN Nusantara Power UP Kendari
            dengan penyedia terpilih untuk pelaksanaan pekerjaan berikut:</p>
            {$identity}
            <h2>Pasal 1 - Ruang Lingkup</h2>
            <p>Penyedia melaksanakan pekerjaan {{nama_pengadaan}} pada {{unit_tujuan}} sesuai RKS dan TOR.</p>
            <h2>Pasal 2 - Nilai Kontrak</h2>
            <p>Nilai kontrak ditetapkan berdasarkan hasil negosiasi dengan pagu {{nilai_hpe}}.</p>
            <h2>Pasal 3 - Kelengkapan Pelaksanaan</h2>
            {{checklist_pelaksanaan}}
            <table class="signature">
                <tr>
                    <td>Pihak Pertama<br>PLN Nusantara Power UP Kendari<br><br><br><br><b>..........................</b></td>
                    <td>Pihak Kedua<br>Penyedia Barang/Jasa<br><br><br><br><b>..........................</b></td>
                </tr>
            </table>
            HTML,
        ];
    }

    /**
     * Term of Reference (TOR) standard template with cover and official UP Kendari layout.
     */
    protected function torTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Term of Reference (TOR)</title>
<style>
    @page { size: A4; margin: 15mm 15mm 15mm 15mm; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        color: #000;
        background: #fff;
        font-family: "Times New Roman", Times, serif;
        font-size: 11pt;
        line-height: 1.35;
    }

    /* Cover Page */
    .cover-page {
        position: relative;
        width: 100%;
        box-sizing: border-box;
        page-break-after: always;
    }
    .cover-container {
        position: relative;
        width: 514px;
        margin: 15px auto 0;
    }
    .ribbon-tab {
        position: absolute;
        top: -18px;
        right: 32px;
        width: 48px;
        height: 82px;
        background: #3572b8;
        color: #ffffff;
        text-align: center;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: bold;
        font-size: 10.5pt;
        padding-top: 55px;
        z-index: 10;
        border-radius: 0 0 2px 2px;
    }
    .cover-card {
        background: #3d4f61;
        border-radius: 4px 4px 0 0;
        padding: 30px 24px 10px;
        text-align: center;
    }
    .cover-photo-box {
        width: 380px;
        margin: 0 auto;
        text-align: center;
    }
    .cover-photo {
        width: 380px;
        height: 285px;
        border: 2px solid #ffffff;
        display: block;
        margin: 0 auto;
    }
    .cover-tor-label {
        color: #ffffff;
        font-size: 12pt;
        font-weight: bold;
        text-align: left;
        width: 380px;
        margin: 22px auto 16px;
        letter-spacing: 0.5px;
        font-family: "Times New Roman", Times, serif;
    }
    .cover-title {
        color: #ffffff;
        font-size: 15pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: center;
        width: 440px;
        margin: 0 auto 30px;
        line-height: 1.4;
        letter-spacing: 0.5px;
        font-family: "Times New Roman", Times, serif;
    }
    .cover-curve {
        display: block;
        width: 514px;
        height: auto;
        margin: -1px auto 0;
    }
    .cover-meta {
        margin: 32px 0 0 45px;
        border-collapse: collapse;
        font-size: 10.5pt;
        font-weight: bold;
        font-family: "Times New Roman", Times, serif;
    }
    .cover-meta td {
        border: none !important;
        padding: 2px 4px;
        vertical-align: top;
    }
    .cover-meta .label {
        width: 125px;
    }
    .cover-meta .colon {
        width: 15px;
        text-align: center;
    }
    .cover-meta .val-box {
        background: #d8dde3;
        border: 1px solid #b8c1cb;
        padding: 1px 6px;
        display: inline-block;
    }
    .cover-footer {
        position: absolute;
        bottom: 20px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 8pt;
        color: #555555;
        font-family: Arial, Helvetica, sans-serif;
    }

    /* Document Body */
    .document { width: 100%; }
    .header {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9pt;
    }
    .header td { border: 1px solid #000; vertical-align: middle; }
    .header-left {
        width: 22%;
        text-align: center;
        padding: 6px;
        font-weight: bold;
    }
    .header-left img {
        max-height: 48px;
        max-width: 140px;
        display: block;
        margin: 0 auto;
    }
    .header-middle {
        width: 37%;
        text-align: center;
        font-weight: bold;
        padding: 7px;
    }
    .header-middle .tor { font-size: 12pt; margin-top: 5px; }
    .header-right { width: 41%; padding: 0; }
    .header-right table { width: 100%; border-collapse: collapse; }
    .header-right td { border: 0; border-bottom: 1px solid #000; padding: 4px 6px; }
    .header-right tr:last-child td { border-bottom: 0; }
    .header-right .label { width: 45%; }
    .header-right .colon { width: 5%; text-align: center; }

    .program {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        font-weight: bold;
    }
    .program td { border: none !important; padding: 1px 0; vertical-align: top; }
    .program .label { width: 17%; }
    .program .colon { width: 2%; text-align: center; }
    .program .value { width: 81%; }

    .section { margin-bottom: 11px; page-break-inside: auto; }
    .title {
        font-weight: bold;
        font-size: 12pt;
        margin: 0 0 5px 0;
    }
    .sub {
        font-weight: bold;
        margin: 0 0 4px 28px;
    }
    .para {
        margin: 0 0 5px 28px;
        text-align: justify;
    }
    .list {
        margin: 0 0 5px 46px;
        padding: 0;
        list-style: none;
    }
    .list div { margin: 0 0 3px 0; text-align: justify; }

    table.data {
        width: calc(100% - 28px);
        margin-left: 28px;
        border-collapse: collapse;
        margin-bottom: 7px;
        font-size: 10pt;
    }
    table.data th, table.data td {
        border: 1px solid #000;
        padding: 4px 5px;
        vertical-align: top;
    }
    table.data th { text-align: center; font-weight: bold; }
    .center { text-align: center; }

    .signature {
        width: 100%;
        border-collapse: collapse;
        margin-top: 24px;
        page-break-inside: avoid;
    }
    .signature td {
        width: 33.333%;
        border: none !important;
        text-align: center;
        vertical-align: top;
        padding: 4px 8px;
    }
    .signature .gap { height: 68px; }
    .signature .name { font-weight: bold; text-decoration: underline; }
    .approval { page-break-before: always; }
    .approval-title { text-align: center; font-weight: bold; font-size: 12pt; line-height: 1.5; margin-top: 5px; }
    .approval-date { text-align: center; margin: 12px 0 22px; }
    .approval-table { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
    .approval-table td { border: none !important; text-align: center; vertical-align: top; padding: 4px 10px; }
    .approval-table .gap { height: 72px; }
    .approval-table .small-gap { height: 46px; }
    .approval-table .name { font-weight: bold; text-decoration: underline; }
    .approval-table.two td { width: 50%; }
</style>
</head>
<body>

<!-- Cover Page -->
<section class="cover-page">
    <div class="cover-container">
        <div class="ribbon-tab" contenteditable="false"><span contenteditable="true">{{tahun}}</span></div>

        <div class="cover-card">
            <div class="cover-photo-box">
                <img src="/image/bg-TOR.jpeg" class="cover-photo" alt="Kantor PLN NP UP Kendari">
            </div>

            <div class="cover-tor-label">TERM OF REFERENCE (TOR)</div>

            <div class="cover-title">
                {{nama_pengadaan}}
            </div>
        </div>

        <img src="/image/tor-cover-wave.png" class="cover-curve" alt="">
    </div>

    <table class="cover-meta">
        <tr><td class="label">NOMOR SKK</td><td class="colon">:</td><td></td></tr>
        <tr><td class="label">NOMOR PRK</td><td class="colon">:</td><td><span class="val-box">{{nomor_prk}}</span></td></tr>
        <tr><td class="label">NO PR/IR</td><td class="colon">:</td><td>{{nomor_pr_ro}}</td></tr>
        <tr><td class="label">TANGGAL</td><td class="colon">:</td><td>{{tanggal_dokumen}}</td></tr>
    </table>

    <div class="cover-footer">
        PT PLN NUSANTARA POWER UPDK KENDARI | Jl. Chairil Anwar No. 2 Mataiwoi, Wuawua, Kota Kendari
    </div>
</section>

<div class="page-break" contenteditable="false"></div>

<!-- Document Content -->
<div class="document">

    <!-- Kop dan metadata, mengikuti pola PDF -->
    <table class="header">
        <tr>
            <td class="header-left">
                <img src="/logo/sidebar-logo.png" alt="PT PLN NUSANTARA POWER">
            </td>
            <td class="header-middle">
                INTEGRATED MANAGEMENT SYSTEM
                <div class="tor">TERM OF REFERENCE (TOR)</div>
            </td>
            <td class="header-right">
                <table>
                    <tr><td class="label">Nomor Dokumen / Pengadaan</td><td class="colon">:</td><td>{{nomor_pengadaan}}</td></tr>
                    <tr><td class="label">Revisi</td><td class="colon">:</td><td>00</td></tr>
                    <tr><td class="label">Tanggal Terbit</td><td class="colon">:</td><td>{{tanggal_dokumen}}</td></tr>
                    <tr><td class="label">Halaman</td><td class="colon">:</td><td>1 dari {{status_progres}}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Identitas program -->
    <table class="program">
        <tr><td class="label">Nama Program</td><td class="colon">:</td><td class="value">{{nama_pengadaan}}</td></tr>
        <tr><td class="label">No. PRK</td><td class="colon">:</td><td class="value">{{nomor_prk}}</td></tr>
        <tr><td class="label">No. PR/RO</td><td class="colon">:</td><td class="value">{{nomor_pr_ro}}</td></tr>
        <tr><td class="label">Tanggal</td><td class="colon">:</td><td class="value">{{tanggal_dokumen}}</td></tr>
        <tr><td class="label">Jenis / Sumber Anggaran</td><td class="colon">:</td><td class="value">{{sumber_anggaran}}</td></tr>
    </table>

    <section class="section">
        <div class="title">1. PENDAHULUAN</div>
        <div class="sub">1.1. Latar Belakang</div>
        <p class="para">Pekerjaan <b>{{nama_pengadaan}}</b> dilaksanakan sebagai bagian dari kebutuhan pekerjaan pada <b>{{unit_tujuan}}</b>. Pelaksanaan pekerjaan disusun untuk mendukung kebutuhan operasional, peningkatan keandalan, efektivitas proses, dan pencapaian sasaran pekerjaan sesuai ketentuan yang berlaku.</p>
        <p class="para">Ruang lingkup, metode, serta target hasil pekerjaan disesuaikan dengan kebutuhan pengguna barang/jasa dan arahan dari <b>{{direksi_pekerjaan}}</b>.</p>

        <div class="sub">1.2. Maksud dan tujuan</div>
        <div class="sub" style="font-weight:normal;">1.2.1. Maksud</div>
        <div class="list">
            <div>a. Menjadi acuan dalam pelaksanaan pengadaan pekerjaan <b>{{nama_pengadaan}}</b>.</div>
            <div>b. Menjadi dasar bagi penyedia jasa dalam menyiapkan dan melaksanakan pekerjaan sesuai kebutuhan pengguna.</div>
        </div>
        <div class="sub" style="font-weight:normal;">1.2.2. Tujuan</div>
        <div class="list">
            <div>a. Memenuhi kebutuhan pekerjaan pada <b>{{unit_tujuan}}</b>.</div>
            <div>b. Menjamin pelaksanaan pekerjaan sesuai ruang lingkup dan ketentuan yang ditetapkan.</div>
            <div>c. Mendukung pencapaian target pekerjaan secara efektif dan terdokumentasi.</div>
            <div>d. Menjamin koordinasi pelaksanaan oleh penyedia dengan <b>{{direksi_pekerjaan}}</b>.</div>
        </div>

        <div class="sub">1.3. Lokasi</div>
        <p class="para">Pelaksanaan pekerjaan dilakukan pada <b>{{unit_tujuan}}</b> dan/atau lokasi lain yang ditetapkan dalam dokumen pengadaan.</p>

        <div class="sub">1.4. Nama pengguna barang/jasa</div>
        <p class="para"><b>{{unit_tujuan}}</b>.</p>
    </section>

    <section class="section">
        <div class="title">2. PEDOMAN ACUAN TEKNIS/DATA REFERENSI TEKNIS</div>
        <div class="sub">2.1. Standart Nasional &amp; Internasional Terkait</div>
        <p class="para">Pelaksanaan pekerjaan mengacu pada standar, prosedur, ketentuan teknis, spesifikasi, gambar, dokumen pengadaan, serta peraturan internal yang relevan dengan <b>{{nama_pengadaan}}</b>.</p>
        <div class="list">
            <div>• Standar dan ketentuan teknis yang berlaku untuk jenis pekerjaan.</div>
            <div>• Spesifikasi teknis dan dokumen pengadaan yang disetujui.</div>
            <div>• Ketentuan keselamatan, keamanan, mutu, dan lingkungan yang berlaku.</div>
        </div>
    </section>

    <section class="section">
        <div class="title">3. LINGKUP PEKERJAAN/SCOPE OF WORK</div>
        <p class="para">Adapun lingkup pekerjaan <b>{{nama_pengadaan}}</b> meliputi pekerjaan, jasa, material, aktivitas pendukung, pengujian, dokumentasi, dan kegiatan lain yang diperlukan untuk mencapai hasil pekerjaan yang dipersyaratkan.</p>
        <p class="para">Metode pelaksanaan pengadaan menggunakan <b>{{metode_pengadaan}}</b> dengan memperhatikan ketentuan dokumen pengadaan dan arahan dari <b>{{direksi_pekerjaan}}</b>.</p>

        <table class="data">
            <tr><th style="width:9%">NO</th><th style="width:51%">JASA / PEKERJAAN</th><th style="width:18%">SATUAN</th><th style="width:22%">JUMLAH</th></tr>
            <tr><td class="center">1</td><td>{{nama_pengadaan}}</td><td class="center">Sesuai kebutuhan</td><td class="center">Sesuai dokumen pengadaan</td></tr>
        </table>
    </section>

    <section class="section">
        <div class="title">4. PERFORMANCE DESIGN</div>
        <div class="list">
            <div>4.1. Pekerjaan harus dapat memenuhi kebutuhan dan sasaran <b>{{nama_pengadaan}}</b>.</div>
            <div>4.2. Hasil pekerjaan harus dapat digunakan/dioperasikan sesuai fungsi yang dipersyaratkan.</div>
            <div>4.3. Dokumentasi hasil pekerjaan harus tersedia dan dapat ditelusuri.</div>
            <div>4.4. Pelaksanaan pekerjaan harus mendukung kebutuhan operasional <b>{{unit_tujuan}}</b>.</div>
        </div>
    </section>

    <section class="section">
        <div class="title">5. KUALIFIKASI CALON PELAKSANA PEKERJAAN</div>
        <div class="list">
            <div>5.1. Penyedia merupakan badan usaha/perorangan yang memiliki legalitas dan bidang usaha yang sesuai dengan <b>{{nama_pengadaan}}</b>.</div>
            <div>5.2. Memiliki pengalaman yang relevan dengan jenis pekerjaan yang akan dilaksanakan.</div>
            <div>5.3. Memiliki sumber daya manusia yang kompeten sesuai kebutuhan pekerjaan.</div>
            <div>5.4. Memiliki kemampuan menyediakan peralatan, material, dan sumber daya pendukung sesuai kebutuhan.</div>
            <div>5.5. Mampu melaksanakan pekerjaan sesuai instruksi dan koordinasi <b>{{direksi_pekerjaan}}</b>.</div>
        </div>
    </section>

    <section class="section">
        <div class="title">6. DETAIL URAIAN PEKERJAAN</div>
        <p class="para">Detail uraian pekerjaan untuk <b>{{nama_pengadaan}}</b> ditetapkan berdasarkan kebutuhan teknis dan dokumen pengadaan.</p>
        <table class="data">
            <tr><th style="width:8%">NO.</th><th style="width:31%">PEKERJAAN</th><th style="width:13%">STN</th><th style="width:12%">VOL.</th><th style="width:36%">KETERANGAN</th></tr>
            <tr><td class="center">1</td><td>{{nama_pengadaan}}</td><td class="center">Sesuai kebutuhan</td><td class="center">Sesuai dokumen</td><td>Pelaksanaan sesuai spesifikasi teknis dan arahan <b>{{direksi_pekerjaan}}</b>.</td></tr>
            <tr><td class="center">2</td><td>Pekerjaan pendukung</td><td class="center">Sesuai kebutuhan</td><td class="center">Sesuai dokumen</td><td>Termasuk pekerjaan penunjang yang diperlukan sampai pekerjaan dinyatakan selesai.</td></tr>
            <tr><td class="center">3</td><td>Dokumentasi / pelaporan</td><td class="center">Lot</td><td class="center">1</td><td>Dokumen hasil pekerjaan disampaikan sesuai ketentuan.</td></tr>
        </table>
    </section>

    <section class="section">
        <div class="title">7. KELENGKAPAN PELAKSANAAN PEKERJAAN</div>
        <p class="para">Penyedia wajib menyiapkan seluruh tenaga kerja, peralatan, material, dokumen, metode kerja, serta kebutuhan lain yang diperlukan untuk melaksanakan <b>{{nama_pengadaan}}</b> secara lengkap, aman, dan sesuai ketentuan.</p>
    </section>

    <div class="page-break" contenteditable="false"></div>

    <section class="section">
        <div class="title">8. ASPEK K3 DAN KEAMANAN</div>
        <div class="sub">8.1. Identifikasi Bahaya dan Risiko Kerja</div>
        <div class="sub" style="font-weight:normal;">8.1.1. Potensi bahaya yang termasuk dalam pekerjaan ini adalah :</div>
        <table class="data">
            <tr><th style="width:8%">No.</th><th style="width:28%">Identifikasi Risiko</th><th style="width:23%">Risiko / Penyebab</th><th style="width:20%">Dampak</th><th style="width:21%">Pengendalian</th></tr>
            <tr><td class="center">1</td><td>Risiko pelaksanaan pekerjaan</td><td>Potensi bahaya sesuai aktivitas pekerjaan</td><td>Gangguan terhadap pekerja/operasional</td><td>Mengikuti prosedur K3 dan pengendalian risiko yang berlaku.</td></tr>
        </table>
        <div class="sub" style="font-weight:normal;">8.1.2. Dalam pekerjaan ini penyedia wajib :</div>
        <div class="list">
            <div>• Melakukan identifikasi, evaluasi, dan pengendalian risiko.</div>
            <div>• Mematuhi ketentuan K3 dan keamanan di lokasi pekerjaan.</div>
            <div>• Menjaga keamanan data, dokumen, material, dan fasilitas pengguna.</div>
        </div>
        <table class="data">
            <tr><th style="width:8%">No</th><th style="width:42%">Item Barang/Peralatan</th><th style="width:25%">Pelaksana Pekerjaan</th><th style="width:25%">Pengguna Barang/Jasa</th></tr>
            <tr><td class="center">1</td><td>Peralatan kerja sesuai kebutuhan</td><td class="center">Sesuai kebutuhan</td><td class="center">Sesuai ketentuan pengadaan</td></tr>
            <tr><td class="center">2</td><td>Perlengkapan keselamatan kerja</td><td class="center">Sesuai kebutuhan</td><td class="center">Sesuai ketentuan pengadaan</td></tr>
        </table>
        <div class="sub" style="font-weight:normal;">8.1.3. Semua potensi bahaya yang telah diidentifikasi harus dievaluasi dan dikendalikan oleh pelaksana pekerjaan.</div>
    </section>

    <section class="section">
        <div class="title">9. LAPORAN HASIL PEKERJAAN/DOKUMEN KELENGKAPAN PEKERJAAN</div>
        <div class="sub">9.1. Laporan hasil pekerjaan dengan ketentuan sebagaimana berikut:</div>
        <div class="list">
            <div>- Laporan pelaksanaan pekerjaan <b>{{nama_pengadaan}}</b>.</div>
            <div>- Dokumen hasil pekerjaan dan hasil pengujian/pengecekan sesuai kebutuhan.</div>
            <div>- Dokumentasi pelaksanaan pekerjaan.</div>
            <div>- Manual, SOP, atau dokumen pendukung lainnya apabila dipersyaratkan.</div>
            <div>- Dokumen administrasi dan berita acara sesuai tahapan pekerjaan.</div>
        </div>
        <div class="sub">9.2. Dokumen Penagihan</div>
        <div class="list">
            <div>- Penagihan dilakukan sesuai tahapan penyelesaian pekerjaan dan ketentuan kontrak.</div>
            <div>- Dokumen penagihan harus telah diperiksa/diketahui oleh <b>{{direksi_pekerjaan}}</b>.</div>
        </div>
    </section>

    <section class="section">
        <div class="title">9. BARANG SISA &amp; LIMBAH</div>
        <div class="list">
            <div>10.1. Material/barang sisa dan limbah akibat pelaksanaan pekerjaan ditangani dan dibuang sesuai ketentuan yang berlaku.</div>
            <div>10.2. Penyedia bertanggung jawab menjaga kebersihan dan ketertiban lingkungan selama pelaksanaan pekerjaan.</div>
        </div>
    </section>

    <section class="section">
        <div class="title">10. QUALITY ACCEPTANCE</div>
        <p class="para">Parameter Quality Acceptance dalam lingkup pekerjaan <b>{{nama_pengadaan}}</b>, antara lain sebagai berikut:</p>
        <div class="list">
            <div>11.1. Hasil pekerjaan memenuhi fungsi dan spesifikasi yang dipersyaratkan.</div>
            <div>11.2. Hasil pekerjaan telah diperiksa dan dinyatakan sesuai oleh <b>{{direksi_pekerjaan}}</b>.</div>
            <div>11.3. Dokumen hasil pekerjaan tersedia dan lengkap.</div>
            <div>11.4. Tidak terdapat cacat/ketidaksesuaian yang menghambat fungsi pekerjaan.</div>
            <div>11.5. Hasil pekerjaan dapat diterima oleh pengguna barang/jasa.</div>
        </div>
    </section>

    <section class="section">
        <div class="title">11. WAKTU PENYELESAIAN PEKERJAAN</div>
        <div class="sub" style="font-weight:normal;">11.1. Jangka waktu pelaksanaan pekerjaan <b>{{nama_pengadaan}}</b> ditetapkan sesuai kontrak/dokumen pengadaan dan berlaku terhitung sejak tanggal mulai pelaksanaan yang disepakati.</div>
    </section>

    <section class="section">
        <div class="title">12. GARANSI</div>
        <div class="list">
            <div>12.1. Masa garansi pekerjaan mengikuti ketentuan kontrak dan dokumen pengadaan.</div>
            <div>12.2. Penyedia wajib memberikan respon atas gangguan/ketidaksesuaian yang disampaikan oleh pengguna.</div>
            <div>12.3. Penyedia wajib melakukan perbaikan/penyempurnaan terhadap ketidaksesuaian yang menjadi tanggung jawab penyedia.</div>
            <div>12.4. Pelaksanaan garansi tetap dikoordinasikan dengan <b>{{direksi_pekerjaan}}</b>.</div>
        </div>
    </section>

    <section class="section">
        <div class="title">13. LAIN-LAIN</div>
        <div class="list">
            <div>13.1. Setiap perubahan atau tambahan ruang lingkup pekerjaan harus dikomunikasikan dan disepakati oleh para pihak.</div>
            <div>13.2. Biaya yang menjadi tanggung jawab penyedia sesuai kontrak telah diperhitungkan dalam nilai pekerjaan <b>{{nilai_hpe}}</b>.</div>
            <div>13.3. Ketentuan lain yang belum tercantum tetap mengikuti dokumen pengadaan, kontrak, dan ketentuan perusahaan yang berlaku.</div>
        </div>
    </section>

    <!-- Lembar Pengesahan mengikuti halaman akhir PDF -->
    <div class="page-break" contenteditable="false"></div>
    <section class="approval">
        <div class="approval-title">
            LEMBAR PENGESAHAN<br>
            TERM OF REFERENCE (TOR)<br>
            {{nama_pengadaan}}
        </div>
        <div class="approval-date">{{unit_tujuan}}, {{tanggal_dokumen}}</div>

        <table class="approval-table">
            <tr><td><b>Disusun oleh:</b></td></tr>
            <tr><td class="small-gap"></td></tr>
            <tr><td>{{status_progres}}</td></tr>
            <tr><td><div class="gap"></div><span class="name">( {{pic_perencana}} )</span></td></tr>
        </table>

        <table class="approval-table two" style="margin-top:20px;">
            <tr><td colspan="2"><b>Diperiksa/Diketahui oleh:</b></td></tr>
            <tr><td colspan="2"><div class="small-gap"></div></td></tr>
            <tr>
                <td>Direksi Pekerjaan</td>
                <td>Pengguna Barang/Jasa</td>
            </tr>
            <tr>
                <td><div class="gap"></div><span class="name">( {{direksi_pekerjaan}} )</span></td>
                <td><div class="gap"></div><span class="name">( {{unit_tujuan}} )</span></td>
            </tr>
        </table>

        <table class="approval-table" style="margin-top:20px;">
            <tr><td><b>Disetujui oleh:</b></td></tr>
            <tr><td class="small-gap"></td></tr>
            <tr><td>Pejabat yang berwenang</td></tr>
            <tr><td><div class="gap"></div><span class="name">( {{direksi_pekerjaan}} )</span></td></tr>
        </table>
    </section>

</div>
</body>
</html>
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
