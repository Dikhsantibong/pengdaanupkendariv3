<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\WorkCategory;
use App\Http\Controllers\Controller;
use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMethod;
use App\Services\DocumentGenerator;
use App\Services\DocumentPdfRenderer;
use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    /**
     * Show the document template management screen.
     */
    public function index(Request $request, DocumentTemplateLibrary $library): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('master-data/document-templates', [
            // Bodies are large (an RKS is 100+ KB), so the list ships without
            // them and the preview pulls the one it shows on demand.
            'preview' => Inertia::optional(function () use ($request): ?array {
                $template = DocumentTemplate::query()->find($request->integer('preview'));

                return $template === null ? null : [
                    'id' => $template->id,
                    'title' => "{$template->name} v{$template->version}",
                    'body' => $template->body,
                ];
            }),
            'templates' => $this->filtered(DocumentTemplate::query(), $filters)
                ->with(['documentType', 'procurementMethod', 'contractNumberFormat'])
                ->withCount('procurementDocuments')
                ->orderBy('document_type_id')
                ->orderBy('procurement_method_id')
                ->orderByDesc('version')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (DocumentTemplate $template): array => [
                    'id' => $template->id,
                    'document_type_id' => $template->document_type_id,
                    'document_type' => $template->documentType->name,
                    'procurement_method_id' => $template->procurement_method_id,
                    'procurement_method' => $template->procurementMethod?->name,
                    'contract_number_format' => $template->contractNumberFormat?->code,
                    'work_category' => $template->work_category?->label(),
                    'name' => $template->name,
                    'version' => $template->version,
                    'placeholders' => $template->placeholders ?? [],
                    // Shipped default, default changed here, or written here.
                    'origin' => $template->library_key === null
                        ? 'custom'
                        : ($library->checksum($template->body) === $template->library_checksum ? 'default' : 'modified'),
                    'is_active' => $template->is_active,
                    'usage_count' => $template->procurement_documents_count,
                    'updated_at' => $template->updated_at?->toDateTimeString(),
                ]),
            'filters' => $filters,
            'filterOptions' => [
                'documentTypes' => DocumentType::query()->ordered()->get(['id', 'name'])
                    ->map(fn (DocumentType $type): array => ['value' => (string) $type->id, 'label' => $type->name])->all(),
                'procurementMethods' => ProcurementMethod::query()->ordered()->get(['id', 'name'])
                    ->map(fn (ProcurementMethod $method): array => ['value' => (string) $method->id, 'label' => $method->name])->all(),
                'contractNumberFormats' => ContractNumberFormat::query()->ordered()->get(['id', 'code'])
                    ->map(fn (ContractNumberFormat $format): array => ['value' => (string) $format->id, 'label' => $format->code])->all(),
                'workCategories' => WorkCategory::options(),
            ],
        ]);
    }

    /**
     * The filters asked for, each null when not set.
     *
     * @return array{q: string|null, jenis: string|null, metode: string|null, format: string|null, kategori: string|null, status: string|null, asal: string|null}
     */
    protected function filters(Request $request): array
    {
        $value = fn (string $key): ?string => $request->string($key)->trim()->value() ?: null;

        return [
            'q' => $value('q'),
            'jenis' => $value('jenis'),
            'metode' => $value('metode'),
            'format' => $value('format'),
            'kategori' => $value('kategori'),
            'status' => $value('status'),
            'asal' => $value('asal'),
        ];
    }

    /**
     * Keep the templates the filters ask for. "semua" picks the templates
     * that apply to every metode, format or kategori (none set).
     *
     * @param  Builder<DocumentTemplate>  $query
     * @param  array{q: string|null, jenis: string|null, metode: string|null, format: string|null, kategori: string|null, status: string|null, asal: string|null}  $filters
     * @return Builder<DocumentTemplate>
     */
    protected function filtered(Builder $query, array $filters): Builder
    {
        $scoped = function (Builder $query, string $column, ?string $value): void {
            if ($value === 'semua') {
                $query->whereNull($column);
            } elseif ($value !== null) {
                $query->where($column, $value);
            }
        };

        $scoped($query, 'procurement_method_id', $filters['metode']);
        $scoped($query, 'contract_number_format_id', $filters['format']);
        $scoped($query, 'work_category', $filters['kategori']);

        return $query
            ->when($filters['q'], fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('documentType', fn (Builder $type) => $type->where('name', 'like', "%{$search}%"))))
            ->when($filters['jenis'], fn (Builder $query, string $type) => $query->where('document_type_id', $type))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('is_active', $status === 'aktif'))
            ->when($filters['asal'], fn (Builder $query, string $origin) => $origin === 'bawaan'
                ? $query->whereNotNull('library_key')
                : $query->whereNull('library_key'));
    }

    /**
     * Show the editor for a new template.
     */
    public function create(Request $request): Response
    {
        $documentTypeId = $request->integer('document_type_id') ?: null;

        return Inertia::render('master-data/document-template-editor', [
            ...$this->editorOptions(),
            // One document type, one template: only the types without one.
            'documentTypes' => DocumentType::query()->ordered()->whereDoesntHave('templates')->get()
                ->map(fn (DocumentType $type): array => [
                    'value' => $type->id,
                    'label' => $type->name,
                ])->all(),
            'template' => null,
            'initialDocumentTypeId' => DocumentType::query()->whereKey($documentTypeId)->exists()
                ? $documentTypeId
                : null,
        ]);
    }

    /**
     * Show the editor for an existing template.
     */
    public function edit(DocumentTemplate $documentTemplate): Response
    {
        return Inertia::render('master-data/document-template-editor', [
            ...$this->editorOptions(),
            'template' => [
                'id' => $documentTemplate->id,
                'document_type_id' => $documentTemplate->document_type_id,
                'procurement_method_id' => $documentTemplate->procurement_method_id,
                'contract_number_format_id' => $documentTemplate->contract_number_format_id,
                'work_category' => $documentTemplate->work_category?->value,
                'name' => $documentTemplate->name,
                'version' => $documentTemplate->version,
                'body' => $documentTemplate->body,
                'is_active' => $documentTemplate->is_active,
            ],
            'initialDocumentTypeId' => null,
        ]);
    }

    /**
     * Register the template of a document type.
     *
     * A document keeps a single template: one already serving the same
     * scope is changed in place, never joined by a second version.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $this->ensureScopeIsFree($validated);

        $template = new DocumentTemplate($validated);
        $template->version = 1;
        $template->placeholders = $this->extractPlaceholders($validated['body']);
        $template->save();

        $this->enforceSingleActiveTemplate($template);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Template {$template->name} v{$template->version} ditambahkan.",
        ]);

        return to_route('master-data.document-templates.edit', $template);
    }

    /**
     * Update an existing template.
     */
    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $validated = $request->validate($this->rules($documentTemplate));
        $this->ensureScopeIsFree($validated, $documentTemplate);

        $documentTemplate->fill($validated);
        $documentTemplate->placeholders = $this->extractPlaceholders($validated['body']);
        $documentTemplate->save();

        $this->enforceSingleActiveTemplate($documentTemplate);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template dokumen diperbarui.']);

        return back();
    }

    /**
     * The PDF of a template being looked at or edited, filled with sample
     * data, laid out by the same engine as a generated document. Nothing is
     * saved.
     */
    public function pdfPreview(Request $request, DocumentPdfRenderer $pdf): HttpResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:400000'],
            'document_type_id' => ['nullable', 'integer', Rule::exists('document_types', 'id')],
        ]);

        $draft = new ProcurementDocument;
        $draft->forceFill([
            'title' => $validated['title'] ?? 'Pratinjau Template',
            'rendered_body' => $validated['body'],
            'file_name' => 'pratinjau-template.html',
        ]);

        if (isset($validated['document_type_id'])) {
            $draft->setRelation('documentType', DocumentType::query()->find($validated['document_type_id']));
        }

        return response($pdf->render($draft), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pratinjau-template.pdf"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * The template opened from the document list: its scope and body with
     * what the visual editor needs, loaded only when asked for.
     *
     * @return array<string, mixed>|null
     */
    public static function workbench(?DocumentTemplate $template): ?array
    {
        if ($template === null) {
            return null;
        }

        return [
            'template' => [
                'id' => $template->id,
                'document_type_id' => $template->document_type_id,
                'procurement_method_id' => $template->procurement_method_id,
                'contract_number_format_id' => $template->contract_number_format_id,
                'work_category' => $template->work_category?->value,
                'name' => $template->name,
                'version' => $template->version,
                'body' => $template->body,
                'is_active' => $template->is_active,
            ],
            'placeholderCatalog' => collect(DocumentGenerator::placeholderCatalog())
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'documentStylesheet' => DocumentGenerator::documentStylesheet(),
        ];
    }

    /**
     * Archive a template without touching previously generated documents.
     */
    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $documentTemplate->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Template diarsipkan. Dokumen yang sudah digenerate tidak berubah.',
        ]);

        return back();
    }

    /**
     * The choices and reference data the template editor needs.
     *
     * @return array<string, mixed>
     */
    protected function editorOptions(): array
    {
        return [
            'documentTypes' => DocumentType::query()->ordered()->get()
                ->map(fn (DocumentType $type): array => [
                    'value' => $type->id,
                    'label' => $type->name,
                ])->all(),
            'procurementMethods' => ProcurementMethod::query()->ordered()->get()
                ->map(fn (ProcurementMethod $method): array => [
                    'value' => $method->id,
                    'label' => $method->name,
                ])->all(),
            'placeholderCatalog' => collect(DocumentGenerator::placeholderCatalog())
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'contractNumberFormats' => ContractNumberFormat::query()->ordered()->get()
                ->map(fn (ContractNumberFormat $format): array => [
                    'value' => $format->id,
                    'label' => $format->code,
                ])->all(),
            'workCategories' => WorkCategory::options(),
            'documentStylesheet' => DocumentGenerator::documentStylesheet(),
        ];
    }

    /**
     * Get the validation rules for a template.
     *
     * @return array<string, mixed>
     */
    protected function rules(?DocumentTemplate $template = null): array
    {
        return [
            'document_type_id' => [
                'required',
                Rule::exists('document_types', 'id')->whereNull('deleted_at'),
            ],
            'procurement_method_id' => [
                'nullable',
                Rule::exists('procurement_methods', 'id')->whereNull('deleted_at'),
            ],
            'contract_number_format_id' => [
                'nullable',
                Rule::exists('contract_number_formats', 'id')->whereNull('deleted_at'),
            ],
            'work_category' => ['nullable', Rule::enum(WorkCategory::class)],
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:200000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Refuse a second template for a document type that already has one
     * (an archived template does not count): one document type, one
     * template. A document drawn differently per method, format or category
     * is a document type of its own.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    protected function ensureScopeIsFree(array $validated, ?DocumentTemplate $except = null): void
    {
        $existing = DocumentTemplate::query()
            ->where('document_type_id', $validated['document_type_id'])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'document_type_id' => "Jenis dokumen ini sudah punya template ({$existing->name}). Satu jenis dokumen hanya memiliki satu template: ubah template itu, atau buat jenis dokumen baru untuk isi yang berbeda.",
            ]);
        }
    }

    /**
     * Keep a single active template per document type and procurement method.
     *
     * Activating a method specific template must not switch off the general
     * fallback used by the other methods, so the scope includes the method.
     */
    protected function enforceSingleActiveTemplate(DocumentTemplate $template): void
    {
        if (! $template->is_active) {
            return;
        }

        DocumentTemplate::query()
            ->where('document_type_id', $template->document_type_id)
            ->where('procurement_method_id', $template->procurement_method_id)
            ->where('contract_number_format_id', $template->contract_number_format_id)
            ->where('work_category', $template->work_category?->value)
            ->whereKeyNot($template->getKey())
            ->update(['is_active' => false]);
    }

    /**
     * Read the placeholders referenced by a template body.
     *
     * @return array<int, string>
     */
    protected function extractPlaceholders(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }
}
