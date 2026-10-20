<?php

namespace App\Http\Controllers;

use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Support\FilterPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentArchiveController extends Controller
{
    /**
     * The folder of the documents whose procurement has no contract format yet.
     */
    protected const UNSORTED = 'lainnya';

    /**
     * Show every generated document the current user may access, filed in
     * one folder per contract format (SPK, PJ, SPPL) and grouped per
     * procurement, the most recently drawn work first.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $folder = $request->string('folder')->trim()->value() ?: null;
        $matching = fn (Builder $query) => $this->matching($query, $request);

        $groups = Procurement::query()
            ->visibleTo($request->user())
            ->whereHas('documents', $matching)
            ->when($folder !== null, fn (Builder $query) => $this->inFolder($query, $folder))
            ->withMax(['documents' => $matching], 'generated_at')
            ->withCount(['documents' => $matching])
            ->orderByDesc('documents_max_generated_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Procurement $procurement): array => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'name' => $procurement->name,
                'document_count' => (int) $procurement->getAttribute('documents_count'),
            ]);

        return Inertia::render('documents/index', [
            'groups' => $groups,
            'folders' => $this->folders($request),
            'filters' => [
                'search' => $request->string('search')->trim()->value() ?: null,
                'document_type_id' => $request->integer('document_type_id') ?: null,
                'folder' => $folder,
                ...FilterPeriod::current($request),
            ],
            'documentTypes' => DocumentType::query()->ordered()->get()
                ->map(fn (DocumentType $type): array => ['value' => $type->id, 'label' => $type->name])
                ->all(),
            'years' => FilterPeriod::years('procurement_documents', 'generated_at'),
        ]);
    }

    /**
     * Open one work's folder on a page of its own: its documents listed, the
     * chosen one shown large as a PDF. The archive's filters ride along so
     * the way back lands where the folder was opened.
     */
    public function procurement(Request $request, Procurement $procurement): Response
    {
        $this->authorize('view', $procurement);

        $documents = FilterPeriod::apply($procurement->documents()->getQuery(), $request, 'generated_at')
            ->when($request->integer('document_type_id'), fn (Builder $query, int $id) => $query->where('document_type_id', $id))
            ->with(['documentType', 'generatedBy', 'editedBy', 'signedUploads'])
            ->latest('generated_at')
            ->latest('id')
            ->get()
            ->map(fn (ProcurementDocument $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->documentType->name,
                'template_version' => $document->template_version,
                'revision' => $document->revision,
                'generated_by' => $document->generatedBy?->name,
                'generated_at' => $document->generated_at->toDateTimeString(),
                'edited_by' => $document->editedBy?->name,
                'edited_at' => $document->edited_at?->toDateTimeString(),
                'signed_count' => $document->signedUploads->count(),
            ])
            ->all();

        return Inertia::render('documents/procurement', [
            'procurement' => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'name' => $procurement->name,
            ],
            'documents' => $documents,
            'back' => array_filter([
                'folder' => $request->string('folder')->trim()->value() ?: null,
                'search' => $request->string('search')->trim()->value() ?: null,
                'document_type_id' => $request->integer('document_type_id') ?: null,
                ...FilterPeriod::current($request),
                'page' => $request->integer('page') > 1 ? $request->integer('page') : null,
            ]),
        ]);
    }

    /**
     * The documents the user may see, narrowed by the search and type filters.
     *
     * @return Builder<ProcurementDocument>
     */
    protected function filtered(Request $request): Builder
    {
        return $this->matching(
            ProcurementDocument::query()->whereIn(
                'procurement_documents.procurement_id',
                Procurement::query()->visibleTo($request->user())->select('id'),
            ),
            $request,
        );
    }

    /**
     * Narrow documents to the type and search filters.
     *
     * @param  Builder<ProcurementDocument>  $query
     * @return Builder<ProcurementDocument>
     */
    protected function matching(Builder $query, Request $request): Builder
    {
        // The year and month the document was drawn in.
        return FilterPeriod::apply($query, $request, 'generated_at')
            ->when($request->integer('document_type_id'), fn (Builder $query, int $id) => $query->where('procurement_documents.document_type_id', $id))
            ->when($request->string('search')->trim()->value(), function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('procurement_documents.title', 'like', "%{$search}%")
                        ->orWhereHas('procurement', fn (Builder $procurement) => $procurement
                            ->where('number', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * Keep the procurements of one folder: a contract format's code, or the
     * folder of procurements without a format.
     *
     * @param  Builder<Procurement>  $query
     * @return Builder<Procurement>
     */
    protected function inFolder(Builder $query, string $folder): Builder
    {
        if ($folder === self::UNSORTED) {
            return $query->whereNull('contract_number_format_id');
        }

        return $query->whereIn(
            'contract_number_format_id',
            ContractNumberFormat::query()->forContracts()->where('code', $folder)->select('id'),
        );
    }

    /**
     * One folder per contract format with how many documents it holds under
     * the current filters; the folder of unformatted procurements only shows
     * when it holds something.
     *
     * @return array<int, array{key: string, name: string, description: string, count: int}>
     */
    protected function folders(Request $request): array
    {
        /** @var array<int|string, int> $counts */
        $counts = $this->filtered($request)
            ->join('procurements', 'procurements.id', '=', 'procurement_documents.procurement_id')
            ->groupBy('procurements.contract_number_format_id')
            ->selectRaw('procurements.contract_number_format_id as format_id, count(*) as aggregate')
            ->pluck('aggregate', 'format_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        // A retired format keeps its folder while it still holds documents.
        $folders = ContractNumberFormat::query()->forContracts()->ordered()->get()
            ->filter(fn (ContractNumberFormat $format): bool => $format->is_active || isset($counts[$format->id]))
            ->map(fn (ContractNumberFormat $format): array => [
                'key' => $format->code,
                'name' => $format->code,
                'description' => $format->name,
                'count' => $counts[$format->id] ?? 0,
            ])
            ->values()
            ->all();

        $unsorted = $counts[''] ?? 0;

        if ($unsorted > 0) {
            $folders[] = ['key' => self::UNSORTED, 'name' => 'Lainnya', 'description' => 'Jenis kontrak belum ditentukan', 'count' => $unsorted];
        }

        return $folders;
    }
}
