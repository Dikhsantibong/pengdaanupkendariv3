<?php

namespace App\Http\Controllers\MasterData;

use App\Models\PrRoNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrRoNumberController extends MasterDataController
{
    /**
     * Update an existing nomor PR/RO.
     */
    public function update(Request $request, PrRoNumber $prRoNumber): RedirectResponse
    {
        return $this->updateRecord($request, $prRoNumber);
    }

    /**
     * Deactivate a nomor PR/RO.
     */
    public function destroy(PrRoNumber $prRoNumber): RedirectResponse
    {
        return $this->destroyRecord($prRoNumber);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/pr-ro-numbers';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Nomor PR/RO';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return PrRoNumber::query()
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (PrRoNumber $record): array => [
                'id' => $record->id,
                'number' => $record->number,
                'description' => $record->description,
                'source' => $record->source,
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
        return new PrRoNumber;
    }

    /**
     * Get the validation rules for storing or updating a record.
     *
     * @return array<string, mixed>
     */
    protected function rules(?Model $record = null): array
    {
        return [
            'number' => [
                'required', 'string', 'max:255',
                Rule::unique('pr_ro_numbers', 'number')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
