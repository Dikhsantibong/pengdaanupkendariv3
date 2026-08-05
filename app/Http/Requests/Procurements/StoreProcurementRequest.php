<?php

namespace App\Http\Requests\Procurements;

use App\Concerns\ProcurementValidationRules;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcurementRequest extends FormRequest
{
    use ProcurementValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The planning PIC may be appointed straight from the create form. It stays
     * optional so a procurement can still be registered before it is known who
     * will plan it, and is only accepted here: later changes go through the
     * appointment screen so the handover is notified and logged.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->procurementRules(),
            'planner_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::PicPerencana->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
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
            ...$this->procurementAttributes(),
            'planner_id' => 'PIC perencana',
        ];
    }
}
