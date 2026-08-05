<?php

namespace App\Http\Controllers\MasterData;

use App\Models\BudgetSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BudgetSourceController extends MasterDataController
{
    /**
     * Update an existing sumber anggaran.
     */
    public function update(Request $request, BudgetSource $budgetSource): RedirectResponse
    {
        return $this->updateRecord($request, $budgetSource);
    }

    /**
     * Deactivate a sumber anggaran.
     */
    public function destroy(BudgetSource $budgetSource): RedirectResponse
    {
        return $this->destroyRecord($budgetSource);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/budget-sources';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Sumber anggaran';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return BudgetSource::query()
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (BudgetSource $record): array => [
                'id' => $record->id,
                'code' => $record->code,
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
        return new BudgetSource;
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
                Rule::unique('budget_sources', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('budget_sources', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Store the budget code in upper case, as it is used on documents.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        $validated['code'] = Str::upper((string) $validated['code']);

        return $validated;
    }
}
