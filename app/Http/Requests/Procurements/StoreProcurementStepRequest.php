<?php

namespace App\Http\Requests\Procurements;

use App\Models\Procurement;
use App\Services\ProcurementStepService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcurementStepRequest extends FormRequest
{
    /**
     * Only those who may adjust the stage's steps, checked before the data,
     * so a refused user is told so rather than shown field errors.
     */
    public function authorize(): bool
    {
        $procurement = $this->route('procurement');

        return $procurement instanceof Procurement
            && ($this->user()?->can('manageSteps', [$procurement, (string) $this->input('stage')]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Perencanaan and Pelaksanaan pick a Data Master step; Penyerahan and
     * Pembayaran take a name typed by hand. Whether the step belongs to the
     * stage and is missing from the procurement is checked by
     * ProcurementStepService.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $typed = 'exclude_unless:stage,'.implode(',', ProcurementStepService::TYPED_STAGES);

        return [
            'stage' => ['required', Rule::in(array_keys(ProcurementStepService::STAGES))],
            'checklist_item_id' => [
                Rule::excludeIf($this->typesItsSteps()),
                'required',
                'integer',
                Rule::exists('checklist_items', 'id')->whereNull('procurement_id')->whereNull('deleted_at'),
            ],
            'name' => [$typed, 'required', 'string', 'max:255'],
            'description' => [$typed, 'nullable', 'string', 'max:1000'],
            'is_optional' => [$typed, 'boolean'],
        ];
    }

    /**
     * Whether the stage asked for takes its new steps typed by hand.
     */
    public function typesItsSteps(): bool
    {
        return in_array((string) $this->input('stage'), ProcurementStepService::TYPED_STAGES, true);
    }

    /**
     * Get the human readable attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stage' => 'tahap',
            'checklist_item_id' => 'tahapan',
            'name' => 'nama tahapan',
            'description' => 'keterangan',
        ];
    }
}
