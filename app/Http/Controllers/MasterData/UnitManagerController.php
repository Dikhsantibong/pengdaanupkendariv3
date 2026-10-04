<?php

namespace App\Http\Controllers\MasterData;

use App\Models\UnitManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitManagerController extends MasterDataController
{
    /**
     * Update an existing manager.
     */
    public function update(Request $request, UnitManager $unitManager): RedirectResponse
    {
        return $this->updateRecord($request, $unitManager);
    }

    /**
     * Deactivate a manager.
     */
    public function destroy(UnitManager $unitManager): RedirectResponse
    {
        return $this->destroyRecord($unitManager);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/unit-managers';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Manager UP Kendari';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return UnitManager::query()
            ->ordered()
            ->get()
            ->map(fn (UnitManager $record): array => [
                'id' => $record->id,
                'name' => $record->name,
                'position' => $record->position,
                'description' => $record->description,
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
            ])
            ->all();
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new UnitManager;
    }

    /**
     * Get the validation rules for storing or updating a record.
     *
     * @return array<string, mixed>
     */
    protected function rules(?Model $record = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('unit_managers', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'position' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
