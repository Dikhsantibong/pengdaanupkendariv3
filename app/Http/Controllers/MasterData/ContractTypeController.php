<?php

namespace App\Http\Controllers\MasterData;

use App\Models\ContractType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContractTypeController extends MasterDataController
{
    /**
     * Store a new jenis kontrak.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->slugifyCode($request);

        return parent::store($request);
    }

    /**
     * Update an existing jenis kontrak.
     */
    public function update(Request $request, ContractType $contractType): RedirectResponse
    {
        $this->slugifyCode($request);

        return $this->updateRecord($request, $contractType);
    }

    /**
     * Deactivate a jenis kontrak.
     */
    public function destroy(ContractType $contractType): RedirectResponse
    {
        return $this->destroyRecord($contractType);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/contract-types';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Jenis kontrak';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return ContractType::query()
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (ContractType $record): array => [
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
        return new ContractType;
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
                Rule::unique('contract_types', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('contract_types', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Slug the code before it is validated.
     *
     * The form tells the author their code will be stored as a slug, so it has
     * to accept what a person types. Slugging afterwards would leave the
     * alpha_dash rule rejecting "Gabungan Lumsum" before it ever got the
     * chance, and the uniqueness check would run against the wrong value.
     */
    protected function slugifyCode(Request $request): void
    {
        if ($request->filled('code')) {
            $request->merge(['code' => Str::slug((string) $request->input('code'))]);
        }
    }
}
