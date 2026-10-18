<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The proposer's amendment request: the Nota Dinas usulan and its date,
 * and the date of the Notulen. The papers are uploaded on their own.
 */
class UpdateAmendmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'memo_number' => ['nullable', 'string', 'max:255'],
            'memo_date' => ['nullable', 'date'],
            'meeting_date' => ['nullable', 'date'],
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
            'memo_number' => 'nomor nota dinas usulan',
            'memo_date' => 'tanggal nota dinas usulan',
            'meeting_date' => 'tanggal notulen rapat',
        ];
    }
}
