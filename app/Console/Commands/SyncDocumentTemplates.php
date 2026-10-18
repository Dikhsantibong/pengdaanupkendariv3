<?php

namespace App\Console\Commands;

use App\Services\DocumentTemplateLibrary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('templates:sync
    {key?* : Only these library keys, e.g. pj.rks-jasa}
    {--group=* : Only these groups, e.g. pj or sppl}
    {--force : Also replace templates an administrator has customised with the shipped default}
    {--dry-run : Report what would change without writing}')]
#[Description('Install or refresh the default document templates from resources/document-templates')]
class SyncDocumentTemplates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DocumentTemplateLibrary $library): int
    {
        $outcomes = $library->sync(
            groups: $this->option('group'),
            keys: $this->argument('key'),
            force: (bool) $this->option('force'),
            dryRun: (bool) $this->option('dry-run'),
        );

        $labels = [
            DocumentTemplateLibrary::INSTALLED => 'dipasang',
            DocumentTemplateLibrary::UPDATED => 'diperbarui',
            DocumentTemplateLibrary::UP_TO_DATE => 'sudah terbaru',
            DocumentTemplateLibrary::CUSTOMISED => 'dilewati: sudah disesuaikan admin',
            DocumentTemplateLibrary::MISSING_TYPE => 'dilewati: jenis dokumen/format belum ada',
        ];

        $this->table(
            ['Template', 'Hasil'],
            collect($outcomes)->map(fn (string $outcome, string $key): array => [$key, $labels[$outcome] ?? $outcome])->values()->all(),
        );

        $counts = array_count_values($outcomes);

        $this->info(sprintf(
            '%s%d dipasang, %d diperbarui, %d sudah terbaru, %d dilewati.',
            $this->option('dry-run') ? '[simulasi] ' : '',
            $counts[DocumentTemplateLibrary::INSTALLED] ?? 0,
            $counts[DocumentTemplateLibrary::UPDATED] ?? 0,
            $counts[DocumentTemplateLibrary::UP_TO_DATE] ?? 0,
            ($counts[DocumentTemplateLibrary::CUSTOMISED] ?? 0) + ($counts[DocumentTemplateLibrary::MISSING_TYPE] ?? 0),
        ));

        if (($counts[DocumentTemplateLibrary::CUSTOMISED] ?? 0) > 0 && ! $this->option('force')) {
            $this->line('Template yang sudah disesuaikan tidak ditimpa. Pakai --force untuk menimpanya dengan template bawaan.');
        }

        return self::SUCCESS;
    }
}
