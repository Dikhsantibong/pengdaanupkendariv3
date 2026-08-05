<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistItemController extends MasterDataController
{
    /**
     * Update an existing checklist item.
     */
    public function update(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        return $this->updateRecord($request, $checklistItem);
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
            ])
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
            'stage' => ['required', Rule::enum(ProcurementStage::class)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_optional' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
