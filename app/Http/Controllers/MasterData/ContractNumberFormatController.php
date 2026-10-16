<?php

namespace App\Http\Controllers\MasterData;

use App\Models\ContractNumberFormat;
use App\Models\ProcurementMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContractNumberFormatController extends MasterDataController
{
    /**
     * Store a new contract number format.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalise($request);

        return parent::store($request);
    }

    /**
     * Update an existing contract number format.
     */
    public function update(Request $request, ContractNumberFormat $contractNumberFormat): RedirectResponse
    {
        $this->normalise($request);

        return $this->updateRecord($request, $contractNumberFormat);
    }

    /**
     * Deactivate a contract number format.
     */
    public function destroy(ContractNumberFormat $contractNumberFormat): RedirectResponse
    {
        return $this->destroyRecord($contractNumberFormat);
    }

    /**
     * The Inertia page that renders this resource.
     */
    protected function page(): string
    {
        return 'master-data/contract-number-formats';
    }

    /**
     * The human readable singular label of this resource.
     */
    protected function label(): string
    {
        return 'Format no kontrak';
    }

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        return ContractNumberFormat::query()
            ->with('procurementMethod')
            ->withCount('procurements')
            ->ordered()
            ->get()
            ->map(fn (ContractNumberFormat $record): array => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'prefix' => $record->prefix,
                'unit_segment' => $record->unit_segment,
                'sequence_length' => $record->sequence_length,
                'starting_sequence' => $record->starting_sequence,
                'uses_work_category' => $record->uses_work_category,
                'for_amendment' => $record->for_amendment,
                // "none" is the select's way of saying no method.
                'procurement_method_id' => $record->procurement_method_id === null ? 'none' : (string) $record->procurement_method_id,
                'procurement_method' => $record->procurementMethod?->name,
                'description' => $record->description,
                'sample' => $record->sample(),
                'sort_order' => $record->sort_order,
                'is_active' => $record->is_active,
                'usage_count' => $record->procurements_count,
            ])
            ->all();
    }

    /**
     * The options the form offers.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'procurementMethods' => [
                ['value' => 'none', 'label' => 'Tidak ditentukan'],
                ...ProcurementMethod::query()->active()->ordered()->get()
                    ->map(fn (ProcurementMethod $method): array => [
                        'value' => (string) $method->id,
                        'label' => $method->name,
                    ])->all(),
            ],
        ];
    }

    /**
     * Create a new empty record for this resource.
     */
    protected function newRecord(): Model
    {
        return new ContractNumberFormat;
    }

    /**
     * Get the validation rules for storing or updating a record.
     *
     * @return array<string, mixed>
     */
    protected function rules(?Model $record = null): array
    {
        return [
            'code' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('contract_number_formats', 'code')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('contract_number_formats', 'name')->ignore($record?->getKey())->whereNull('deleted_at'),
            ],
            'prefix' => ['required', 'string', 'max:20'],
            // Slashes are part of the shape, so this one is deliberately not
            // an alpha_dash field.
            'unit_segment' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\/.-]+$/'],
            'sequence_length' => ['required', 'integer', 'min:1', 'max:8'],
            'starting_sequence' => ['required', 'integer', 'min:1', 'max:99999999'],
            // Whether procurements on this format choose barang or jasa.
            'uses_work_category' => ['sometimes', 'boolean'],
            // Numbers amendments (Amd) rather than contracts.
            'for_amendment' => ['sometimes', 'boolean'],
            // The method a procurement on this format is let by; it replaces
            // the method once asked on the procurement form.
            'procurement_method_id' => ['sometimes', 'nullable', Rule::exists('procurement_methods', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'unit_segment.regex' => 'Ruas unit hanya boleh berisi huruf, angka, garis miring, titik dan strip.',
        ];
    }

    /**
     * Tidy the submitted values before they are validated.
     *
     * The code is printed inside the number itself, so it is upper cased the
     * way SPK and PJ appear on the contract.
     */
    protected function normalise(Request $request): void
    {
        // An amendment code keeps its case (KDD054.Amd/612/UPKD/2026).
        $fields = $request->boolean('for_amendment') ? ['prefix'] : ['code', 'prefix'];

        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => Str::upper(trim((string) $request->input($field)))]);
            }
        }

        if ($request->input('procurement_method_id') === 'none') {
            $request->merge(['procurement_method_id' => null]);
        }

        if ($request->filled('unit_segment')) {
            $request->merge([
                'unit_segment' => trim((string) $request->input('unit_segment'), " \t\n\r\0\x0B/"),
            ]);
        }
    }
}
