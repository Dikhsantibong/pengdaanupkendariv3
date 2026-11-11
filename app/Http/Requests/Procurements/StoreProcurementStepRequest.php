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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Procurement $procurement */
        $procurement = $this->route('procurement');

        return [
            'stage' => ['required', Rule::in(array_keys(ProcurementStepService::STAGES))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_optional' => ['boolean'],
            'after_checklist_id' => [
                'nullable',
                'integer',
                Rule::exists('procurement_checklists', 'id')->where('procurement_id', $procurement->id),
            ],
            // A Penyerahan step is evidenced by files uploaded on it.
            'document_type_id' => ['nullable', 'integer', 'prohibited_if:stage,penyerahan', Rule::exists('document_types', 'id')],
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
            'name' => 'nama tahapan',
            'description' => 'keterangan',
            'is_optional' => 'sifat tahapan',
            'after_checklist_id' => 'posisi tahapan',
            'document_type_id' => 'dokumen',
        ];
    }
}
