<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
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
            'documentTypes' => DocumentType::query()->ordered()->get()
                ->map(fn (DocumentType $type): array => [
                    'value' => $type->id,
                    'label' => $type->name,
                    'stage' => $type->stage->value,
                ])->all(),
            'procurementMethods' => ProcurementMethod::query()->ordered()->get()
                ->map(fn (ProcurementMethod $method): array => [
                    'value' => $method->id,
                    'label' => $method->name,
                ])->all(),
            'templates' => DocumentTemplate::query()
                ->with(['documentType', 'procurementMethod'])
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
                    'name' => $template->name,
                    'version' => $template->version,
                    'body' => $template->body,
                    'placeholders' => $template->placeholders ?? [],
                    'is_active' => $template->is_active,
                    'usage_count' => $template->procurement_documents_count,
                    'updated_at' => $template->updated_at?->toDateTimeString(),
                ])->all(),
            'placeholderCatalog' => collect(DocumentGenerator::placeholderCatalog())
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
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

        return back();
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
