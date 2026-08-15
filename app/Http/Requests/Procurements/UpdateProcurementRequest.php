<?php

namespace App\Http\Requests\Procurements;

use App\Concerns\ProcurementValidationRules;
use App\Models\Procurement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProcurementRequest extends FormRequest
{
    use ProcurementValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $procurement = $this->route('procurement');

        return $this->procurementRules(
            $procurement instanceof Procurement ? $procurement->getKey() : null,
        );
    }

    /**
     * Get the human readable attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->procurementAttributes();
    }
}
