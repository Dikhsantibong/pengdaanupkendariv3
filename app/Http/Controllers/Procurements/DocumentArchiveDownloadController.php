<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Services\ProcurementArchive;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download every file of a procurement at once, as one ZIP.
 */
class DocumentArchiveDownloadController extends Controller
{
    public function __construct(protected ProcurementArchive $archive) {}

    /**
     * Build the procurement's ZIP and send it.
     */
    public function __invoke(Procurement $procurement): BinaryFileResponse
    {
        $this->authorize('view', $procurement);

        // Rendering many long documents can take a while.
        set_time_limit(300);

        return response()
            ->download($this->archive->build($procurement), $this->archive->fileName($procurement), [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend();
    }
}
