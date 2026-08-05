<?php

namespace App\Http\Controllers\MasterData;

use App\Models\WorkDirector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkDirectorController extends MasterDataController
{
    /**
     * Update an existing direksi pekerjaan.
     */
    public function update(Request $request, WorkDirector $workDirector): RedirectResponse
    {
        return $this->updateRecord($request, $workDirector);
    }

    /**
     * Deactivate a direksi pekerjaan.
     */
    public function destroy(WorkDirector $workDirector): RedirectResponse
    {
        return $this->destroyRecord($workDirector);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/work-directors';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Direksi pekerjaan';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return WorkDirector::query()
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (WorkDirector $record): array => [
                'id' => $record->id,
                'name' => $record->name,
                'description' => $record->description,
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
                'usage_count' => $record->procurements_count,
            ])
            ->all();
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new WorkDirector;
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
                Rule::unique('work_directors', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
