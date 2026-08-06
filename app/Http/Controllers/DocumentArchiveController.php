<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentArchiveController extends Controller
{
    /**
     * Show every generated document the current user may access.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $documents = ProcurementDocument::query()
            ->whereIn(
                'procurement_id',
                Procurement::query()->visibleTo($request->user())->select('id'),
            )
            ->with(['procurement', 'documentType', 'generatedBy', 'editedBy', 'signedUploads'])
            ->when($request->integer('document_type_id'), fn ($query, int $id) => $query->where('document_type_id', $id))
            ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhereHas('procurement', fn ($p) => $p->where('number', 'like', "%{$search}%"));
                });
            })
            ->latest('generated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProcurementDocument $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->documentType->name,
                'template_version' => $document->template_version,
                'revision' => $document->revision,
                'procurement_id' => $document->procurement_id,
                'procurement_number' => $document->procurement->number,
                'procurement_name' => $document->procurement->name,
                'generated_by' => $document->generatedBy?->name,
                'generated_at' => $document->generated_at->toDateTimeString(),
                'edited_by' => $document->editedBy?->name,
                'edited_at' => $document->edited_at?->toDateTimeString(),
                'signed_count' => $document->signedUploads->count(),
            ]);

        return Inertia::render('documents/index', [
            'documents' => $documents,
            'filters' => [
                'search' => $request->string('search')->trim()->value() ?: null,
                'document_type_id' => $request->integer('document_type_id') ?: null,
            ],
            'documentTypes' => DocumentType::query()->ordered()->get()
                ->map(fn (DocumentType $type): array => ['value' => $type->id, 'label' => $type->name])
                ->all(),
        ]);
    }
}
