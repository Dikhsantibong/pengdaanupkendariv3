<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every document keeps a single template of one version.
 *
 * - The HPE and UPB take the latest layout (the item table filled from the
 *   Rincian Item) for every contract format, so the separate PJ copies go.
 * - Of every other template only the one in use stays: the older versions
 *   beside it are removed and it becomes version 1.
 * - Unused defaults of documents merged into another one are removed.
 *
 * Documents that pointed at a removed template move onto the one kept;
 * their stored text is not touched.
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

        // The latest HPE and UPB serve every format, customised or not.
        app(DocumentTemplateLibrary::class)->sync(keys: ['umum.hpe', 'umum.upb'], force: true);

        foreach (['hpe' => 'umum.hpe', 'upb' => 'umum.upb'] as $type => $key) {
            $kept = DB::table('document_templates')->where('library_key', $key)->orderByDesc('version')->value('id');

            if ($kept === null) {
                continue;
            }

            $this->replace(
                DB::table('document_templates')->where('library_key', "pj.{$type}")->pluck('id')->all(),
                (int) $kept,
            );
        }

        $scopes = DB::table('document_templates')
            ->whereNull('deleted_at')
            ->select('document_type_id', 'procurement_method_id', 'contract_number_format_id', 'work_category')
            ->distinct()
            ->get();

        foreach ($scopes as $scope) {
            $templates = DB::table('document_templates')
                ->where('document_type_id', $scope->document_type_id)
                ->where(fn ($query) => $scope->procurement_method_id === null ? $query->whereNull('procurement_method_id') : $query->where('procurement_method_id', $scope->procurement_method_id))
                ->where(fn ($query) => $scope->contract_number_format_id === null ? $query->whereNull('contract_number_format_id') : $query->where('contract_number_format_id', $scope->contract_number_format_id))
                ->where(fn ($query) => $scope->work_category === null ? $query->whereNull('work_category') : $query->where('work_category', $scope->work_category))
                // The one in use, else the newest one still listed.
                ->orderByRaw('deleted_at is not null')
                ->orderByDesc('is_active')
                ->orderByDesc('version')
                ->pluck('id');

            $kept = (int) $templates->shift();
            $this->replace($templates->all(), $kept);

            DB::table('document_templates')->where('id', $kept)->update(['version' => 1]);
        }

        DB::table('procurement_documents')->whereNotNull('document_template_id')->update(['template_version' => 1]);

        // Defaults the library no longer ships (merged into another document)
        // that nothing was drawn from.
        DB::table('document_templates')
            ->whereNotNull('library_key')
            ->whereNotIn('library_key', array_keys(app(DocumentTemplateLibrary::class)->entries()))
            ->where('is_active', false)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('procurement_documents')->whereColumn('procurement_documents.document_template_id', 'document_templates.id'))
            ->delete();
    }

    /**
     * Move the documents of the given templates onto the kept one and remove
     * those templates for good.
     *
     * @param  array<int, int|string>  $removed
     */
    protected function replace(array $removed, int $kept): void
    {
        if ($removed === []) {
            return;
        }

        DB::table('procurement_documents')
            ->whereIn('document_template_id', $removed)
            ->update(['document_template_id' => $kept]);

        DB::table('document_templates')->whereIn('id', $removed)->delete();
    }

    /**
     * Reverse the migrations.
     *
     * The removed versions are not brought back.
     */
    public function down(): void
    {
        //
    }
};
