<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\GenerateDocumentRequest;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Services\DocumentGenerator;
use App\Services\DocumentPdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use RuntimeException;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentGenerator $generator,
        protected DocumentPdfRenderer $pdf,
    ) {}

    /**
     * Generate a document for the procurement from the resolved template.
     */
    public function store(GenerateDocumentRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('generateDocument', $procurement);

        $documentType = DocumentType::query()->findOrFail($request->integer('document_type_id'));

        try {
            $document = $this->generator->generate($procurement, $documentType, $request->user());
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$document->title} berhasil digenerate."]);

        return back();
    }

    /**
     * Download a previously generated document.
     *
     * Defaults to PDF; `?format=html` returns the printable HTML instead.
     */
    public function show(
        Request $request,
        Procurement $procurement,
        ProcurementDocument $document,
    ): HttpResponse {
        $this->authorize('view', $procurement);

        abort_unless($document->procurement_id === $procurement->id, 404);

        if ($request->string('format')->value() === 'html') {
            return response($this->generator->printableHtml($document), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
            ]);
        }

        return response($this->pdf->bytes($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->pdf->fileName($document).'"',
        ]);
    }
}
