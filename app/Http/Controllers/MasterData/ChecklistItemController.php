<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\ChecklistInput;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistItemController extends MasterDataController
{
    /**
     * Store a new checklist item along with the methods that skip it.
     */
    public function store(Request $request): RedirectResponse
    {
        $response = parent::store($request);

        $item = ChecklistItem::query()->latest('id')->firstOrFail();
        $this->syncRelations($request, $item);

        return $response;
    }

    /**
     * Update an existing checklist item.
     */
    public function update(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        $response = $this->updateRecord($request, $checklistItem);

        $this->syncRelations($request, $checklistItem);

        return $response;
    }

    /**
     * Deactivate a checklist item.
     */
    public function destroy(ChecklistItem $checklistItem): RedirectResponse
    {
        return $this->destroyRecord($checklistItem);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/checklist-items';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Item checklist';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return ChecklistItem::query()
            ->with(['excludedProcurementMethods:id', 'excludedContractNumberFormats:id', 'documentTypes:id,name'])
            ->withCount('procurementChecklists')
            ->orderBy('stage')
            ->ordered()
            ->get()
            ->map(fn (ChecklistItem $record): array => [
                'id' => $record->id,
                'stage' => $record->stage->value,
                'stage_label' => $record->stage->label(),
                'name' => $record->name,
                'description' => $record->description,
                'is_optional' => $record->is_optional,
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
                'usage_count' => $record->procurement_checklists_count,
                'excluded_procurement_method_ids' => $record->excludedProcurementMethods
                    ->pluck('id')
                    ->all(),
                'excluded_contract_number_format_ids' => $record->excludedContractNumberFormats
                    ->pluck('id')
                    ->all(),
                'input_kind' => $record->input_kind === null ? 'none' : $record->input_kind->value,
                'input_label' => $record->input_kind?->label(),
                'document_type_ids' => $this->currentLinks($record, false),
                'alternative_document_type_ids' => $this->currentLinks($record, true),
                'document_types' => $record->documentTypes
                    ->map(fn (DocumentType $type): string => $type->name.($type->isAlternative() ? ' (pilihan)' : ''))
                    ->all(),
            ])
            ->all();
    }

    /**
     * Drop the relation payload before the attributes are filled.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        unset(
            $validated['excluded_procurement_method_ids'],
            $validated['excluded_contract_number_format_ids'],
            $validated['document_type_ids'],
            $validated['alternative_document_type_ids'],
        );

        // "none" is the select's way of saying the step asks for nothing.
        $kind = $validated['input_kind'] ?? null;
        $validated['input_kind'] = $kind === null || $kind === 'none' ? null : $kind;

        return $validated;
    }

    /**
     * Persist the relations the form carries alongside the attributes.
     */
    protected function syncRelations(Request $request, ChecklistItem $item): void
    {
        if ($request->has('excluded_procurement_method_ids')) {
            /** @var array<int, int> $methodIds */
            $methodIds = $request->input('excluded_procurement_method_ids', []);

            $item->excludedProcurementMethods()->sync($methodIds);
        }

        if ($request->has('excluded_contract_number_format_ids')) {
            /** @var array<int, int> $formatIds */
            $formatIds = $request->input('excluded_contract_number_format_ids', []);

            $item->excludedContractNumberFormats()->sync($formatIds);
        }

        if (! $request->has('document_type_ids') && ! $request->has('alternative_document_type_ids')) {
            return;
        }

        /** @var array<int, int> $typeIds */
        $typeIds = $request->input('document_type_ids', $this->currentLinks($item, false));

        /** @var array<int, int> $alternativeIds */
        $alternativeIds = $request->input('alternative_document_type_ids', $this->currentLinks($item, true));

        // An empty selection makes the step a plain tick again. A document
        // listed as both required and alternative counts as required.
        $links = [];
        $order = 0;

        foreach (array_values($typeIds) as $id) {
            $links[(int) $id] = ['sort_order' => ++$order, 'is_alternative' => false];
        }

        foreach (array_values($alternativeIds) as $id) {
            $links[(int) $id] ??= ['sort_order' => ++$order, 'is_alternative' => true];
        }

        $item->documentTypes()->sync($links);
    }

    /**
     * The document type ids currently linked to a step, of one kind.
     *
     * @return array<int, int>
     */
    protected function currentLinks(ChecklistItem $item, bool $alternative): array
    {
        return $item->documentTypes
            ->filter(fn (DocumentType $type): bool => $type->isAlternative() === $alternative)
            ->pluck('id')
            ->all();
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new ChecklistItem;
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
            'inputKinds' => [
                ['value' => 'none', 'label' => 'Tanpa isian'],
                ...ChecklistInput::options(),
            ],
            'procurementMethods' => ProcurementMethod::query()->active()->ordered()->get()
                ->map(fn (ProcurementMethod $method): array => [
                    'value' => $method->id,
                    'label' => $method->name,
                ])->all(),
            'contractNumberFormats' => ContractNumberFormat::query()->active()->ordered()->get()
                ->map(fn (ContractNumberFormat $format): array => [
                    'value' => $format->id,
                    'label' => $format->code,
                ])->all(),
            'documentTypes' => DocumentType::query()->active()->orderBy('stage')->ordered()->get()
                ->map(fn (DocumentType $type): array => [
                    'value' => $type->id,
                    'label' => $type->stage->label().' — '.$type->name,
                ])->all(),
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
            'stage' => ['required', Rule::enum(ProcurementStage::class)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_optional' => ['required', 'boolean'],
            'input_kind' => ['nullable', Rule::in(['none', ...array_column(ChecklistInput::cases(), 'value')])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
            'excluded_procurement_method_ids' => ['sometimes', 'array'],
            'excluded_procurement_method_ids.*' => ['integer', Rule::exists('procurement_methods', 'id')],
            'excluded_contract_number_format_ids' => ['sometimes', 'array'],
            'excluded_contract_number_format_ids.*' => ['integer', Rule::exists('contract_number_formats', 'id')],
            // An empty list means the step is a plain tick with no paperwork.
            'document_type_ids' => ['sometimes', 'array'],
            'document_type_ids.*' => ['integer', Rule::exists('document_types', 'id')->whereNull('deleted_at')],
            // Alternatives: uploading any one of them completes the step.
            'alternative_document_type_ids' => ['sometimes', 'array'],
            'alternative_document_type_ids.*' => ['integer', Rule::exists('document_types', 'id')->whereNull('deleted_at')],
        ];
    }
}
