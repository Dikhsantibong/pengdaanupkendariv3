<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\ProcurementStage;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentTypeController extends MasterDataController
{
    /**
     * Update an existing jenis dokumen.
     */
    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        return $this->updateRecord($request, $documentType);
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
            ->with('activeTemplate')
            ->withCount(['templates', 'procurementDocuments'])
            ->orderBy('stage')
            ->ordered()
            ->get()
            ->map(fn (DocumentType $record): array => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'stage' => $record->stage->value,
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
        return ['stages' => ProcurementStage::options()];
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

        return $validated;
    }
}
