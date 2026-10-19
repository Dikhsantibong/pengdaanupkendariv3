<?php

namespace App\Console\Commands;

use App\Models\ProcurementDocument;
use App\Services\DocumentGenerator;
use App\Services\DocumentTemplateLibrary;
use App\Support\TorKop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tor:perbaiki
    {--termasuk-ttd : Also redraw TORs that already have a signed scan (the scans stay as they are)}
    {--timpa-isian : Also redraw TORs someone has typed into, discarding what was typed}
    {--dry-run : Report what would change without writing}')]
#[Description('Put the TOR template (points and sub points only, form kop) in place and repair the TORs already drawn')]
class RepairTorDocuments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DocumentTemplateLibrary $library, DocumentGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withSigned = (bool) $this->option('termasuk-ttd');
        $overwrite = (bool) $this->option('timpa-isian');

        // The shipped outline replaces the installed TOR template, even one
        // an administrator changed.
        $outcome = $library->sync(keys: ['umum.tor'], force: true, dryRun: $dryRun)['umum.tor'] ?? null;
        $this->line('Template TOR: '.match ($outcome) {
            DocumentTemplateLibrary::INSTALLED => 'dipasang',
            DocumentTemplateLibrary::UPDATED => 'diperbarui menjadi kerangka poin',
            DocumentTemplateLibrary::UP_TO_DATE => 'sudah terbaru',
            DocumentTemplateLibrary::MISSING_TYPE => 'dilewati: jenis dokumen TOR belum ada',
            default => (string) $outcome,
        });

        $counts = ['dibangun ulang' => 0, 'kop diganti' => 0, 'dilewati (bertanda tangan)' => 0, 'dilewati (tanpa template)' => 0];

        ProcurementDocument::query()
            ->whereHas('documentType', fn ($type) => $type->where('code', 'tor'))
            ->whereNull('procurement_amendment_id')
            ->whereNull('procurement_delivery_order_id')
            ->whereHas('procurement')
            ->withExists('signedUploads')
            ->lazyById()
            ->each(function (ProcurementDocument $document) use ($generator, $dryRun, $withSigned, $overwrite, &$counts): void {
                if ($document->getAttribute('signed_uploads_exists') && ! $withSigned) {
                    $counts['dilewati (bertanda tangan)']++;

                    return;
                }

                // Nobody typed into it (or that may be discarded): drawn again
                // as the outline.
                if ((int) $document->revision === 0 || $overwrite) {
                    if ($dryRun || $generator->rebuild($document)) {
                        $counts['dibangun ulang']++;
                    } else {
                        $counts['dilewati (tanpa template)']++;
                    }

                    return;
                }

                // Typed into: what was typed stays, only the kop changes.
                $document->loadMissing('procurement');
                $body = TorKop::apply(
                    (string) $document->rendered_body,
                    $document->procurement->tor_issue_date ?? $document->generated_at,
                );

                if (! $dryRun && $body !== $document->rendered_body) {
                    $document->forceFill(['rendered_body' => $body])->save();
                }

                $counts['kop diganti']++;
            });

        $this->table(['Hasil', 'Jumlah TOR'], collect($counts)->map(fn (int $count, string $label): array => [$label, $count])->values()->all());

        if ($dryRun) {
            $this->warn('Simulasi: tidak ada yang diubah.');
        }

        if ($counts['dilewati (bertanda tangan)'] > 0) {
            $this->line('TOR bertanda tangan dibiarkan. Tambahkan --termasuk-ttd untuk ikut membangun ulang isinya (scan tidak berubah).');
        }

        if ($counts['kop diganti'] > 0 && ! $overwrite) {
            $this->line('TOR yang sudah diketik hanya diganti kop-nya. Tambahkan --timpa-isian untuk menggantinya dengan kerangka poin (isian hilang).');
        }

        return self::SUCCESS;
    }
}
