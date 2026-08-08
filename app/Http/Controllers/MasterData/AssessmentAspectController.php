<?php

namespace App\Http\Controllers\MasterData;

use App\Models\AssessmentAspect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentAspectController extends MasterDataController
{
    /**
     * Store a new aspect.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalise($request);

        return parent::store($request);
    }

    /**
     * Update an existing aspect.
     */
    public function update(Request $request, AssessmentAspect $assessmentAspect): RedirectResponse
    {
        $this->normalise($request);

        return $this->updateRecord($request, $assessmentAspect);
    }

    /**
     * Deactivate an aspect.
     */
    public function destroy(AssessmentAspect $assessmentAspect): RedirectResponse
    {
        return $this->destroyRecord($assessmentAspect);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/assessment-aspects';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Aspek penilaian';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return AssessmentAspect::query()
            ->withCount(['scores', 'forms'])
            ->ordered()
            ->get()
            ->map(fn (AssessmentAspect $record): array => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'preamble' => $record->preamble,
                'indicators' => $record->indicators,
                'indicator_count' => count($record->indicators),
                'form_count' => $record->forms_count,
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
                'usage_count' => $record->scores_count,
            ])
            ->all();
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new AssessmentAspect;
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
                Rule::unique('assessment_aspects', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('assessment_aspects', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'preamble' => ['nullable', 'string', 'max:1000'],
            'indicators' => ['required', 'array', 'min:1', 'max:26'],
            'indicators.*' => ['required', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Adjust the validated attributes before they are persisted.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        // The aspect names are printed in capitals on the official form.
        $validated['name'] = Str::upper(trim((string) $validated['name']));

        return $validated;
    }

    /**
     * Tidy the submitted values before they are validated.
     *
     * The form tells the author their code is stored as a slug, so it has to
     * accept what a person types; slugging afterwards would let alpha_dash
     * reject "Manajemen K3" before it ever got the chance. Blank indicator
     * rows go the same way — they are a change of mind, not an error.
     */
    protected function normalise(Request $request): void
    {
        if ($request->filled('code')) {
            $request->merge(['code' => Str::slug((string) $request->input('code'))]);
        }

        $this->pruneList($request, 'indicators');
    }
}
