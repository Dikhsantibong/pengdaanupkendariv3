<?php

use App\Models\ProcurementDocument;
use App\Services\DocumentGenerator;
use App\Services\DocumentTemplateLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The TOR holds only its points and sub points (the narrative is typed in by
 * hand) under the kop of the official form: PT PLN NUSANTARA POWER,
 * INTEGRATED MANAGEMENT SYSTEM and TERM OF REFERENCE (TOR) beside Nomor
 * Dokumen, Revisi, Tanggal Terbit and Halaman.
 *
 * The installed template is refreshed. TORs already drawn follow it: one
 * nobody edited is drawn again from the new template; an edited one keeps
 * what was typed and only gets the new kop. A TOR with a signed scan stays
 * as it was signed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            return;
        }

        // Forced: the outline replaces the TOR template even where an
        // administrator changed it.
        app(DocumentTemplateLibrary::class)->sync(keys: ['umum.tor'], force: true);

        $generator = app(DocumentGenerator::class);

        ProcurementDocument::query()
            ->whereHas('documentType', fn ($type) => $type->where('code', 'tor'))
            ->whereDoesntHave('signedUploads')
            ->whereNull('procurement_amendment_id')
            ->whereNull('procurement_delivery_order_id')
            ->with('procurement')
            ->lazyById()
            ->each(function (ProcurementDocument $document) use ($generator): void {
                if ($document->procurement === null) {
                    return;
                }

                if ((int) $document->revision === 0) {
                    $generator->refreshDocument($document);

                    return;
                }

                $body = $this->withKop((string) $document->rendered_body, CarbonImmutable::parse($document->generated_at));

                if ($body !== $document->rendered_body) {
                    DB::table('procurement_documents')->where('id', $document->id)->update(['rendered_body' => $body]);
                }
            });
    }

    /**
     * An edited TOR with the kop of the form in place of its old one.
     */
    protected function withKop(string $body, CarbonImmutable $generatedAt): string
    {
        $kop = '<table class="header">'
            .'<tr><td class="logo" rowspan="4"><img src="/logo/sidebar-logo.png" alt="PT PLN NUSANTARA POWER"></td>'
            .'<td class="company">PT PLN NUSANTARA POWER</td><td class="label">Nomor Dokumen</td><td class="value">: FMKP – 167 – 9.1.1.2.e.a.c - 001</td></tr>'
            .'<tr><td class="company">INTEGRATED MANAGEMENT SYSTEM</td><td class="label">Revisi</td><td class="value">: 00</td></tr>'
            .'<tr><td class="company tor" rowspan="2">TERM OF REFERENCE (TOR)</td><td class="label">Tanggal Terbit</td><td class="value">: '.$generatedAt->format('d - m - Y').'</td></tr>'
            .'<tr><td class="label">Halaman</td><td class="value">: </td></tr>'
            .'</table>';

        // The old kop: an outer table with the logo, the title and a nested
        // table of the document data.
        $body = preg_replace('#<table class="header">.*?</table>\s*</td>\s*</tr>\s*</table>#s', $kop, $body, 1) ?? $body;

        // Its stylesheet, so the new kop draws its borders and rows.
        $css = '.header td { border: 1px solid #000; vertical-align: middle; padding: 4px 6px; }'
            .' .header .logo { width: 22%; text-align: center; padding: 6px; }'
            .' .header .logo img { max-height: 48px; max-width: 140px; display: block; margin: 0 auto; }'
            .' .header .company { width: 37%; text-align: center; font-weight: bold; }'
            .' .header .company.tor { font-size: 11pt; }'
            .' .header .label { width: 16%; border-right: 0; }'
            .' .header .value { width: 25%; border-left: 0; }';

        return str_contains($body, '.header .company')
            ? $body
            : (preg_replace('#</style>#', $css."\n</style>", $body, 1) ?? $body);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
