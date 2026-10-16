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
     * The status is set by the system; only cancelling (Batal) and lifting
     * a cancellation are asked here. A cancellation says why.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancelled' => ['required', 'boolean'],
            'note' => [Rule::requiredIf(fn (): bool => $this->boolean('cancelled')), 'nullable', 'string', 'max:500'],
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
            'cancelled' => 'pembatalan',
            'note' => 'alasan',
        ];
    }
}
