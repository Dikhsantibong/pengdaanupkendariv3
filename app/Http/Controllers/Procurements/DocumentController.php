<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\GenerateDocumentRequest;
use App\Http\Requests\Procurements\UpdateDocumentRequest;
use App\Http\Requests\Procurements\UploadSignedDocumentRequest;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementDocumentUpload;
use App\Services\DocumentGenerator;
use App\Services\DocumentPdfRenderer;
use App\Services\SignedDocumentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentGenerator $generator,
        protected DocumentPdfRenderer $pdf,
        protected SignedDocumentStore $signed,
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

        $this->assertBelongsTo($procurement, $document);

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

    /**
     * Open the editor for a generated document.
     */
    public function edit(Procurement $procurement, ProcurementDocument $document): Response
    {
        $this->authorize('editDocument', $procurement);

        $this->assertBelongsTo($procurement, $document);

        $document->load(['documentType', 'generatedBy', 'editedBy']);

        $values = $this->generator->placeholderValues($procurement);

        return Inertia::render('procurements/document-editor', [
            'procurement' => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'name' => $procurement->name,
            ],
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'body' => $document->rendered_body,
                'type' => $document->documentType->name,
                'template_version' => $document->template_version,
                'revision' => $document->revision,
                'generated_at' => $document->generated_at->toIso8601String(),
                'generated_by' => $document->generatedBy?->name,
                'edited_at' => $document->edited_at?->toIso8601String(),
                'edited_by' => $document->editedBy?->name,
            ],
            // The values the template pulled in, so whoever checks the document
            // can see at a glance which data landed in it and correct the
            // procurement instead of patching the text.
            'placeholders' => collect(DocumentGenerator::placeholderCatalog())
                ->map(fn (string $label, string $key): array => [
                    'key' => $key,
                    'label' => $label,
                    'value' => $values[$key] ?? '-',
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Save a hand corrected document body.
     */
    public function update(
        UpdateDocumentRequest $request,
        Procurement $procurement,
        ProcurementDocument $document,
    ): RedirectResponse {
        $this->authorize('editDocument', $procurement);

        $this->assertBelongsTo($procurement, $document);

        $this->generator->saveEdit(
            $document,
            $request->user(),
            $request->string('title')->value(),
            $request->string('body')->value(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Perubahan pada {$document->title} tersimpan.",
        ]);

        return back();
    }

    /**
     * Rebuild the document from its template using the current data.
     */
    public function regenerate(
        Request $request,
        Procurement $procurement,
        ProcurementDocument $document,
    ): RedirectResponse {
        $this->authorize('editDocument', $procurement);

        $this->assertBelongsTo($procurement, $document);

        try {
            $this->generator->regenerate($document, $request->user());
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen dimuat ulang dari template dengan data terbaru.',
        ]);

        return back();
    }

    /**
     * Attach the signed scans that came back from the printer.
     */
    public function storeSigned(
        UploadSignedDocumentRequest $request,
        Procurement $procurement,
        ProcurementDocument $document,
    ): RedirectResponse {
        $this->authorize('editDocument', $procurement);

        $this->assertBelongsTo($procurement, $document);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files');

        $stored = $this->signed->store($document, $files, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $stored->count().' dokumen bertanda tangan untuk '
                .$document->title.' tersimpan.',
        ]);

        return back();
    }

    /**
     * Download one signed scan of a document.
     */
    public function showSigned(
        Procurement $procurement,
        ProcurementDocument $document,
        ProcurementDocumentUpload $upload,
    ): StreamedResponse {
        $this->authorize('view', $procurement);

        $this->assertBelongsTo($procurement, $document);

        abort_unless($upload->procurement_document_id === $document->id, 404);

        return $this->signed->disk()->download($upload->path, $upload->file_name);
    }

    /**
     * Remove one signed scan so a corrected one can be uploaded.
     */
    public function destroySigned(
        Request $request,
        Procurement $procurement,
        ProcurementDocument $document,
        ProcurementDocumentUpload $upload,
    ): RedirectResponse {
        $this->authorize('editDocument', $procurement);

        $this->assertBelongsTo($procurement, $document);

        abort_unless($upload->procurement_document_id === $document->id, 404);

        $this->signed->remove($upload, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen bertanda tangan dihapus.',
        ]);

        return back();
    }

    /**
     * Remove every signed scan of a document at once.
     */
    public function destroyAllSigned(
        Request $request,
        Procurement $procurement,
        ProcurementDocument $document,
    ): RedirectResponse {
        $this->authorize('editDocument', $procurement);

        $this->assertBelongsTo($procurement, $document);

        abort_unless($document->signedUploads()->exists(), 404);

        $this->signed->removeAll($document, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Seluruh dokumen bertanda tangan dihapus.',
        ]);

        return back();
    }

    /**
     * Reject a document that belongs to a different procurement.
     */
    protected function assertBelongsTo(Procurement $procurement, ProcurementDocument $document): void
    {
        abort_unless($document->procurement_id === $procurement->id, 404);
    }
}
