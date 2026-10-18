<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Under the SPK format the "Kontrak" step draws two documents:
 *
 * - the SPK itself, a table of what is ordered (who, what, how much, by
 *   when), from a new template;
 * - its Syarat & Ketentuan (Pasal), the long document of articles the SPK
 *   used to be, now a document type of its own on the same step.
 *
 * A document type may now belong to one contract format only: the Pasal
 * document is asked for under SPK alone, since a PJ carries its articles in
 * the Surat Perjanjian. The SPK article templates and the documents already
 * drawn from them move to the new type, so nothing drawn is lost.
 */
return new class extends Migration
{
    /**
     * The library templates holding the SPK articles.
     *
     * @var array<int, string>
     */
    private const ARTICLE_TEMPLATES = ['kontrak.spk-jasa', 'kontrak.spk-barang'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->foreignId('contract_number_format_id')->nullable()->after('upload_only')
                ->constrained()->nullOnDelete();
        });

        // Only where the SPK is set up already; a fresh install gets the
        // Pasal type from the master data seeder.
        if (! DB::table('document_types')->where('code', 'spk')->exists()) {
            return;
        }

        $spkFormat = DB::table('contract_number_formats')->where('code', 'SPK')->whereNull('deleted_at')->value('id');
        $articles = DB::table('document_types')->where('code', 'spk-pasal')->value('id');

        if ($articles === null) {
            $articles = DB::table('document_types')->insertGetId([
                'code' => 'spk-pasal',
                'name' => 'SPK - Syarat & Ketentuan (Pasal)',
                'stage' => 'pelaksanaan',
                'upload_only' => false,
                'contract_number_format_id' => $spkFormat,
                'description' => 'Pasal-pasal Surat Perintah Kerja, pelengkap tabel SPK.',
                'sort_order' => (int) DB::table('document_types')->where('code', 'spk')->value('sort_order') + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->linkToContractStep((int) $articles);

        // The article templates, every version, and what was drawn from them.
        $templateIds = DB::table('document_templates')->whereIn('library_key', self::ARTICLE_TEMPLATES)->pluck('id');

        if ($templateIds->isNotEmpty()) {
            DB::table('document_templates')->whereIn('id', $templateIds)->update(['document_type_id' => $articles, 'updated_at' => now()]);
            DB::table('procurement_documents')->whereIn('document_template_id', $templateIds)->update(['document_type_id' => $articles, 'updated_at' => now()]);
        }

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['kontrak.spk-tabel']);
        }
    }

    /**
     * Ask for the article document on the "Kontrak" step, right after the SPK.
     */
    private function linkToContractStep(int $articles): void
    {
        $spk = DB::table('document_types')->where('code', 'spk')->value('id');

        $steps = DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->whereNull('deleted_at')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Kontrak')
            ->pluck('id');

        foreach ($steps as $step) {
            $exists = DB::table('checklist_item_document_type')
                ->where('checklist_item_id', $step)
                ->where('document_type_id', $articles)
                ->exists();

            if ($exists) {
                continue;
            }

            $after = (int) DB::table('checklist_item_document_type')
                ->where('checklist_item_id', $step)
                ->where('document_type_id', $spk)
                ->value('sort_order');

            // Room right after the SPK.
            DB::table('checklist_item_document_type')
                ->where('checklist_item_id', $step)
                ->where('sort_order', '>', $after)
                ->increment('sort_order');

            DB::table('checklist_item_document_type')->insert([
                'checklist_item_id' => $step,
                'document_type_id' => $articles,
                'sort_order' => $after + 1,
                'is_alternative' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $spk = DB::table('document_types')->where('code', 'spk')->value('id');
        $articles = DB::table('document_types')->where('code', 'spk-pasal')->value('id');

        if ($spk !== null && $articles !== null) {
            $templateIds = DB::table('document_templates')->whereIn('library_key', self::ARTICLE_TEMPLATES)->pluck('id');

            DB::table('document_templates')->whereIn('id', $templateIds)->update(['document_type_id' => $spk]);
            DB::table('procurement_documents')->where('document_type_id', $articles)->update(['document_type_id' => $spk]);
            DB::table('document_templates')->where('library_key', 'kontrak.spk-tabel')->update(['is_active' => false]);
            DB::table('checklist_item_document_type')->where('document_type_id', $articles)->delete();
            DB::table('document_types')->where('id', $articles)->delete();
        }

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_number_format_id');
        });
    }
};
