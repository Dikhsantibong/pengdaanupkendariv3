<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\TeamLeaderField;
use App\Enums\UnitManagerLevel;
use App\Models\TargetUnit;
use App\Models\UnitManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitManagerController extends MasterDataController
{
    /**
     * Store a new official along with the units they hold.
     */
    public function store(Request $request): RedirectResponse
    {
        $response = parent::store($request);

        $this->syncUnits($request, UnitManager::query()->latest('id')->firstOrFail());

        return $response;
    }

    /**
     * Update an existing manager.
     */
    public function update(Request $request, UnitManager $unitManager): RedirectResponse
    {
        $response = $this->updateRecord($request, $unitManager);

        $this->syncUnits($request, $unitManager);

        return $response;
    }

    /**
     * Deactivate a manager.
     */
    public function destroy(UnitManager $unitManager): RedirectResponse
    {
        return $this->destroyRecord($unitManager);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/unit-managers';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Manager UP Kendari';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return UnitManager::query()
            ->with('targetUnits')
            ->ordered()
            ->get()
            ->map(fn (UnitManager $record): array => [
                'id' => $record->id,
                'name' => $record->name,
                'position' => $record->position,
                'level' => $record->level->value,
                'field' => $record->field?->value,
                'target_unit_ids' => $record->level->holdsSeveralUnits() ? $record->targetUnits->pluck('id')->all() : [],
                'target_unit_id' => $record->level->holdsOneUnit() && $record->targetUnits->isNotEmpty()
                    ? (string) $record->targetUnits->first()->id
                    : null,
                'unit_names' => $record->targetUnits->pluck('name')->all(),
                'description' => $record->description,
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
            ])
            ->all();
    }

    /**
     * The ranks, bidang and units an official can be given.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'levelOptions' => UnitManagerLevel::options(),
            'fieldOptions' => TeamLeaderField::options(),
            'unitOptions' => TargetUnit::query()
                ->active()
                ->ordered()
                ->get(['id', 'name'])
                ->map(fn (TargetUnit $unit): array => [
                    'value' => (string) $unit->id,
                    'label' => $unit->name,
                ])
                ->all(),
        ];
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new UnitManager;
    }

    /**
     * Get the validation rules for storing or updating a record.
     *
     * @return array<string, mixed>
     */
    protected function rules(?Model $record = null): array
    {
        $unit = Rule::exists('target_units', 'id')->whereNull('deleted_at');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('unit_managers', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'level' => ['required', Rule::enum(UnitManagerLevel::class)],
            'field' => ['nullable', 'required_if:level,'.UnitManagerLevel::Tl->value, Rule::enum(TeamLeaderField::class)],
            'target_unit_ids' => [
                'nullable', 'array',
                'required_if:level,'.UnitManagerLevel::ManagerUl->value.','.UnitManagerLevel::Tl->value,
            ],
            'target_unit_ids.*' => ['integer', $unit],
            'target_unit_id' => ['nullable', 'required_if:level,'.UnitManagerLevel::TlOphar->value, 'integer', $unit],
            'description' => ['nullable', 'string', 'max:255'],
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
            'field.required_if' => 'Pilih bidang yang dipegang TL.',
            'target_unit_ids.required_if' => 'Pilih unit yang dipegang.',
            'target_unit_id.required_if' => 'Pilih satu unit yang dipegang TL OPHAR.',
        ];
    }

    /**
     * Write the jabatan from the rank, its bidang and its unit.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        $level = UnitManagerLevel::from((string) $validated['level']);
        $field = $level === UnitManagerLevel::Tl ? TeamLeaderField::from((string) $validated['field']) : null;
        $unit = $level->holdsOneUnit() ? TargetUnit::query()->find((int) $validated['target_unit_id'])?->name : null;

        $validated['field'] = $field;
        $validated['position'] = UnitManager::positionFor($level, $field, $unit);

        // The units are synced after the save, not filled onto the model.
        unset($validated['target_unit_ids'], $validated['target_unit_id']);

        return $validated;
    }

    /**
     * Link the official to the units their rank holds, in the order chosen.
     */
    protected function syncUnits(Request $request, UnitManager $official): void
    {
        $official->refresh();

        $ids = match (true) {
            $official->level->holdsOneUnit() => [(int) $request->input('target_unit_id')],
            $official->level->holdsSeveralUnits() => array_map('intval', (array) $request->input('target_unit_ids', [])),
            default => [],
        };

        $links = [];

        foreach (array_values(array_unique($ids)) as $index => $id) {
            $links[$id] = ['sort_order' => $index + 1];
        }

        $official->targetUnits()->sync($links);
    }
}
