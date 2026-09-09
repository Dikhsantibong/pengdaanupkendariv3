<?php

namespace App\Services;

use App\Models\AssessmentAspect;
use App\Models\AssessmentForm;
use App\Models\VendorAssessment;
use App\Models\VendorAssessmentInvitation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Reproduces the official SMT-FM-DAN-02.02 layout.
 *
 * One assessor sheet, or the master recap that averages every aspect across
 * the assessors who scored it.
 */
class VendorAssessmentRenderer
{
    /**
     * Render one assessor sheet, or the master recap when no sheet is given.
     */
    public function html(VendorAssessment $assessment, ?AssessmentForm $form = null): string
    {
        $assessment->loadMissing(['scores.form', 'scores.aspect', 'invitations']);

        // The akumulasi is issued as a performance certificate, not the plain
        // internal form the individual assessor sheets use.
        if ($form === null) {
            return $this->certificateDocument($assessment);
        }

        $title = $form->name;
        $rows = $this->formRows($assessment, $form);
        $signature = $this->signature($assessment, $form);

        $body = $this->bodyContent($assessment, $title, $rows, $signature);

        return $this->htmlShell($body);
    }

    /**
     * Render a sheet to PDF bytes.
     */
    public function pdf(VendorAssessment $assessment, ?AssessmentForm $form = null): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        // The body is built here from our own data, never from user markup.
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->html($assessment, $form), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * The download file name of a sheet.
     */
    public function fileName(VendorAssessment $assessment, ?AssessmentForm $form = null): string
    {
        $suffix = $form === null ? 'akumulasi' : $form->code;

        return 'penilaian-kinerja-'.$assessment->id.'-'.$suffix.'.pdf';
    }

    /**
     * The scored rows of one assessor sheet.
     */
    protected function formRows(VendorAssessment $assessment, AssessmentForm $form): string
    {
        $form->loadMissing('aspects');
        $scores = $assessment->scoresForForm($form->id);

        return $form->aspects
            ->map(function (AssessmentAspect $aspect) use ($scores): string {
                $score = $scores->firstWhere('assessment_aspect_id', $aspect->id);

                return $this->row(
                    $aspect,
                    $score?->level === null ? '' : $this->number($score->level),
                );
            })
            ->implode('');
    }

    /**
     * The recap rows: every aspect with its average across assessors.
     */
    protected function recapRows(VendorAssessment $assessment): string
    {
        return AssessmentAspect::query()
            ->active()
            ->ordered()
            ->get()
            ->map(function (AssessmentAspect $aspect) use ($assessment): string {
                $average = $assessment->averageFor($aspect->id);

                return $this->row(
                    $aspect,
                    $average === null ? '' : $this->number($average),
                );
            })
            ->implode('');
    }

    /**
     * One numbered aspect row with its lettered indicators.
     */
    protected function row(AssessmentAspect $aspect, string $level): string
    {
        $letters = range('a', 'z');

        /** @var Collection<int, string> $indicators */
        $indicators = collect($aspect->indicators);

        $list = $indicators
            ->map(fn (string $text, int $index): string => '<div class="ind">'
                .($letters[$index] ?? '-').'. '.e($text).'</div>')
            ->implode('');

        $preamble = $aspect->preamble === null
            ? ''
            : '<div class="pre">'.e($aspect->preamble).'</div>';

        return <<<HTML
        <tr>
            <td class="no">{$aspect->sort_order}</td>
            <td class="ind-cell"><span class="aspect">{$aspect->name} :</span>{$preamble}{$list}</td>
            <td class="level">{$level}</td>
        </tr>
        HTML;
    }

