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
     * Whether the step belongs to the stage and is missing from the
     * procurement is checked by ProcurementStepService::add().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::in(array_keys(ProcurementStepService::STAGES))],
            'checklist_item_id' => [
                'required',
                'integer',
                Rule::exists('checklist_items', 'id')->whereNull('procurement_id')->whereNull('deleted_at'),
            ],
        ];
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
        ];
    }
}
