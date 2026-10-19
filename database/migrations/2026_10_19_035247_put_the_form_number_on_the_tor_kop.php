<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The TOR kop's Nomor Dokumen is the fixed number of the form,
 * FMKP – 167 – 9.1.1.2.e.a.c - 001, the same on every TOR.
 *
 * Put in the installed template, and in the TORs already drawn whose
 * Nomor Dokumen is still empty; one with a signed scan stays as signed.
 */
return new class extends Migration
{
    /**
     * The form number printed on every TOR.
     */
    protected const FORM_NUMBER = 'FMKP – 167 – 9.1.1.2.e.a.c - 001';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            return;
        }

        app(DocumentTemplateLibrary::class)->sync(keys: ['umum.tor'], force: true);

        DB::table('procurement_documents')
            ->where('rendered_body', 'like', '%<td class="label">Nomor Dokumen</td>%')
            ->whereNotExists(fn ($signed) => $signed->select(DB::raw(1))
                ->from('procurement_document_uploads')
                ->whereColumn('procurement_document_uploads.procurement_document_id', 'procurement_documents.id'))
            ->orderBy('id')
            ->each(function (object $document): void {
                $body = preg_replace(
                    '#(<td class="label">Nomor Dokumen</td>\s*<td class="value">:)\s*(</td>)#',
                    '$1 '.self::FORM_NUMBER.'$2',
                    (string) $document->rendered_body,
                    1,
                ) ?? (string) $document->rendered_body;

                if ($body !== $document->rendered_body) {
                    DB::table('procurement_documents')->where('id', $document->id)->update(['rendered_body' => $body]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
