<?php

namespace App\Http\Controllers\MasterData;

use App\Models\AssessmentAspect;
use App\Models\AssessmentForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentFormController extends MasterDataController
{
    /**
     * Store a new assessor sheet along with the aspects it scores.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalise($request);

        $response = parent::store($request);

        $this->syncAspects($request, AssessmentForm::query()->latest('id')->firstOrFail());

        return $response;
    }

    /**
     * Update an existing assessor sheet.
     */
    public function update(Request $request, AssessmentForm $assessmentForm): RedirectResponse
    {
        $this->normalise($request);

        $response = $this->updateRecord($request, $assessmentForm);

        $this->syncAspects($request, $assessmentForm);

        return $response;
    }

    /**
     * Deactivate an assessor sheet.
     */
    public function destroy(AssessmentForm $assessmentForm): RedirectResponse
    {
        return $this->destroyRecord($assessmentForm);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/assessment-forms';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Lembar penilai';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return AssessmentForm::query()
            ->with('aspects:id,name')
            ->withCount('scores')
            ->ordered()
            ->get()
            ->map(fn (AssessmentForm $record): array => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'assessor_title' => $record->assessor_title,
                'assessor_name' => $record->assessor_name,
                'assessor_options' => $record->assessor_options ?? [],
                'description' => $record->description,
                'aspect_ids' => $record->aspects->pluck('id')->all(),
                'aspect_names' => $record->aspects->pluck('name')->all(),
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
                'usage_count' => $record->scores_count,
            ])
            ->all();
    }

    /**
     * The aspects a sheet can be given.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'aspectOptions' => AssessmentAspect::query()
                ->active()
                ->ordered()
                ->get(['id', 'name'])
                ->map(fn (AssessmentAspect $aspect): array => [
                    'value' => (string) $aspect->id,
                    'label' => $aspect->name,
                ])
                ->all(),
        ];
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new AssessmentForm;
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
                Rule::unique('assessment_forms', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('assessment_forms', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'assessor_title' => ['required', 'string', 'max:255'],
            'assessor_name' => ['nullable', 'string', 'max:255'],
            'assessor_options' => ['nullable', 'array', 'max:50'],
            'assessor_options.*' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'aspect_ids' => ['required', 'array', 'min:1'],
            'aspect_ids.*' => ['integer', Rule::exists('assessment_aspects', 'id')->whereNull('deleted_at')],
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
        /** @var array<int, string> $options */
        $options = $validated['assessor_options'] ?? [];

        // An empty list means the sheet takes a typed name rather than a
        // choice, which is how every sheet but Direksi Pekerjaan works.
        $validated['assessor_options'] = $options === [] ? null : $options;

        // The pivot is synced after the save, not filled onto the model.
        unset($validated['aspect_ids']);

        return $validated;
    }

    /**
     * Attach the aspects this sheet scores, in the order they were chosen.
     */
    protected function syncAspects(Request $request, AssessmentForm $form): void
    {
        if (! $request->has('aspect_ids')) {
            return;
        }

        /** @var array<int, int> $aspectIds */
        $aspectIds = $request->input('aspect_ids', []);

        $links = [];

        foreach (array_values($aspectIds) as $index => $id) {
            $links[(int) $id] = ['sort_order' => $index + 1];
        }

        $form->aspects()->sync($links);
    }

    /**
     * Tidy the submitted values before they are validated.
     *
     * The code is stored as a slug, and blank rows left in the name list are a
     * change of mind rather than an error worth a message about an index the
     * author cannot see.
     */
    protected function normalise(Request $request): void
    {
        if ($request->filled('code')) {
            $request->merge(['code' => Str::slug((string) $request->input('code'))]);
        }

        $this->pruneList($request, 'assessor_options');
    }
}
