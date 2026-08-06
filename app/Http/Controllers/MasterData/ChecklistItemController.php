<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
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
            ->with(['excludedProcurementMethods:id', 'documentTypes:id,name'])
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
                'document_type_ids' => $record->documentTypes->pluck('id')->all(),
                'document_types' => $record->documentTypes->pluck('name')->all(),
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
        unset($validated['excluded_procurement_method_ids'], $validated['document_type_ids']);

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

        if (! $request->has('document_type_ids')) {
            return;
        }

        /** @var array<int, int> $typeIds */
        $typeIds = $request->input('document_type_ids', []);

        // An empty selection makes the step a plain tick again.
        $links = [];

        foreach (array_values($typeIds) as $index => $id) {
            $links[(int) $id] = ['sort_order' => $index + 1];
        }

        $item->documentTypes()->sync($links);
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
            'procurementMethods' => ProcurementMethod::query()->active()->ordered()->get()
                ->map(fn (ProcurementMethod $method): array => [
                    'value' => $method->id,
                    'label' => $method->name,
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
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
            'excluded_procurement_method_ids' => ['sometimes', 'array'],
            'excluded_procurement_method_ids.*' => ['integer', Rule::exists('procurement_methods', 'id')],
            // An empty list means the step is a plain tick with no paperwork.
            'document_type_ids' => ['sometimes', 'array'],
            'document_type_ids.*' => ['integer', Rule::exists('document_types', 'id')->whereNull('deleted_at')],
        ];
    }
}
