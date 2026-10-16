<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmendmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The numbers and dates of the papers, and the articles that change:
     * each one what it said and what it says now.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $amendment = $this->route('amendment');

        return [
            'number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('procurement_amendments', 'number')->ignore($amendment),
            ],
            'amendment_date' => ['nullable', 'date'],
            'contract_date' => ['nullable', 'date'],
            'meeting_date' => ['nullable', 'date'],
            'memo_number' => ['nullable', 'string', 'max:255'],
            'memo_date' => ['nullable', 'date'],
            'request_number' => ['nullable', 'string', 'max:255'],
            'request_date' => ['nullable', 'date'],
            'changes' => ['present', 'array', 'max:50'],
            'changes.*.article' => ['required', 'string', 'max:255'],
            'changes.*.original_text' => ['required', 'string', 'max:10000'],
            'changes.*.new_text' => ['required', 'string', 'max:10000'],
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
            'number' => 'nomor amandemen',
            'amendment_date' => 'tanggal amandemen',
            'contract_date' => 'tanggal kontrak',
            'meeting_date' => 'tanggal notulen rapat',
            'memo_number' => 'nomor nota dinas amandemen',
            'memo_date' => 'tanggal nota dinas amandemen',
            'request_number' => 'nomor surat permohonan',
            'request_date' => 'tanggal surat permohonan',
            'changes.*.article' => 'pasal yang diubah',
            'changes.*.original_text' => 'bunyi semula',
            'changes.*.new_text' => 'bunyi menjadi',
        ];
    }
}