    /**
     * Format a level the way the form does, with a comma as the decimal mark.
     */
    protected function number(int|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    /**
     * The signature block at the foot of a sheet.
     */
    protected function signature(VendorAssessment $assessment, ?AssessmentForm $form): string
    {
        $place = e($assessment->place);
        $date = $assessment->bastp_date?->translatedFormat('d F Y')
            ?? $assessment->form_date?->translatedFormat('d F Y')
            ?? '..........................';

        $title = $form === null
            ? 'Mengetahui,<br>Manager UP Kendari'
            : 'Penilai,<br>'.e($form->assessor_title);

        $invitation = null;
        $name = '';

        if ($form !== null) {
            /** @var VendorAssessmentInvitation|null $invitation */
            $invitation = $assessment->invitations->firstWhere('assessment_form_id', $form->id);

            $name = e($invitation === null
                ? (string) $form->assessor_name
                : (string) ($invitation->assessor_name ?? $form->assessor_name));

            $name = preg_replace('/\s*\([^)]*\)/', '', $name);
        }

        // A signature drawn through the WhatsApp link fills the blank space
        // above the name; otherwise the space is left for a wet signature.
        $drawn = $this->drawnSignature($invitation);

        $space = $drawn === null
            ? '<td class="space"></td>'
            : '<td class="space"><img src="'.$drawn.'" alt=""></td>';

        return <<<HTML
        <table class="sign">
            <tr><td>{$place}, {$date}</td></tr>
            <tr><td class="role">{$title}</td></tr>
            <tr>{$space}</tr>
            <tr><td class="name">{$name}</td></tr>
        </table>
        HTML;
    }

    /**
     * A signature drawn through a signing link, as a data URI.
     *
     * Embedded rather than linked because the signatures live on the private
     * disk, outside the chroot the renderer is confined to. Only submitted,
     * unrevoked links count: a signature collected and then withdrawn must not
     * keep appearing on the printed sheet.
     */
    protected function drawnSignature(?VendorAssessmentInvitation $invitation): ?string
    {
        if ($invitation === null || $invitation->submitted_at === null) {
            return null;
        }

        if ($invitation->revoked_at !== null || $invitation->signature_path === null) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($invitation->signature_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($disk->get($invitation->signature_path) ?? '');
    }

    /**
     * Generate the inner HTML body content for a single sheet.
     */
    protected function bodyContent(
        VendorAssessment $assessment,
        string $title,
        string $rows,
        string $signature,
    ): string {
        $logo = $this->logo();
        $project = e($assessment->project);
        $poNumber = e((string) ($assessment->po_number ?? ''));
        $poDate = $assessment->po_date?->translatedFormat('d F Y') ?? '';
        $bastpDate = $assessment->bastp_date?->translatedFormat('d F Y') ?? '';
        $vendor = e($assessment->vendor_name);
        $formNumber = e($assessment->form_number);
        $revision = e($assessment->revision_number);
        $formDate = $assessment->form_date?->translatedFormat('d F Y') ?? '';
        $sheet = e($title);

        return <<<HTML
            <table class="head">
                <tr>
                    <td class="logo">{$logo}</td>
                    <td class="title">FORMULIR<br>PENILAIAN KINERJA<br>PENYEDIA BARANG DAN JASA</td>
                    <td class="meta">
                        <span>No. Formulir</span>: {$formNumber}<br>
                        <span>No. Revisi</span>: {$revision}<br>
                        <span>Tanggal</span>: {$formDate}<br>
                        <span>Halaman</span>: 1 dari 1
                    </td>
                </tr>
            </table>

            <table class="fields">
                <tr><td class="label">PROJECT / PEKERJAAN</td><td class="colon">:</td><td class="value">{$project}</td></tr>
                <tr><td class="label">NO KONTRAK</td><td class="colon">:</td><td class="value">{$poNumber}</td></tr>
                <tr><td class="label">TANGGAL KONTRAK</td><td class="colon">:</td><td class="value">{$poDate}</td></tr>
                <tr><td class="label">TANGGAL BASTP</td><td class="colon">:</td><td class="value">{$bastpDate}</td></tr>
                <tr><td class="label">PENYEDIA BARANG/JASA</td><td class="colon">:</td><td class="value">{$vendor}</td></tr>
                <tr><td class="label">LEMBAR PENILAIAN</td><td class="colon">:</td><td class="value">{$sheet}</td></tr>
            </table>

            <table class="grid">
                <tr>
                    <th class="no">No.</th>
                    <th>INDIKATOR PENILAIAN</th>
                    <th class="level">LEVEL<br>(NILAI 1-5)</th>
                </tr>
                {$rows}
            </table>

            {$signature}
        HTML;
    }

    /**
     * The akumulasi rendered as a performance certificate.
     *
     * A framed, watermarked page addressed to the vendor. Each aspect carries a
     * percentage weight, so its score on the certificate is the average level
     * across the assessors who scored it multiplied by that weight, and the
     * total is the sum of those weighted figures. The contract details are
     * woven into the narrative, the sheet is dated from the BASTP, and it is
     * signed off by the procurement team.
     */
    protected function certificateDocument(VendorAssessment $assessment): string
    {
        $logo = $this->logo();
        $deco = $this->certificateDecoration();
        $vendor = e($assessment->vendor_name);
        $project = e($assessment->project);
        $poNumber = e((string) ($assessment->po_number ?? '-'));
        $poDate = $assessment->po_date?->translatedFormat('d F Y') ?? '-';
        $place = e($assessment->place);
        $date = $assessment->bastp_date?->translatedFormat('d F Y')
            ?? $assessment->form_date?->translatedFormat('d F Y')
            ?? '..........................';

        [$rows, $levelTotal, $total] = $this->certificateTable($assessment);

        $body = <<<HTML
            <div class="deco">{$deco}</div>
            <div class="watermark">UP KENDARI</div>
            <div class="content">
                <div class="logo">{$logo}</div>
                <h1 class="cert-title">LAPORAN KINERJA SUPPLIER</h1>
                <div class="rule"></div>
                <p class="given">diberikan kepada</p>
                <p class="vendor">{$vendor}</p>
                <p class="narrative">
                    Hasil kinerja perusahaan terhadap surat perjanjian nomor
                    <strong>{$poNumber}</strong>, tanggal <strong>{$poDate}</strong>
                    tentang <strong>{$project}</strong>.
                </p>

                <table class="grid">
                    <thead>
                        <tr>
                            <th class="ind">Indikator</th>
                            <th class="num">Bobot</th>
                            <th class="num">Level [1-5]</th>
                            <th class="num">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rows}
                    </tbody>
                    <tfoot>
                        <tr class="total">
                            <td class="ind" colspan="2">Total Nilai</td>
                            <td class="num">{$levelTotal}</td>
                            <td class="num">{$total}</td>
                        </tr>
                    </tfoot>
                </table>

                <p class="closing">
                    Atas nama manajemen PT PLN Nusantara Power UP Kendari, kami
                    mengucapkan terima kasih atas kerjasamanya dan selamat atas
                    kinerja Saudara, mohon dapat ditingkatkan lagi. Demikian atas
                    perhatiannya kami mengucapkan terima kasih.
                </p>

                <table class="sign-cert">
                    <tr><td>{$place}, {$date}</td></tr>
                    <tr><td class="space"></td></tr>
                    <tr><td class="role">Manajemen UP Kendari</td></tr>
                </table>
            </div>
        HTML;

        return $this->certificateShell($body);
    }

    /**
     * The certificate rows and their weighted total.
     *
     * Each aspect's score is its average level times its weight; unscored
     * aspects show a dash and contribute nothing to the totals. The footer
     * accumulates both the levels and the weighted scores.
     *
     * @return array{0: string, 1: string, 2: string} Rows HTML, the level
     *                                                total, and the weighted total.
     */
    protected function certificateTable(VendorAssessment $assessment): array
    {
        $levelTotal = 0.0;
        $total = 0.0;

        $rows = AssessmentAspect::query()
            ->active()
            ->ordered()
            ->get()
            ->map(function (AssessmentAspect $aspect) use ($assessment, &$levelTotal, &$total): string {
                $average = $assessment->averageFor($aspect->id);
                $name = e($aspect->name);
                $weight = $aspect->weight;

                $level = $average === null ? '-' : $this->number($average);

                if ($average === null) {
                    $value = '-';
                } else {
                    $levelTotal += $average;
                    $weighted = $average * $weight / 100;
                    $total += $weighted;
                    $value = $this->weightedNumber($weighted);
                }

                return <<<HTML
                <tr>
                    <td class="ind">{$name}</td>
                    <td class="num">{$weight}%</td>
                    <td class="num">{$level}</td>
                    <td class="num">{$value}</td>
                </tr>
                HTML;
            })
            ->implode('');

        return [$rows, $this->number($levelTotal), $this->weightedNumber($total)];
    }

    /**
     * Format a weighted figure with a comma decimal mark, trimmed.
     */
    protected function weightedNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
    }

