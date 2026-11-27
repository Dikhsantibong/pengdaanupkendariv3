<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\CoaSegment;
use App\Models\CoaSegmentOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CoaSegmentOptionController extends MasterDataController
{
    /**
     * Update an existing COA segment option.
     */
    public function update(Request $request, CoaSegmentOption $coaSegment): RedirectResponse
    {
        return $this->updateRecord($request, $coaSegment);
    }

    /**
     * Deactivate (soft-delete) a COA segment option.
     */
    public function destroy(CoaSegmentOption $coaSegment): RedirectResponse
    {
        return $this->destroyRecord($coaSegment);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/coa-segments';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Opsi Segmen COA';
    }

    /**
     * Extra props sent to the Inertia page.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'segments' => CoaSegment::options(),
        ];
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        $segmentOrder = array_flip(array_map(
            fn (CoaSegment $segment): string => $segment->value,
            CoaSegment::cases(),
        ));

        return CoaSegmentOption::query()
            ->ordered()
            ->get()
            ->sortBy(fn (CoaSegmentOption $record): array => [
                $segmentOrder[$record->segment->value] ?? 99,
                $record->sort_order,
                $record->code,
            ])
            ->values()
            ->map(fn (CoaSegmentOption $record): array => [
                'id' => $record->id,
                'segment' => $record->segment->value,
                'segment_label' => $record->segment->label(),
                'segment_short_label' => $record->segment->shortLabel(),
                'code' => $record->code,
                'name' => $record->name,
                'description' => $record->description,
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
        return new CoaSegmentOption;
    }

    /**
     * Get the validation rules for storing or updating a record.
     *
     * @return array<string, mixed>
     */
    protected function rules(?Model $record = null): array
    {
        $segment = (string) request()->input('segment', $record instanceof CoaSegmentOption ? $record->segment->value : '');

        return [
            'segment' => ['required', Rule::enum(CoaSegment::class)],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9\/._-]+$/',
                Rule::unique('coa_segment_options', 'code')
                    ->where('segment', $segment)
                    ->ignore($record?->getKey())
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Normalize code to uppercase before saving.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        $validated['code'] = Str::upper(trim((string) $validated['code']));
        $validated['name'] = trim((string) $validated['name']);

        return $validated;
    }
}
