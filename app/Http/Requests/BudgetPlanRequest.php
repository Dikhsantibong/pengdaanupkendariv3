<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetPlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prk_induk' => ['required', 'string', 'max:100'],
            // The bidang whose proposers may pick this PRK.
            'work_director_id' => ['required', 'integer', Rule::exists('work_directors', 'id')->whereNull('deleted_at')],
            'prk_induk_description' => ['nullable', 'string', 'max:2000'],
            'sub_program_description' => ['nullable', 'string', 'max:2000'],
            'ai_plan_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'aki_plan_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'wo_number' => ['nullable', 'string', 'max:255'],
            'pr_number' => ['nullable', 'string', 'max:255'],
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
            'prk_induk' => 'PRK induk',
            'work_director_id' => 'bidang (direksi pekerjaan)',
            'prk_induk_description' => 'deskripsi PRK induk',
            'sub_program_description' => 'deskripsi sub program',
            'ai_plan_value' => 'rencana AI / nilai RAB',
            'aki_plan_value' => 'rencana AKI',
            'wo_number' => 'nomor WO',
            'pr_number' => 'nomor PR',
        ];
    }
}
