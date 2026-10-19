<?php

namespace App\Http\Controllers\MasterData;

use App\Models\SelectionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SelectionMethodController extends MasterDataController
{
    /**
     * Store a new metode pengadaan.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->slugifyCode($request);

        return parent::store($request);
    }

    /**
     * Update an existing metode pengadaan.
     */
    public function update(Request $request, SelectionMethod $selectionMethod): RedirectResponse
    {
        $this->slugifyCode($request);

        return $this->updateRecord($request, $selectionMethod);
    }

    /**
     * Deactivate a metode pengadaan.
     */
    public function destroy(SelectionMethod $selectionMethod): RedirectResponse
    {
        return $this->destroyRecord($selectionMethod);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/selection-methods';
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
        return SelectionMethod::query()
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (SelectionMethod $record): array => [
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
        return new SelectionMethod;
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
                Rule::unique('selection_methods', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('selection_methods', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Slug the code before it is validated, so what a person types passes
     * the alpha_dash rule and the uniqueness check runs on the stored value.
     */
    protected function slugifyCode(Request $request): void
    {
        if ($request->filled('code')) {
            $request->merge(['code' => Str::slug((string) $request->input('code'))]);
        }
    }
}
