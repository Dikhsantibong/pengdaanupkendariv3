<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Lampiran SP forced a page break before "D. PEMBAYARAN", leaving most of
 * page 1 empty. The template no longer has it; this takes it out of the SP
 * appendices already drawn (and of templates copied from the old one), so
 * their sections flow on and fill the page.
 *
 * A signed appendix is left exactly as it was signed.
 */
return new class extends Migration
{
    /**
     * The SP appendix document types, current and merged.
     *
     * @var array<int, string>
     */
    private const CODES = ['lampiran-sp', 'lampiran-sp-barang', 'lampiran-sp-jasa', 'surat-pesanan-barang', 'surat-pesanan-jasa'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $typeIds = DB::table('document_types')->whereIn('code', self::CODES)->pluck('id');

        if ($typeIds->isEmpty()) {
            return;
        }

        $signed = DB::table('procurement_document_uploads')->select('procurement_document_id');

        DB::table('procurement_documents')
            ->whereIn('document_type_id', $typeIds)
            ->whereNotIn('id', $signed)
            ->where('rendered_body', 'like', '%page-break-before%')
            ->get(['id', 'rendered_body'])
            ->each(fn (object $document) => DB::table('procurement_documents')
                ->where('id', $document->id)
                ->update(['rendered_body' => $this->withoutForcedBreak((string) $document->rendered_body)]));

        DB::table('document_templates')
            ->whereIn('document_type_id', $typeIds)
            ->where('body', 'like', '%page-break-before%')
            ->get(['id', 'body'])
            ->each(fn (object $template) => DB::table('document_templates')
                ->where('id', $template->id)
                ->update(['body' => $this->withoutForcedBreak((string) $template->body)]));
    }

    /**
     * Reverse the migrations.
     *
     * The break was only empty space; it is not put back.
     */
    public function down(): void
    {
        //
    }

    /**
     * Drop the empty element that forced the page break.
     */
    private function withoutForcedBreak(string $html): string
    {
        return (string) preg_replace(
            '/\s*<div[^>]*style="[^"]*page-break-before:\s*always;?[^"]*"[^>]*>\s*<\/div>/i',
            '',
            $html,
        );
    }
};