    /**
     * The framed corner ornament, as an inline SVG data URI.
     *
     * Shapes only — navy and gold flourishes top-left and bottom-right plus a
     * double border — because dompdf renders SVG paths reliably but its SVG
     * text support is weak, so the "PLN" watermark is drawn as HTML instead.
     */
    protected function certificateDecoration(): string
    {
        $svg = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 210 297" preserveAspectRatio="none">
            <rect x="6" y="6" width="198" height="285" fill="none" stroke="#123a5e" stroke-width="0.6"/>
            <rect x="8" y="8" width="194" height="281" fill="none" stroke="#c9a227" stroke-width="0.3"/>
            <path d="M6,6 L52,6 C34,23 22,37 6,49 Z" fill="#123a5e"/>
            <path d="M6,6 L36,6 C25,15 17,23 6,33 Z" fill="#1f4e79"/>
            <path d="M58,6 C39,28 27,42 6,58" fill="none" stroke="#c9a227" stroke-width="0.9"/>
            <path d="M204,291 L158,291 C176,274 188,260 204,248 Z" fill="#123a5e"/>
            <path d="M204,291 L174,291 C185,282 193,274 204,264 Z" fill="#1f4e79"/>
            <path d="M204,240 C185,262 173,276 152,291" fill="none" stroke="#c9a227" stroke-width="0.9"/>
        </svg>
        SVG;

        return '<img src="data:image/svg+xml;base64,'.base64_encode($svg).'" alt="">';
    }

