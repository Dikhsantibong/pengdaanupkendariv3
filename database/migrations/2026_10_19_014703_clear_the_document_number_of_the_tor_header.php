<?php

use App\Services\DocumentTemplateLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The TOR header leaves its Nomor Dokumen (and Halaman) to be filled in by
 * hand, and its Tanggal Terbit is the day the TOR was generated.
 *
 * The installed template is refreshed, and the TOR documents already drawn
 * get the same header, unless they carry a signed upload (a record of what
 * was signed stays as it is).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['umum.tor']);
        }

        DB::table('procurement_documents')
            ->where('rendered_body', 'like', '%>Tanggal Terbit<%')
            ->whereNotExists(fn ($signed) => $signed->select(DB::raw(1))
                ->from('procurement_document_uploads')
                ->whereColumn('procurement_document_uploads.procurement_document_id', 'procurement_documents.id'))
            ->orderBy('id')
            ->each(function (object $document): void {
                $body = $this->header((string) $document->rendered_body, CarbonImmutable::parse($document->generated_at));

                if ($body !== $document->rendered_body) {
                    DB::table('procurement_documents')->where('id', $document->id)->update(['rendered_body' => $body]);
                }
            });
    }

    /**
     * The header rows of a TOR body with the number and page left empty and
     * the issue date set to the day it was generated.
     */
    protected function header(string $body, CarbonImmutable $generatedAt): string
    {
        $value = '(<td class="label">%s</td><td class="colon">:</td><td>)(.*?)(</td>)';

        $body = preg_replace('#'.sprintf($value, 'Nomor Dokumen / Pengadaan').'#s', '$1$3', $body) ?? $body;
        $body = preg_replace('#'.sprintf($value, 'Halaman').'#s', '$1$3', $body) ?? $body;

        return preg_replace(
            '#'.sprintf($value, 'Tanggal Terbit').'#s',
            '${1}'.$generatedAt->translatedFormat('d F Y').'$3',
            $body,
        ) ?? $body;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
