<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\ProcurementStage;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Support\IndonesianNumber;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentGenerator
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * The placeholders every template may reference, with a human readable label.
     *
     * @return array<string, string>
     */
    public static function placeholderCatalog(): array
    {
        return [
            'nomor_pengadaan' => 'Nomor pengadaan internal',
            'nama_pengadaan' => 'Nama/judul pekerjaan',
            'nama_mitra' => 'Nama mitra/pelaksana pekerjaan',
            'direksi_pekerjaan' => 'Direksi pekerjaan',
            'unit_tujuan' => 'Unit tujuan (seluruh unit, dipisah koma)',
            'metode_pengadaan' => 'Metode pengadaan',
            'sumber_anggaran' => 'Sumber anggaran (kode, mis. AO)',
            'sumber_anggaran_keterangan' => 'Kepanjangan sumber anggaran, mis. Anggaran Operasi',
            'jenis_kontrak' => 'Jenis kontrak, mis. KHS atau Lumsum',
            'nomor_nota_dinas_manager' => 'Nomor nota dinas ke manager',
            'nomor_pr_ro' => 'Nomor PR/PO (diketik manual)',
            'nomor_pr_po' => 'Nomor PR/PO (diketik manual)',
            'nomor_prk' => 'Nomor PRK',
            'nomor_nota_dinas_usulan' => 'Nomor nota dinas usulan',
            'tanggal_nota_dinas_usulan' => 'Tanggal nota dinas usulan',
            'nomor_nota_dinas_icc' => 'Nomor nota dinas ke manager',
            'tanggal_nota_dinas_icc' => 'Tanggal nota dinas ke manager',
            'tanggal_nota_dinas_manager' => 'Tanggal nota dinas ke manager',
            'nomor_coa' => 'Nomor COA',
            'nomor_wo' => 'Nomor WO',
            'nilai_hpe' => 'Nilai sebelum nego / HPE (format Rupiah)',
            'nilai_hpe_angka' => 'Nilai HPE dalam angka tanpa simbol',
            'nilai_hpe_terbilang' => 'Nilai HPE dieja dalam huruf',
            'nilai_hpe_dpp' => 'Nilai DPP HPE (11/12, format Rupiah)',
            'nilai_hpe_dpp_angka' => 'Nilai DPP HPE dalam angka tanpa simbol',
            'nilai_hpe_ppn' => 'Nilai PPN 12% HPE (format Rupiah)',
            'nilai_hpe_ppn_angka' => 'Nilai PPN 12% HPE dalam angka tanpa simbol',
            'nilai_hpe_total' => 'Total HPE termasuk PPN (format Rupiah)',
            'nilai_hpe_total_angka' => 'Total HPE termasuk PPN dalam angka tanpa simbol',
            'nilai_hpe_total_terbilang' => 'Total HPE termasuk PPN dieja dalam huruf',
            'nilai_setelah_nego' => 'Nilai setelah nego (format Rupiah)',
            'nilai_setelah_nego_angka' => 'Nilai setelah nego dalam angka tanpa simbol',
            'nilai_setelah_nego_terbilang' => 'Nilai setelah nego dieja dalam huruf',
            'nilai_setelah_nego_dpp' => 'Nilai DPP setelah nego (11/12, format Rupiah)',
            'nilai_setelah_nego_dpp_angka' => 'Nilai DPP setelah nego dalam angka tanpa simbol',
            'nilai_setelah_nego_ppn' => 'Nilai PPN 12% setelah nego (format Rupiah)',
            'nilai_setelah_nego_ppn_angka' => 'Nilai PPN 12% setelah nego dalam angka tanpa simbol',
            'nilai_setelah_nego_total' => 'Total setelah nego termasuk PPN (format Rupiah)',
            'nilai_setelah_nego_total_angka' => 'Total setelah nego termasuk PPN dalam angka tanpa simbol',
            'nilai_setelah_nego_total_terbilang' => 'Total setelah nego termasuk PPN dieja dalam huruf',
            'tanggal_mulai_pelaksanaan' => 'Tanggal mulai pelaksanaan',
            'jangka_waktu_hari' => 'Jangka waktu pelaksanaan (hari)',
            'jangka_waktu_hari_terbilang' => 'Jangka waktu pelaksanaan dieja dalam huruf',
            'tanggal_selesai_pelaksanaan' => 'Tanggal akhir pelaksanaan (dihitung otomatis)',
            'masa_garansi_bulan' => 'Masa garansi (bulan)',
            'masa_garansi_bulan_terbilang' => 'Masa garansi dieja dalam huruf (mis. tiga)',
            'nomor_rekening' => 'Nomor rekening pelaksana',
            'nama_bank' => 'Nama bank pelaksana',
            'nama_pemilik_rekening' => 'Nama pemilik rekening',
            'status_progres' => 'Status progres saat ini',
            'pic_perencana' => 'Nama PIC perencana',
            'pic_pelaksana' => 'Nama PIC pelaksana',
            'nomor_ba_nego' => 'Nomor Berita Acara Negosiasi',
            'target_penyelesaian' => 'Target penyelesaian pengadaan',
            'tanggal_dokumen' => 'Tanggal dokumen digenerate',
            'tahun' => 'Tahun berjalan',
            'checklist_perencanaan' => 'Daftar checklist tahap perencanaan',
            'checklist_pelaksanaan' => 'Daftar checklist tahap pelaksanaan',
        ];
    }

    /**
     * Build the placeholder values for a procurement.
     *
     * @return array<string, string>
     */
    public function placeholderValues(Procurement $procurement): array
    {
        $procurement->loadMissing([
            'workDirector',
            'targetUnit',
            'procurementMethod',
            'budgetSource',
            'contractType',
            'targetUnits',
            'progressStatus',
            'planner',
            'executor',
            'checklists.checklistItem',
        ]);

        $hpe = (float) $procurement->hpe_value;
        $hpeDpp = (float) round($hpe * 11 / 12);
        $hpePpn = (float) round($hpe * 0.12);
        $hpeTotal = $hpe + $hpePpn;

        $nego = $procurement->value_after_negotiation !== null ? (float) $procurement->value_after_negotiation : null;
        $negoDpp = $nego !== null ? (float) round($nego * 11 / 12) : null;
        $negoPpn = $nego !== null ? (float) round($nego * 0.12) : null;
        $negoTotal = $nego !== null ? $nego + $negoPpn : null;

        return [
            'nomor_pengadaan' => $procurement->number,
            'nama_pengadaan' => $procurement->name,
            'nama_mitra' => $procurement->partner_name ?? '-',
            'direksi_pekerjaan' => $procurement->workDirector->name,
            'unit_tujuan' => $procurement->targetUnitNames(),
            'metode_pengadaan' => $procurement->procurement_method_id === null
                ? '-'
                : $procurement->procurementMethod->name,
            'sumber_anggaran' => $procurement->budget_source_id === null
                ? '-'
                : $procurement->budgetSource->name,
            'sumber_anggaran_keterangan' => $procurement->budget_source_id === null
                ? '-'
                : rtrim($procurement->budgetSource->description ?? $procurement->budgetSource->name, '.'),
            'jenis_kontrak' => $procurement->contract_type_id === null
                ? '-'
                : $procurement->contractType->name,
            'nomor_nota_dinas_manager' => $procurement->icc_memo_number ?? $procurement->manager_memo_number ?? '-',
            // Kept under its old key too, so templates written for PR/RO still fill in.
            'nomor_pr_ro' => $procurement->pr_po_number ?? '-',
            'nomor_pr_po' => $procurement->pr_po_number ?? '-',
            'nomor_prk' => $procurement->prk_number ?? '-',
            'nomor_nota_dinas_usulan' => $procurement->proposal_memo_number ?? '-',
            'tanggal_nota_dinas_usulan' => $procurement->proposal_memo_date?->translatedFormat('d F Y') ?? '-',
            'nomor_nota_dinas_icc' => $procurement->icc_memo_number ?? $procurement->manager_memo_number ?? '-',
            'tanggal_nota_dinas_icc' => $procurement->icc_memo_date?->translatedFormat('d F Y') ?? '-',
            'tanggal_nota_dinas_manager' => $procurement->icc_memo_date?->translatedFormat('d F Y') ?? '-',
            'nomor_coa' => $procurement->coa_number ?? '-',
            'nomor_wo' => $procurement->wo_number ?? '-',
            'nilai_hpe' => 'Rp '.number_format($hpe, 2, ',', '.'),
            'nilai_hpe_angka' => number_format($hpe, 0, ',', '.'),
            'nilai_hpe_terbilang' => Str::ucfirst(IndonesianNumber::spellRupiah($hpe)),
            'nilai_hpe_dpp' => 'Rp '.number_format($hpeDpp, 0, ',', '.'),
            'nilai_hpe_dpp_angka' => number_format($hpeDpp, 0, ',', '.'),
            'nilai_hpe_ppn' => 'Rp '.number_format($hpePpn, 0, ',', '.'),
            'nilai_hpe_ppn_angka' => number_format($hpePpn, 0, ',', '.'),
            'nilai_hpe_total' => 'Rp '.number_format($hpeTotal, 0, ',', '.'),
            'nilai_hpe_total_angka' => number_format($hpeTotal, 0, ',', '.'),
            'nilai_hpe_total_terbilang' => Str::title(IndonesianNumber::spellRupiah($hpeTotal)),
            'nilai_setelah_nego' => $nego === null
                ? '-'
                : 'Rp '.number_format($nego, 2, ',', '.'),
            'nilai_setelah_nego_angka' => $nego === null
                ? '-'
                : number_format($nego, 0, ',', '.'),
            'nilai_setelah_nego_terbilang' => $nego === null
                ? '-'
                : Str::title(IndonesianNumber::spellRupiah($nego)),
            'nilai_setelah_nego_dpp' => $negoDpp === null
                ? '-'
                : 'Rp '.number_format($negoDpp, 0, ',', '.'),
            'nilai_setelah_nego_dpp_angka' => $negoDpp === null
                ? '-'
                : number_format($negoDpp, 0, ',', '.'),
            'nilai_setelah_nego_ppn' => $negoPpn === null
                ? '-'
                : 'Rp '.number_format($negoPpn, 0, ',', '.'),
            'nilai_setelah_nego_ppn_angka' => $negoPpn === null
                ? '-'
                : number_format($negoPpn, 0, ',', '.'),
            'nilai_setelah_nego_total' => $negoTotal === null
                ? '-'
                : 'Rp '.number_format($negoTotal, 0, ',', '.'),
            'nilai_setelah_nego_total_angka' => $negoTotal === null
                ? '-'
                : number_format($negoTotal, 0, ',', '.'),
            'nilai_setelah_nego_total_terbilang' => $negoTotal === null
                ? '-'
                : Str::title(IndonesianNumber::spellRupiah($negoTotal)),
            'tanggal_mulai_pelaksanaan' => $procurement->execution_start_date?->translatedFormat('d F Y') ?? '-',
            'jangka_waktu_hari' => $procurement->execution_duration_days === null
                ? '-'
                : (string) $procurement->execution_duration_days,
            'jangka_waktu_hari_terbilang' => $procurement->execution_duration_days === null
                ? '-'
                : IndonesianNumber::spell((int) $procurement->execution_duration_days),
            'tanggal_selesai_pelaksanaan' => $procurement->executionEndDate()?->translatedFormat('d F Y') ?? '-',
            'masa_garansi_bulan' => $procurement->warranty_months === null
                ? '-'
                : (string) $procurement->warranty_months,
            'masa_garansi_bulan_terbilang' => $procurement->warranty_months === null
                ? '-'
                : IndonesianNumber::spell((int) $procurement->warranty_months),
            'nomor_rekening' => $procurement->bank_account_number ?? '-',
            'nama_bank' => $procurement->bank_name ?? '-',
            'nama_pemilik_rekening' => $procurement->bank_account_holder ?? '-',
            'status_progres' => $procurement->progressStatus->name,
            'pic_perencana' => $procurement->planner_id === null ? '-' : $procurement->planner->name,
            'pic_pelaksana' => $procurement->executor_id === null ? '-' : $procurement->executor->name,
            'nomor_ba_nego' => $procurement->number,
            'target_penyelesaian' => $procurement->target_completion_date === null
                ? '-'
                : $procurement->target_completion_date->translatedFormat('d F Y'),
            'tanggal_dokumen' => now()->translatedFormat('d F Y'),
            'tahun' => now()->format('Y'),
            'checklist_perencanaan' => $this->checklistMarkup($procurement, ProcurementStage::Perencanaan),
            'checklist_pelaksanaan' => $this->checklistMarkup($procurement, ProcurementStage::Pelaksanaan),
        ];
    }

    /**
     * Render a template body against a procurement without persisting it.
     */
    public function render(DocumentTemplate $template, Procurement $procurement): string
    {
        $values = $this->placeholderValues($procurement);

        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn (array $matches): string => $values[strtolower($matches[1])] ?? $matches[0],
            $template->body,
        ) ?? $template->body;
    }

    /**
     * Generate and archive a document for a procurement.
     */
    public function generate(Procurement $procurement, DocumentType $documentType, User $author): ProcurementDocument
    {
        $template = DocumentTemplate::resolveFor($documentType->id, $procurement->procurement_method_id);

        if ($template === null) {
            throw new RuntimeException("Template aktif untuk dokumen {$documentType->name} belum tersedia.");
        }

        $document = $procurement->documents()->create([
            'document_type_id' => $documentType->id,
            'document_template_id' => $template->id,
            'title' => "{$documentType->name} - {$procurement->name}",
            'file_name' => Str::slug("{$procurement->number}-{$documentType->code}").'.html',
            'template_version' => $template->version,
            'rendered_body' => $this->render($template, $procurement),
            'generated_by' => $author->id,
            'generated_at' => now(),
        ]);

        $this->procurements->recordActivity(
            $procurement,
            $author,
            ActivityType::DokumenDigenerate,
            "Dokumen {$documentType->name} digenerate.",
            ['document_type' => $documentType->code, 'template_version' => $template->version],
        );

        return $document;
    }

    /**
     * Save a hand corrected body onto an archived document.
     *
     * The body is the document: whatever an authorised user leaves here is
     * what gets printed, so the template is not consulted again.
     */
    public function saveEdit(
        ProcurementDocument $document,
        User $editor,
        string $title,
        string $body,
    ): ProcurementDocument {
        $document->title = $title;
        $document->rendered_body = $body;
        $document->revision = $document->revision + 1;
        $document->edited_by = $editor->id;
        $document->edited_at = now();
        $document->save();

        $this->procurements->recordActivity(
            $document->procurement,
            $editor,
            ActivityType::DokumenDiedit,
            "Dokumen {$document->title} diperbaiki (revisi {$document->revision}).",
            ['document_id' => $document->id, 'revision' => $document->revision],
        );

        return $document;
    }

    /**
     * Rebuild a document from its template using the current procurement data.
     *
     * The escape hatch for the other half of the problem: when the wording was
     * fine but the data pulled into the document was wrong, fix the
     * procurement and pull the values through again. Manual corrections made
     * since the last generate are discarded, which is the point.
     */
    public function regenerate(ProcurementDocument $document, User $editor): ProcurementDocument
    {
        $document->loadMissing(['procurement', 'documentType']);

        $template = DocumentTemplate::resolveFor(
            $document->document_type_id,
            $document->procurement->procurement_method_id,
        );

        if ($template === null) {
            throw new RuntimeException(
                "Template aktif untuk dokumen {$document->documentType->name} belum tersedia."
            );
        }

        $document->document_template_id = $template->id;
        $document->template_version = $template->version;
        $document->rendered_body = $this->render($template, $document->procurement);
        $document->revision = 0;
        $document->edited_by = null;
        $document->edited_at = null;
        $document->generated_by = $editor->id;
        $document->generated_at = now();
        $document->save();

        $this->procurements->recordActivity(
            $document->procurement,
            $editor,
            ActivityType::DokumenDigenerate,
            "Dokumen {$document->title} dimuat ulang dari template.",
            ['document_id' => $document->id, 'template_version' => $template->version],
        );

        return $document;
    }

    /**
     * Wrap a rendered document body in a printable HTML shell.
     *
     * The PDF renderer paginates with its own page box, so the on-screen page
     * simulation is dropped and a font the PDF engine actually embeds is used.
     */
    public function printableHtml(ProcurementDocument $document, bool $forPdf = false): string
    {
        $body = $document->rendered_body;

        if (preg_match('/<html[\s>]/i', $body)) {
            if ($forPdf) {
                return str_ireplace(
                    ['font-family: "Times New Roman", Times, serif;', "font-family: 'Times New Roman', Times, serif;"],
                    "font-family: 'DejaVu Serif', serif;",
                    $body,
                );
            }

            return $body;
        }

        $title = e($document->title);

        $fontStack = $forPdf
            ? "'DejaVu Serif', serif"
            : "'Times New Roman', Times, serif";

        $bodyBox = $forPdf
            ? 'margin: 0; padding: 0;'
            : 'max-width: 210mm; margin: 0 auto; padding: 16mm 14mm;';

        return <<<HTML
        <!doctype html>
        <html lang="id">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>{$title}</title>
        <style>
            /* The document is paper, never themed: force a light page so a
               browser in dark mode does not render dark text on dark. */
            :root { color-scheme: only light; }
            html, body { background: #fff; }
            @page { size: A4; margin: 22mm 18mm 20mm; }
            body { font-family: {$fontStack}; font-size: 11pt; color: #111; line-height: 1.45; text-align: justify; {$bodyBox} }
            @media print { body { max-width: none; margin: 0; padding: 0; } }
            h1 { font-size: 14pt; text-align: center; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4pt; }
            h2 { font-size: 12pt; text-transform: uppercase; border-bottom: 1px solid #111; padding-bottom: 2pt; margin-top: 18pt; }
            h3 { font-size: 11pt; margin: 12pt 0 4pt; }
            h4 { font-size: 11pt; margin: 10pt 0 4pt; }
            p { margin: 4pt 0; }
            table { width: 100%; border-collapse: collapse; margin: 8pt 0; font-size: 10.5pt; }
            td, th { border: 1px solid #444; padding: 4pt 6pt; vertical-align: top; }
            th { background: #f0f0f0; text-align: left; }
            ol, ul { margin: 4pt 0 4pt 18pt; padding: 0; }
            li { margin: 2pt 0; }

            /* Cover page */
            .cover { text-align: center; page-break-after: always; padding-top: 40pt; }
            .cover .org { font-size: 15pt; font-weight: bold; line-height: 1.3; }
            .cover .doc-title { font-size: 14pt; font-weight: bold; margin-top: 48pt; line-height: 1.4; }
            .cover .doc-meta { margin-top: 24pt; font-size: 12pt; }
            .cover .doc-meta table { width: auto; margin: 0 auto; }
            .cover .doc-meta td { border: none; padding: 2pt 6pt; text-align: left; }
            .cover .subject { margin-top: 48pt; font-size: 12pt; font-weight: bold; line-height: 1.5; }
            .cover .footer { margin-top: 96pt; font-size: 12pt; font-weight: bold; }

            /* Structure */
            .bab { page-break-before: always; }
            .bab-heading { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; border: none; margin: 0 0 18pt; }
            .lampiran { page-break-before: always; }
            .lampiran-head { width: 100%; margin-bottom: 12pt; }
            .lampiran-head td { border: none; padding: 1pt 0; font-size: 10.5pt; }
            .toc { list-style: none; margin-left: 0; }
            .toc li { margin: 3pt 0; }
            .toc ul { list-style: none; margin-left: 16pt; }

            /* Signatures */
            .signature { margin-top: 28pt; width: 100%; page-break-inside: avoid; }
            .signature td { border: none; text-align: center; vertical-align: top; padding: 6pt 8pt; }
            .signature .role { font-size: 11pt; }
            .signature .space { height: 56pt; }
            .signature .name { font-weight: bold; text-decoration: underline; }
            .fill { letter-spacing: .5pt; }
            .note { font-size: 10pt; font-style: italic; }
        </style>
        </head>
        <body>{$document->rendered_body}</body>
        </html>
        HTML;
    }

    /**
     * Build an HTML list of the checklist rows for a stage.
     */
    protected function checklistMarkup(Procurement $procurement, ProcurementStage $stage): string
    {
        $rows = $procurement->checklists
            ->where('stage', $stage)
            ->sortBy(fn ($checklist): int => $checklist->checklistItem->sort_order);

        if ($rows->isEmpty()) {
            return '<p>-</p>';
        }

        $items = $rows->map(function ($checklist): string {
            $mark = $checklist->is_completed ? '&#10003;' : '&ndash;';

            return '<li>['.$mark.'] '.e($checklist->checklistItem->name).'</li>';
        })->implode('');

        return '<ul>'.$items.'</ul>';
    }
}