    /**
     * The certificate document shell, framed to the page edge.
     */
    protected function certificateShell(string $content): string
    {
        return <<<HTML
        <!doctype html>
        <html lang="id">
        <head>
        <meta charset="utf-8">
        <title>Laporan Kinerja Supplier</title>
        <style>
            :root { color-scheme: only light; }
            html, body { background: #fff; }
            /* Real page margins, so text on any overflow page keeps its inset;
               the decoration is drawn full-bleed behind, from the paper edge. */
            @page { size: A4 portrait; margin: 18mm 24mm; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #000; margin: 0; }
            .deco { position: fixed; top: -18mm; left: -24mm; width: 210mm; height: 297mm; }
            .deco img { width: 210mm; height: 297mm; }
            .watermark {
                position: fixed; top: 122mm; left: -24mm; width: 210mm;
                text-align: center; font-size: 54pt; font-weight: bold;
                color: #f0f0f0; letter-spacing: 6pt; white-space: nowrap;
            }
            .content { position: relative; text-align: center; }
            .logo { margin-bottom: 8pt; }
            .logo img { width: 168pt; }
            .cert-title { margin: 0; font-size: 15pt; font-weight: bold; letter-spacing: 1.5pt; color: #000; }
            .rule { width: 46pt; height: 2pt; background: #c9a227; margin: 6pt auto 0; }
            .given { margin: 12pt 0 2pt; font-size: 8.5pt; color: #000; letter-spacing: 0.3pt; }
            .vendor { margin: 0; font-size: 13pt; font-weight: bold; color: #000; letter-spacing: 0.5pt; }
            .narrative { margin: 9pt auto 0; max-width: 134mm; font-size: 9pt; line-height: 1.55; color: #000; }
            .grid { width: 100%; border-collapse: collapse; margin: 12pt 0; font-size: 8.5pt; }
            .grid thead th {
                padding: 5pt 8pt; color: #000; font-weight: bold; font-size: 7.5pt;
                letter-spacing: 0.4pt; text-transform: uppercase;
                border-top: 1.2pt solid #123a5e; border-bottom: 1.2pt solid #123a5e;
            }
            .grid tbody td { padding: 4pt 8pt; border-bottom: 0.4pt solid #e3e8ee; }
            .grid .ind { text-align: left; }
            .grid tbody .ind { font-style: italic; color: #000; }
            .grid .num { text-align: center; width: 58pt; }
            .grid tfoot .total td {
                padding: 5.5pt 8pt; border-top: 1.2pt solid #123a5e;
                font-weight: bold; color: #000; font-size: 9pt;
            }
            .grid tfoot .total .ind { text-align: right; text-transform: uppercase; letter-spacing: 0.4pt; }
            .closing { margin: 4pt auto 0; max-width: 134mm; font-size: 9pt; line-height: 1.55; text-align: justify; color: #000; }
            .sign-cert { width: 62mm; margin-top: 16pt; margin-left: auto; margin-right: 0; page-break-inside: avoid; }
            .sign-cert td { text-align: center; padding: 0; font-size: 9pt; }
            .sign-cert .space { height: 40pt; }
            .sign-cert .role { font-weight: bold; color: #000; }
        </style>
        </head>
        <body>
            {$content}
        </body>
        </html>
        HTML;
    }

    /**
     * The HTML document shell including styles.
     */
    protected function htmlShell(string $content): string
    {
        return <<<HTML
        <!doctype html>
        <html lang="id">
        <head>
        <meta charset="utf-8">
        <title>Formulir Penilaian Kinerja Penyedia</title>
        <style>
            :root { color-scheme: only light; }
            html, body { background: #fff; }
            @page { size: A4 portrait; margin: 12mm; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #000; margin: 0; }
            table { width: 100%; border-collapse: collapse; }
            .head td { border: 1px solid #1f3864; padding: 3pt 5pt; vertical-align: middle; }
            .head .logo { width: 104pt; text-align: center; }
            .head .logo img { width: 94pt; }
            .head .title { text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.35; }
            .head .meta { width: 168pt; font-size: 7.5pt; line-height: 1.5; }
            .head .meta span { display: inline-block; width: 62pt; }
            .fields { margin-top: 6pt; font-size: 8.5pt; }
            .fields td { padding: 1.5pt 0; vertical-align: top; }
            .fields .label { width: 118pt; }
            .fields .colon { width: 10pt; }
            .fields .value { border-bottom: 1px dotted #1f3864; }
            .grid { margin-top: 6pt; }
            .grid th, .grid td { border: 1px solid #1f3864; padding: 4pt 5pt; vertical-align: top; }
            .grid th { text-align: center; font-size: 8.5pt; line-height: 1.3; }
            .grid .no { width: 26pt; text-align: center; font-weight: bold; font-style: italic; }
            .grid .level { width: 92pt; text-align: center; vertical-align: middle; font-weight: bold; }
            .aspect { display: block; font-weight: bold; font-style: italic; }
            .pre { margin-top: 1pt; text-align: justify; }
            .ind { margin-left: 8pt; }
            .sign { margin-top: 16pt; width: auto; float: right; min-width: 200pt; }
            .sign td { text-align: center; padding: 0; }
            .sign .role { font-weight: bold; padding-top: 4pt; line-height: 1.35; }
            .sign .space { height: 52pt; vertical-align: middle; }
            .sign .space img { height: 48pt; }
            .sign .name { font-weight: bold; text-decoration: underline; }
            .recap-note { margin-top: 8pt; font-size: 7.5pt; font-style: italic; }
            
            .page-break { page-break-after: always; }
            .page-break:last-child { page-break-after: auto; }
        </style>
        </head>
        <body>
            {$content}
        </body>
        </html>
        HTML;
    }

    /**
     * Render the combined PDF of all forms for Panitia (without recap).
     */
    public function panitiaPdf(VendorAssessment $assessment): string
    {
        $assessment->loadMissing(['scores.form', 'scores.aspect', 'invitations']);
        $forms = AssessmentForm::query()->active()->ordered()->get();

        $bodies = [];

        foreach ($forms as $form) {
            $title = $form->name;
            $rows = $this->formRows($assessment, $form);
            $signature = $this->signature($assessment, $form);

            $bodies[] = '<div class="page-break">'.$this->bodyContent($assessment, $title, $rows, $signature).'</div>';
        }

        $html = $this->htmlShell(implode("\n", $bodies));

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Render the combined PDF of all forms including recap (akumulasi).
     */
    public function allPdf(VendorAssessment $assessment): string
    {
        $assessment->loadMissing(['scores.form', 'scores.aspect', 'invitations']);
        $forms = AssessmentForm::query()->active()->ordered()->get();

        $bodies = [];

        // 1. Recap (Akumulasi)
        $title = 'AKUMULASI HASIL PENILAIAN';
        $rows = $this->recapRows($assessment);
        $signature = $this->signature($assessment, null);
        $bodies[] = '<div class="page-break">'.$this->bodyContent($assessment, $title, $rows, $signature).'</div>';

        // 2. All Sheets
        foreach ($forms as $form) {
            $title = $form->name;
            $rows = $this->formRows($assessment, $form);
            $signature = $this->signature($assessment, $form);

            $bodies[] = '<div class="page-break">'.$this->bodyContent($assessment, $title, $rows, $signature).'</div>';
        }

        $html = $this->htmlShell(implode("\n", $bodies));

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * The unit logo, embedded so the PDF needs no network access.
     */
    protected function logo(): string
    {
        $path = $this->printableLogoPath();

        if ($path === null) {
            return '<b>PLN</b>';
        }

        $data = base64_encode((string) file_get_contents($path));

        return '<img src="data:image/png;base64,'.$data.'" alt="PLN">';
    }

    /**
     * A print-sized copy of the logo, built once and cached.
     *
     * The source asset is 2480x3397, which a PDF renderer expands to a raw
     * bitmap of roughly 34 MB — more than the whole request is allowed. The
     * logo prints about 16 mm wide, so a copy a few hundred pixels across is
     * indistinguishable and costs almost nothing.
     */
    protected function printableLogoPath(): ?string
    {
        $source = public_path('logo/sidebar-logo.png');

        if (! is_file($source)) {
            return null;
        }

        // Keyed on which file it came from and when that file last changed, so
        // swapping the letterhead logo cannot leave the old one cached.
        $key = substr(sha1($source.'|'.filemtime($source)), 0, 12);
        $cached = storage_path('app/private/cache/logo-print-'.$key.'.png');

        if (is_file($cached)) {
            return $cached;
        }

        if (! function_exists('imagecreatefrompng')) {
            return null;
        }

        $image = @imagecreatefrompng($source);

        if ($image === false) {
            return null;
        }

        $scaled = imagescale($image, 420);
        imagedestroy($image);

        if ($scaled === false) {
            return null;
        }

        // Keep the transparent background the letterhead relies on.
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        if (! is_dir(dirname($cached))) {
            mkdir(dirname($cached), 0755, true);
        }

        imagepng($scaled, $cached, 9);
        imagedestroy($scaled);

        return is_file($cached) ? $cached : null;
    }
}
