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

        return [
            'nota-dinas-usulan' => <<<HTML
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

            'tor' => <<<HTML
            <h1>Term of Reference (TOR)</h1>
            <h2>A. Identitas Pekerjaan</h2>
            {$identity}
            <h2>B. Maksud dan Tujuan</h2>
            <p>Pekerjaan {{nama_pengadaan}} dilaksanakan untuk menjaga keandalan dan kesiapan operasional
            pembangkit pada {{unit_tujuan}}.</p>
            <h2>C. Ruang Lingkup Pekerjaan</h2>
            <p>Ruang lingkup pekerjaan mengacu pada spesifikasi teknis yang ditetapkan oleh {{direksi_pekerjaan}}.</p>
            <h2>D. Jangka Waktu Pelaksanaan</h2>
            <p>Jangka waktu pelaksanaan ditetapkan pada dokumen RKS pekerjaan ini.</p>
            {$signature}
            HTML,

            'rab' => <<<HTML
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
            <h1>Harga Perkiraan Engineer (HPE)</h1>
            <h2>A. Identitas Pekerjaan</h2>
            {$identity}
            <h2>B. Nilai Harga Perkiraan Engineer</h2>
            <p>Nilai HPE untuk pekerjaan {{nama_pengadaan}} ditetapkan sebesar <b>{{nilai_hpe}}</b>
            ({{nilai_hpe_terbilang}}) sudah termasuk pajak yang berlaku.</p>
            {$signature}
            HTML,

            'upb' => <<<HTML
            <h1>Usulan Pengadaan Barang/Jasa (UPB)</h1>
            <h2>A. Identitas Pengadaan</h2>
            {$identity}
            <h2>B. Kelengkapan Dokumen</h2>
            {{checklist_perencanaan}}
            {$signature}
            HTML,

            'rks' => <<<HTML
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
