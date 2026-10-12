<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\WorkCategory;
use App\Http\Controllers\Controller;
use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use App\Services\DocumentGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    /**
     * Show the document template management screen.
     */
    public function index(Request $request): Response
    {
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
            'templates' => DocumentTemplate::query()
                ->with(['documentType', 'procurementMethod', 'contractNumberFormat'])
                ->withCount('procurementDocuments')
                ->orderBy('document_type_id')
                ->orderBy('procurement_method_id')
                ->orderByDesc('version')
                ->get()
                ->map(fn (DocumentTemplate $template): array => [
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
                    'is_active' => $template->is_active,
                    'usage_count' => $template->procurement_documents_count,
                    'updated_at' => $template->updated_at?->toDateTimeString(),
                ])->all(),
        ]);
    }

    /**
     * Show the editor for a new template.
     */
    public function create(Request $request): Response
    {
        $documentTypeId = $request->integer('document_type_id') ?: null;

        return Inertia::render('master-data/document-template-editor', [
            ...$this->editorOptions(),
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
     * Register a new template version for a document type.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        // Versions run independently per document type and procurement method.
        $nextVersion = (int) DocumentTemplate::query()
            ->where('document_type_id', $validated['document_type_id'])
            ->where('procurement_method_id', $validated['procurement_method_id'] ?? null)
            ->where('contract_number_format_id', $validated['contract_number_format_id'] ?? null)
            ->where('work_category', $validated['work_category'] ?? null)
            ->max('version') + 1;

        $template = new DocumentTemplate($validated);
        $template->version = $nextVersion;
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

        $documentTemplate->fill($validated);
        $documentTemplate->placeholders = $this->extractPlaceholders($validated['body']);
        $documentTemplate->save();

        $this->enforceSingleActiveTemplate($documentTemplate);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template dokumen diperbarui.']);

        return back();
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
