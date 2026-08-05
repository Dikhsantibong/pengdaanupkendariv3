<?php

namespace App\Http\Requests\Procurements;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignPicRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'planner_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::PicPerencana->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'executor_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::PicPelaksana->value)
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
            'planner_id' => 'PIC perencana',
            'executor_id' => 'PIC pelaksana',
        ];
    }
}
