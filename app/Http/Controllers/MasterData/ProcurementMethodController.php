<?php

namespace App\Http\Controllers\MasterData;

use App\Models\ProcurementMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProcurementMethodController extends MasterDataController
{
    /**
     * Update an existing metode pengadaan.
     */
    public function update(Request $request, ProcurementMethod $procurementMethod): RedirectResponse
    {
        return $this->updateRecord($request, $procurementMethod);
    }

    /**
     * Deactivate a metode pengadaan.
     */
    public function destroy(ProcurementMethod $procurementMethod): RedirectResponse
    {
        return $this->destroyRecord($procurementMethod);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/procurement-methods';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Metode pengadaan';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return ProcurementMethod::query()
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (ProcurementMethod $record): array => [
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
        return new ProcurementMethod;
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
                Rule::unique('procurement_methods', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('procurement_methods', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Normalise the method code.
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
