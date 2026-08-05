<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\StatusCategory;
use App\Models\ProgressStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgressStatusController extends MasterDataController
{
    /**
     * Update an existing status progres.
     */
    public function update(Request $request, ProgressStatus $progressStatus): RedirectResponse
    {
        return $this->updateRecord($request, $progressStatus);
    }

    /**
     * Deactivate a status progres.
     */
    public function destroy(ProgressStatus $progressStatus): RedirectResponse
    {
        if ($progressStatus->procurements()->exists()) {
            $this->failValidation(
                'name',
                'Status ini masih dipakai pada pengadaan berjalan. Nonaktifkan terlebih dahulu.',
            );
        }

        return $this->destroyRecord($progressStatus);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/progress-statuses';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Status progres';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return ProgressStatus::query()
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (ProgressStatus $record): array => [
                'id' => $record->id,
                'name' => $record->name,
                'slug' => $record->slug,
                'category' => $record->category->value,
                'sort_order' => $record->sort_order,
                'is_default' => $record->is_default,
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
        return new ProgressStatus;
    }

    /**
     * Extra props sent to the Inertia page.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return ['categories' => StatusCategory::options()];
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
                Rule::unique('progress_statuses', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'category' => ['required', Rule::enum(StatusCategory::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Derive the slug and keep a single default status.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        $validated['slug'] = Str::slug((string) $validated['name']);

        if ($validated['is_default']) {
            ProgressStatus::query()
                ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->getKey()))
                ->update(['is_default' => false]);
        }

        return $validated;
    }
}
