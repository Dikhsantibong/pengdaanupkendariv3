<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\ProcurementDocument;
use App\Models\ProcurementDocumentUpload;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps the signed scans that come back after a document has been printed.
 *
 * A document may carry several files: pages are often scanned in batches or
 * photographed one at a time. They live on the private disk and are only ever
 * streamed through the controller, so a scan of a signed berita acara is never
 * reachable by URL guessing the way a file under public/ would be.
 */
class SignedDocumentStore
{
    /**
     * Where the signed scans live on the private disk.
     */
    private const DIRECTORY = 'documents/signed';

    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Add signed scans to a document, keeping the ones already filed.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, ProcurementDocumentUpload>
     */
    public function store(ProcurementDocument $document, array $files, User $uploader): Collection
    {
        $stored = DB::transaction(function () use ($document, $files, $uploader): Collection {
            return collect($files)->map(
                fn (UploadedFile $file): ProcurementDocumentUpload => $this->storeOne($document, $file, $uploader),
            );
        });

        $names = $stored->pluck('file_name')->implode(', ');
        $count = $stored->count();

        $this->procurements->recordActivity(
            $document->procurement,
            $uploader,
            ActivityType::DokumenDitandatangani,
            "{$count} dokumen bertanda tangan untuk {$document->title} diunggah: {$names}.",
            ['document_id' => $document->id, 'files' => $stored->pluck('file_name')->all()],
        );

        $document->unsetRelation('signedUploads');

        return $stored;
    }

    /**
     * Remove one signed scan from a document.
     */
    public function remove(ProcurementDocumentUpload $upload, User $actor): void
    {
        $document = $upload->document;
        $name = $upload->file_name;

        $this->disk()->delete($upload->path);
        $upload->delete();

        $this->procurements->recordActivity(
            $document->procurement,
            $actor,
            ActivityType::DokumenDitandatangani,
            "Dokumen bertanda tangan {$name} untuk {$document->title} dihapus.",
            ['document_id' => $document->id, 'file_name' => $name],
        );

        $document->unsetRelation('signedUploads');
    }

    /**
     * Remove every signed scan of a document.
     */
    public function removeAll(ProcurementDocument $document, User $actor): void
    {
        $document->loadMissing('signedUploads');

        foreach ($document->signedUploads as $upload) {
            $this->disk()->delete($upload->path);
        }

        $count = $document->signedUploads->count();

        $document->signedUploads()->delete();

        $this->procurements->recordActivity(
            $document->procurement,
            $actor,
            ActivityType::DokumenDitandatangani,
            "Seluruh dokumen bertanda tangan untuk {$document->title} dihapus ({$count} berkas).",
            ['document_id' => $document->id],
        );

        $document->unsetRelation('signedUploads');
    }

    /**
     * The disk the signed scans are kept on.
     */
    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Put one uploaded file on disk and record it.
     */
    protected function storeOne(
        ProcurementDocument $document,
        UploadedFile $file,
        User $uploader,
    ): ProcurementDocumentUpload {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        // The stored name never reuses what the browser reported, so a crafted
        // file name cannot steer where the file lands.
        $name = Str::slug(pathinfo($document->file_name, PATHINFO_FILENAME)).'-ttd-'.Str::random(10);

        $path = $file->storeAs(
            self::DIRECTORY.'/'.$document->getKey(),
            $name.($extension === '' ? '' : '.'.$extension),
            'local',
        );

        $size = $file->getSize();

        return $document->signedUploads()->create([
            'path' => $path === false ? '' : $path,
            'file_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $size === false ? null : $size,
            'uploaded_by' => $uploader->id,
        ]);
    }
}
