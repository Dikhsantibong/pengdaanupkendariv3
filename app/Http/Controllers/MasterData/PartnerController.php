<?php

namespace App\Http\Controllers\MasterData;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerController extends MasterDataController
{
    /**
     * Update an existing partner.
     */
    public function update(Request $request, Partner $partner): RedirectResponse
    {
        return $this->updateRecord($request, $partner);
    }

    /**
     * Deactivate a partner.
     */
    public function destroy(Partner $partner): RedirectResponse
    {
        return $this->destroyRecord($partner);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/partners';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Mitra';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return Partner::query()
            ->ordered()
            ->get()
            ->map(fn (Partner $record): array => [
                'id' => $record->id,
                'name' => $record->name,
                'vendor_code' => $record->vendor_code,
                'director_name' => $record->director_name,
                'address' => $record->address,
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
        return new Partner;
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
                Rule::unique('partners', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'vendor_code' => ['nullable', 'string', 'max:100'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Validation messages that replace the generated ones.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.unique' => 'Mitra dengan nama ini sudah ada.',
        ];
    }
}
