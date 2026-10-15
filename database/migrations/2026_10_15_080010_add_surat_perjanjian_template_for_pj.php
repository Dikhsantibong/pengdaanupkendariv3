<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Under the PJ format the "Kontrak" step draws a Surat Perjanjian instead of
 * the Surat Perintah Kerja. The template serves the SPK document type limited
 * to the PJ format, so PJ procurements pick it up and SPK ones are untouched.
 *
 * Contracts already drawn for a PJ procurement and not yet signed are
 * renamed; an unedited one follows the new template on its own.
 */
return new class extends Migration
{
    /**
     * The title prefix of the contract under each name.
     */
    private const SPK_PREFIX = 'SPK (Surat Perintah Kerja) - ';

    private const PJ_PREFIX = 'Surat Perjanjian (PJ) - ';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Where the default templates are installed, install this one too; a
        // fresh database gets it with the rest from the template seeders.
        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['pj.surat-perjanjian']);
        }

        $this->retitle(self::SPK_PREFIX, self::PJ_PREFIX);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->retitle(self::PJ_PREFIX, self::SPK_PREFIX);

        $typeId = DB::table('document_types')->where('code', 'spk')->value('id');

        // The template stays for the documents built from it; it is only
        // taken out of use.
        DB::table('document_templates')
            ->where('library_key', 'pj.surat-perjanjian')
            ->when($typeId !== null, fn ($query) => $query->where('document_type_id', $typeId))
            ->update(['is_active' => false]);
    }

    /**
     * Swap the title prefix of the unsigned contracts of PJ procurements.
     */
    private function retitle(string $from, string $to): void
    {
        $typeId = DB::table('document_types')->where('code', 'spk')->value('id');
        $formatId = DB::table('contract_number_formats')->where('code', 'PJ')->value('id');

        if ($typeId === null || $formatId === null) {
            return;
        }

        DB::table('procurement_documents')
            ->where('document_type_id', $typeId)
            ->whereIn('procurement_id', DB::table('procurements')->where('contract_number_format_id', $formatId)->select('id'))
            ->whereNotIn('id', DB::table('procurement_document_uploads')->select('procurement_document_id'))
            ->where('title', 'like', $from.'%')
            ->get(['id', 'title'])
            ->each(fn (object $document) => DB::table('procurement_documents')
                ->where('id', $document->id)
                ->update(['title' => $to.substr((string) $document->title, strlen($from))]));
    }
};
