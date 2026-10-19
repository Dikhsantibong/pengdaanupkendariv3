<?php

namespace App\Console\Commands;

use App\Models\DocumentTemplate;
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
    {--nonaktifkan-template-lain : Deactivate every other active TOR template, so the shipped one is used}
    {--detail : List every TOR with what was done to it}
    {--dry-run : Report what would change without writing}')]
#[Description('Put the TOR template (points and sub points only, form kop, Penyusun) in place and repair the TORs already drawn')]
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
        $this->line('Template TOR bawaan: '.match ($outcome) {
            DocumentTemplateLibrary::INSTALLED => 'dipasang',
            DocumentTemplateLibrary::UPDATED => 'diperbarui menjadi kerangka poin',
            DocumentTemplateLibrary::UP_TO_DATE => 'sudah terbaru',
            DocumentTemplateLibrary::MISSING_TYPE => 'dilewati: jenis dokumen TOR belum ada',
            default => (string) $outcome,
        });

        $this->checkTemplates($dryRun);

        $counts = [
            'dibangun ulang' => 0,
            'kop & label diganti (isian tetap)' => 0,
            'dilewati (bertanda tangan)' => 0,
            'dilewati (tanpa template)' => 0,
        ];
        $details = [];

        ProcurementDocument::query()
            ->whereHas('documentType', fn ($type) => $type->where('code', 'tor'))
            ->whereNull('procurement_amendment_id')
            ->whereNull('procurement_delivery_order_id')
            ->whereHas('procurement')
            ->with('procurement')
            ->withExists('signedUploads')
            ->lazyById()
            ->each(function (ProcurementDocument $document) use ($generator, $dryRun, $withSigned, $overwrite, &$counts, &$details): void {
                $signed = (bool) $document->getAttribute('signed_uploads_exists');
                $edited = (int) $document->revision > 0;

                if ($signed && ! $withSigned) {
                    $counts['dilewati (bertanda tangan)']++;
                    $details[] = $this->detail($document, $signed, $edited, 'dilewati: sudah bertanda tangan (--termasuk-ttd)');

                    return;
                }

                // Nobody typed into it (or that may be discarded): drawn again
                // as the outline.
                if (! $edited || $overwrite) {
                    if (! $dryRun && ! $generator->rebuild($document)) {
                        $counts['dilewati (tanpa template)']++;
                        $details[] = $this->detail($document, $signed, $edited, 'dilewati: tidak ada template TOR yang cocok');

                        return;
                    }

                    // A template kept by an administrator may still say "User".
                    $this->save($document, TorKop::signAsPenyusun((string) $document->rendered_body), $dryRun);
                    $counts['dibangun ulang']++;
                    $details[] = $this->detail($document, $signed, $edited, 'dibangun ulang dari template');

                    return;
                }

                // Typed into: what was typed stays; the kop changes, and the
                // author signs as "Penyusun" instead of "User".
                $issueDate = $document->procurement->tor_issue_date ?? $document->generated_at;
                $body = TorKop::apply((string) $document->rendered_body, $issueDate);
                $body = TorKop::dateIn($body, $issueDate);

                // The signature page is the data's, not the typist's: drawn
                // again whole (title, Kendari and date, signatures in capitals).
                $page = $generator->torApprovalPage($document->procurement, $document->generated_at);
                $body = preg_replace_callback(
                    '#(<section class="approval"[^>]*>).*?(</section>)#s',
                    fn (array $match): string => $match[1].$page.$match[2],
                    $body,
                    1,
                ) ?? $body;

                $this->save($document, TorKop::signAsPenyusun($body), $dryRun);
                $counts['kop & label diganti (isian tetap)']++;
                $details[] = $this->detail($document, $signed, $edited, 'kop & label diganti, isian tetap');
            });

        $this->table(['Hasil', 'Jumlah TOR'], collect($counts)->map(fn (int $count, string $label): array => [$label, $count])->values()->all());

        if ($this->option('detail')) {
            $this->table(['ID', 'Pengadaan', 'Bertanda tangan', 'Diedit', 'Tindakan', 'Masih "User"'], $details);
        }

        if ($dryRun) {
            $this->warn('Simulasi: tidak ada yang diubah.');
        }

        if ($counts['dilewati (bertanda tangan)'] > 0) {
            $this->line('TOR bertanda tangan dibiarkan. Tambahkan --termasuk-ttd untuk ikut membangun ulang isinya (scan tidak berubah).');
        }

        if ($counts['kop & label diganti (isian tetap)'] > 0 && ! $overwrite) {
            $this->line('TOR yang sudah diketik hanya diganti kop dan labelnya. Tambahkan --timpa-isian untuk menggantinya dengan kerangka poin (isian hilang).');
        }

        return self::SUCCESS;
    }

    /**
     * Report every active TOR template; one other than the shipped default
     * may be picked for new TORs and still carry the old wording.
     */
    protected function checkTemplates(bool $dryRun): void
    {
        $templates = DocumentTemplate::query()
            ->whereHas('documentType', fn ($type) => $type->where('code', 'tor'))
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $others = $templates->filter(fn (DocumentTemplate $template): bool => $template->library_key !== 'umum.tor');

        $this->table(
            ['ID', 'Nama', 'Bawaan', 'Kerangka poin', 'Masih "User"'],
            $templates->map(fn (DocumentTemplate $template): array => [
                $template->id,
                $template->name,
                $template->library_key === 'umum.tor' ? 'ya' : 'tidak',
                str_contains($template->body, '{{pengesahan_tor}}') ? 'ya' : 'tidak',
                TorKop::stillSignsAsUser($template->body) || str_contains($template->body, '<td>User</td>') ? 'ya' : 'tidak',
            ])->all(),
        );

        if ($others->isEmpty()) {
            return;
        }

        if (! $this->option('nonaktifkan-template-lain')) {
            $this->warn('Ada template TOR aktif selain bawaan. TOR baru bisa memakai template itu. Tambahkan --nonaktifkan-template-lain untuk menonaktifkannya.');

            return;
        }

        if (! $dryRun) {
            $others->each(fn (DocumentTemplate $template) => $template->forceFill(['is_active' => false])->save());
        }

        $this->line($others->count().' template TOR lain dinonaktifkan.');
    }

    /**
     * Store a repaired body.
     */
    protected function save(ProcurementDocument $document, string $body, bool $dryRun): void
    {
        if (! $dryRun && $body !== $document->rendered_body) {
            $document->forceFill(['rendered_body' => $body])->save();
        }
    }

    /**
     * One row of the --detail list.
     *
     * @return array{int, string, string, string, string, string}
     */
    protected function detail(ProcurementDocument $document, bool $signed, bool $edited, string $action): array
    {
        return [
            (int) $document->id,
            (string) $document->procurement->number,
            $signed ? 'ya' : 'tidak',
            $edited ? 'ya' : 'tidak',
            $action,
            TorKop::stillSignsAsUser((string) $document->rendered_body) ? 'ya' : 'tidak',
        ];
    }
}
