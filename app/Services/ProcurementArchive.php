<?php

namespace App\Services;

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Support\ProposalDocuments;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Packs every file of a procurement into one ZIP, a folder per stage:
 *
 *   1. Usulan/       files sent with the work proposal
 *   2. Perencanaan/  planning documents
 *   3. Pelaksanaan/  execution documents
 *   4. Keuangan/     invoice and payment papers
 *
 * Inside a stage, each document has a folder holding its generated PDF
 * (with the latest data) and every signed or uploaded scan.
 */
class ProcurementArchive
{
    /**
     * The stage folders, in the order of the journey.
     *
     * @var array<string, string>
     */
    protected const FOLDERS = [
        'perencanaan' => '2. Perencanaan',
        'pelaksanaan' => '3. Pelaksanaan',
        'keuangan' => '4. Keuangan',
    ];

    public function __construct(
        protected DocumentGenerator $generator,
        protected DocumentPdfRenderer $pdf,
        protected SignedDocumentStore $signed,
    ) {}

    /**
     * Build the ZIP in a temporary file and return its path.
     *
     * The caller sends it and deletes it afterwards.
     */
    public function build(Procurement $procurement): string
    {
        $procurement->loadMissing(['documents.documentType', 'documents.signedUploads', 'attachments', 'checklists.checklistItem', 'checklists.files']);

        $path = tempnam(sys_get_temp_dir(), 'pengadaan-zip-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat berkas ZIP sementara.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat berkas ZIP.');
        }

        $used = [];
        $files = 0;

        foreach ($procurement->attachments as $attachment) {
            if (Storage::disk('local')->exists($attachment->path)) {
                $zip->addFromString(
                    $this->unique($used, '1. Usulan/Lampiran/'.$this->clean($attachment->file_name)),
                    (string) Storage::disk('local')->get($attachment->path),
                );
                $files++;
            }
        }

        foreach ($procurement->documents->sortBy('generated_at') as $document) {
            $files += $this->addDocument($zip, $document, $used);
        }

        // The papers uploaded on the Penyerahan / Pembayaran steps.
        foreach ($procurement->checklists as $checklist) {
            foreach ($checklist->files as $file) {
                if (Storage::disk('local')->exists($file->path)) {
                    $zip->addFromString(
                        $this->unique($used, self::FOLDERS[$checklist->stage->value].'/'
                            .$this->clean(Str::limit($checklist->checklistItem->name, 80, '')).'/'.$this->clean($file->file_name)),
                        (string) Storage::disk('local')->get($file->path),
                    );
                    $files++;
                }
            }
        }

        $zip->addFromString('BACA SAYA.txt', $this->readme($procurement, $files));
        $zip->close();

        return $path;
    }

    /**
     * The download name of a procurement's ZIP.
     */
    public function fileName(Procurement $procurement): string
    {
        return Str::slug($procurement->number.' '.Str::limit($procurement->name, 60, '')).'.zip';
    }

    /**
     * Add one document: its generated PDF and every uploaded scan.
     *
     * @param  array<string, true>  $used
     */
    protected function addDocument(ZipArchive $zip, ProcurementDocument $document, array &$used): int
    {
        $stage = $document->documentType->stage;

        // The TOR and Nota Dinas Usulan belong with the proposal.
        $stageFolder = array_key_exists($document->documentType->code, ProposalDocuments::TYPES)
            ? '1. Usulan'
            : self::FOLDERS[$stage->value];

        $folder = $stageFolder.'/'.$this->clean($document->documentType->name);
        $added = 0;

        // A generated document is drawn with the latest data, as on screen.
        if ($document->document_template_id !== null && $document->rendered_body !== '') {
            try {
                $this->generator->refreshDocument($document);

                $zip->addFromString(
                    $this->unique($used, $folder.'/'.$this->clean($this->pdf->fileName($document))),
                    $this->pdf->bytes($document),
                );
                $added++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach ($document->signedUploads as $upload) {
            if (! $this->signed->disk()->exists($upload->path)) {
                continue;
            }

            $prefix = $document->documentType->upload_only ? '' : 'TTD - ';

            $zip->addFromString(
                $this->unique($used, $folder.'/'.$prefix.$this->clean($upload->file_name)),
                (string) $this->signed->disk()->get($upload->path),
            );
            $added++;
        }

        return $added;
    }

    /**
     * A short note on what the archive holds.
     */
    protected function readme(Procurement $procurement, int $files): string
    {
        return implode("\r\n", [
            'Arsip dokumen pengadaan',
            '',
            'Nomor      : '.$procurement->number,
            'Pekerjaan  : '.$procurement->name,
            'Diunduh    : '.now()->translatedFormat('d F Y H:i'),
            'Jumlah file: '.$files,
            '',
            'Folder per tahap: 1. Usulan, 2. Perencanaan, 3. Pelaksanaan, 4. Keuangan.',
            'Dokumen yang digenerate disertakan sebagai PDF dengan data terbaru;',
            'berkas berawalan "TTD - " adalah hasil tanda tangan yang diunggah.',
        ]);
    }

    /**
     * Keep a file name safe for every operating system.
     */
    protected function clean(string $name): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $name) ?? $name;

        return trim($name, ' .-') ?: 'berkas';
    }

    /**
     * Make a path unique inside the archive by numbering repeats.
     *
     * @param  array<string, true>  $used
     */
    protected function unique(array &$used, string $path): string
    {
        $candidate = $path;
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = $extension === '' ? $path : substr($path, 0, -strlen($extension) - 1);

        for ($copy = 2; isset($used[strtolower($candidate)]); $copy++) {
            $candidate = $base." ({$copy})".($extension === '' ? '' : ".{$extension}");
        }

        $used[strtolower($candidate)] = true;

        return $candidate;
    }
}
