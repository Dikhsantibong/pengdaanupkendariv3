<?php

namespace App\Http\Controllers;

use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
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
     * one folder per contract format (SPK, PJ, SPPL).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $folder = $request->string('folder')->trim()->value() ?: null;

        $documents = $this->filtered($request)
            ->when($folder !== null, fn (Builder $query) => $this->inFolder($query, $folder))
            ->with(['procurement', 'documentType', 'generatedBy', 'editedBy', 'signedUploads'])
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
            'folders' => $this->folders($request),
            'filters' => [
                'search' => $request->string('search')->trim()->value() ?: null,
                'document_type_id' => $request->integer('document_type_id') ?: null,
                'folder' => $folder,
            ],
            'documentTypes' => DocumentType::query()->ordered()->get()
                ->map(fn (DocumentType $type): array => ['value' => $type->id, 'label' => $type->name])
                ->all(),
        ]);
    }

    /**
     * The documents the user may see, narrowed by the search and type filters.
     *
     * @return Builder<ProcurementDocument>
     */
    protected function filtered(Request $request): Builder
    {
        return ProcurementDocument::query()
            ->whereIn(
                'procurement_documents.procurement_id',
                Procurement::query()->visibleTo($request->user())->select('id'),
            )
            ->when($request->integer('document_type_id'), fn (Builder $query, int $id) => $query->where('procurement_documents.document_type_id', $id))
            ->when($request->string('search')->trim()->value(), function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('procurement_documents.title', 'like', "%{$search}%")
                        ->orWhereHas('procurement', fn (Builder $procurement) => $procurement->where('number', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * Keep the documents of one folder: a contract format's code, or the
     * folder of procurements without a format.
     *
     * @param  Builder<ProcurementDocument>  $query
     * @return Builder<ProcurementDocument>
     */
    protected function inFolder(Builder $query, string $folder): Builder
    {
        if ($folder === self::UNSORTED) {
            return $query->whereHas('procurement', fn (Builder $procurement) => $procurement->whereNull('contract_number_format_id'));
        }

        return $query->whereHas('procurement', fn (Builder $procurement) => $procurement->whereIn(
            'contract_number_format_id',
            ContractNumberFormat::query()->forContracts()->where('code', $folder)->select('id'),
        ));
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
