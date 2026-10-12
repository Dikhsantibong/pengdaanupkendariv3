<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record where a template came from.
 *
 * Default templates now live as HTML files in resources/document-templates
 * (see DocumentTemplateLibrary) instead of being written into seeders and
 * migrations. library_key names the file entry a template was installed
 * from, library_checksum the text it was installed with, so
 * `php artisan templates:sync` can refresh untouched defaults and leave
 * customised ones alone.
 *
 * Existing templates are matched to their entry by scope and name. Their
 * checksum is the library text: a template whose body differs from it is
 * treated as customised and kept as it is.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->string('library_key', 100)->nullable()->after('work_category')->index();
            $table->char('library_checksum', 40)->nullable()->after('library_key');
        });

        $library = new DocumentTemplateLibrary;

        foreach ($library->entries() as $key => $entry) {
            $scope = $library->scope($entry);

            if ($scope === null) {
                continue;
            }

            $query = DB::table('document_templates')->where('name', $entry['name']);

            foreach ($scope as $column => $value) {
                $value === null ? $query->whereNull($column) : $query->where($column, $value);
            }

            $query->update([
                'library_key' => $key,
                'library_checksum' => $library->checksum($library->body($key)),
            ]);
        }

        // Install the defaults this database does not have yet; customised
        // templates are left alone. Later changes ship via templates:sync.
        $library->sync();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropIndex(['library_key']);
            $table->dropColumn(['library_key', 'library_checksum']);
        });
    }
};
