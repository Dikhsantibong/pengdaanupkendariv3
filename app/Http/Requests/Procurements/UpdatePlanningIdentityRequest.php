<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanningIdentityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Every field is `sometimes` because each control on the identity panel
     * posts only its own value, and nullable so a value entered by mistake can
     * be cleared again. The contract type is limited to active entries, so a
     * deactivated one cannot be picked while the procurements that already use
     * it keep their record.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contract_type_id' => [
                'sometimes',
                'nullable',
                Rule::exists('contract_types', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'manager_memo_number' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'contract_type_id' => 'jenis kontrak',
            'manager_memo_number' => 'nomor nota dinas manager',
        ];
    }
}
