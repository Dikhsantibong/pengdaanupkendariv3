<?php

use App\Services\DocumentTemplateLibrary;
use App\Support\TenderBeritaAcara;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A document can belong to some selection methods only, and the tenders
     * (Penunjukan Langsung, Lelang Terbuka 1 Tahap 1 / 2 Sampul) get their
     * own Berita Acara on the "Proses SMART SCM & Berita Acara" step, while
     * Pengadaan Langsung keeps its own.
     */
    public function up(): void
    {
        Schema::create('document_type_selection_method', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selection_method_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_type_id', 'selection_method_id'], 'document_type_selection_method_unique');
        });

        if (! DB::table('document_types')->exists()) {
            return;
        }

        foreach (TenderBeritaAcara::TYPES as $code => $name) {
            if (! DB::table('document_types')->where('code', $code)->exists()) {
                DB::table('document_types')->insert([
                    'code' => $code,
                    'name' => $name,
                    'stage' => 'pelaksanaan',
                    'sort_order' => (int) DB::table('document_types')->max('sort_order') + 1,
                    'upload_only' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $types = DB::table('document_types')->pluck('id', 'code');
        $methods = DB::table('selection_methods')->pluck('id', 'code');

        foreach (TenderBeritaAcara::METHODS as $code => $methodCodes) {
            foreach ($methodCodes as $methodCode) {
                if (isset($types[$code], $methods[$methodCode])) {
                    DB::table('document_type_selection_method')->insertOrIgnore([
                        'document_type_id' => $types[$code],
                        'selection_method_id' => $methods[$methodCode],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // The step files the new documents too, in the order the job runs
        // through them; links an administrator added are kept.
        $step = DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->whereNull('procurement_id')
            ->where('name', TenderBeritaAcara::STEP_NAME)
            ->value('id');

        if ($step !== null) {
            foreach (TenderBeritaAcara::STEP as $index => $code) {
                if (! isset($types[$code])) {
                    continue;
                }

                $link = ['checklist_item_id' => $step, 'document_type_id' => $types[$code]];

                if (DB::table('checklist_item_document_type')->where($link)->exists()) {
                    DB::table('checklist_item_document_type')->where($link)->update(['sort_order' => $index + 1]);
                } elseif (array_key_exists($code, TenderBeritaAcara::TYPES)) {
                    DB::table('checklist_item_document_type')->insert([
                        ...$link,
                        'sort_order' => $index + 1,
                        'is_alternative' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(groups: ['lelang']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $types = DB::table('document_types')->whereIn('code', array_keys(TenderBeritaAcara::TYPES))->pluck('id');

        DB::table('checklist_item_document_type')->whereIn('document_type_id', $types)->delete();
        DB::table('document_templates')->whereIn('document_type_id', $types)->delete();
        DB::table('document_types')->whereIn('id', $types)->whereNotExists(fn ($query) => $query
            ->from('procurement_documents')
            ->whereColumn('procurement_documents.document_type_id', 'document_types.id'))
            ->delete();

        Schema::dropIfExists('document_type_selection_method');
    }
};
