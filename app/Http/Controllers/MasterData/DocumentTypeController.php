<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\ProcurementStage;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\SelectionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentTypeController extends MasterDataController
{
    /**
     * Store a new jenis dokumen.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normaliseFormat($request);

        $response = parent::store($request);

        $this->syncMethods($request, DocumentType::query()->latest('id')->firstOrFail());

        return $response;
    }

    /**
     * Update an existing jenis dokumen.
     */
    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        $this->normaliseFormat($request);

        $response = $this->updateRecord($request, $documentType);

        $this->syncMethods($request, $documentType);

        return $response;
    }

    /**
     * Keep the document to the selection methods chosen; none means every
     * method.
     */
    protected function syncMethods(Request $request, DocumentType $documentType): void
    {
        if ($request->has('selection_method_ids')) {
            $documentType->selectionMethods()->sync(array_map('intval', (array) $request->input('selection_method_ids', [])));
        }
    }

    /**
     * "none" is the select's way of saying every contract format.
     */
    protected function normaliseFormat(Request $request): void
    {
        if ($request->input('contract_number_format_id') === 'none') {
            $request->merge(['contract_number_format_id' => null]);
        }
    }

    /**
     * Deactivate a jenis dokumen.
     */
    public function destroy(DocumentType $documentType): RedirectResponse
    {
        return $this->destroyRecord($documentType);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/document-types';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Jenis dokumen';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return DocumentType::query()
            ->with(['activeTemplate', 'contractNumberFormat', 'selectionMethods'])
            ->withCount(['templates', 'procurementDocuments'])
            ->orderBy('stage')
            ->ordered()
            ->get()
            ->map(fn (DocumentType $record): array => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'stage' => $record->stage->value,
                'upload_only' => $record->upload_only,
                // "none" is the select's way of saying every format.
                'contract_number_format_id' => $record->contract_number_format_id === null ? 'none' : (string) $record->contract_number_format_id,
                'contract_number_format' => $record->contractNumberFormat?->code,
                'selection_method_ids' => $record->selectionMethods->pluck('id')->all(),
                'selection_methods' => $record->selectionMethods->pluck('name')->all(),
                'stage_label' => $record->stage->label(),
                'description' => $record->description,
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
                'template_count' => $record->templates_count,
                'active_template' => $record->activeTemplate === null ? null : [
                    'id' => $record->activeTemplate->id,
                    'name' => $record->activeTemplate->name,
                    'version' => $record->activeTemplate->version,
                ],
                'usage_count' => $record->procurement_documents_count,
            ])
            ->all();
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new DocumentType;
    }

    /**
     * Extra props sent to the Inertia page.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'stages' => ProcurementStage::options(),
            'selectionMethods' => SelectionMethod::query()->active()->ordered()->get()
                ->map(fn (SelectionMethod $method): array => ['value' => (string) $method->id, 'label' => $method->name])
                ->all(),
            'contractNumberFormats' => [
                ['value' => 'none', 'label' => 'Semua format'],
                ...ContractNumberFormat::query()->forContracts()->active()->ordered()->get()
                    ->map(fn (ContractNumberFormat $format): array => [
                        'value' => (string) $format->id,
                        'label' => "{$format->code} - {$format->name}",
                    ])->all(),
            ],
        ];
    }

    /**
     * Get the validation rules for storing or updating a record.
     *
     * @return array<string, mixed>
     */
    protected function rules(?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('document_types', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'stage' => ['required', Rule::enum(ProcurementStage::class)],
            // Optional so a form that predates the switch still saves.
            'upload_only' => ['sometimes', 'boolean'],
            // Asked for under this contract format only (e.g. the SPK's Pasal).
            'contract_number_format_id' => ['sometimes', 'nullable', Rule::exists('contract_number_formats', 'id')->whereNull('deleted_at')],
            // Asked for under these selection methods only; none for every one.
            'selection_method_ids' => ['sometimes', 'array'],
            'selection_method_ids.*' => ['integer', Rule::exists('selection_methods', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Normalise the document code.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        $validated['code'] = Str::slug((string) $validated['code']);

        // The methods are synced after the save, not filled onto the model.
        unset($validated['selection_method_ids']);

        return $validated;
    }
}
