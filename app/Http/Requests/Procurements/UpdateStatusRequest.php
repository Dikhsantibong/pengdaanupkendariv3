<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'progress_status_id' => [
                'required',
                Rule::exists('progress_statuses', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'note' => ['nullable', 'string', 'max:500'],
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
            'progress_status_id' => 'status progres',
            'note' => 'catatan',
        ];
    }
}
