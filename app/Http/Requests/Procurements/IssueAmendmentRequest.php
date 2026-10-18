<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What Laksdan fills in on an agreed amendment: its number (drawn
 * automatically, correctable), its date, the contract's date, and the
 * articles that change: what each said in the contract and what it says now.
 */
class IssueAmendmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('procurement_amendments', 'number')->ignore($this->route('amendment')),
            ],
            'amendment_date' => ['nullable', 'date'],
            'contract_date' => ['nullable', 'date'],
            'changes' => ['present', 'array', 'max:50'],
            'changes.*.article' => ['required', 'string', 'max:255'],
            'changes.*.original_text' => ['required', 'string', 'max:20000'],
            'changes.*.new_text' => ['required', 'string', 'max:20000'],
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
            'changes.*.article' => 'pasal yang diubah',
            'changes.*.original_text' => 'bunyi semula',
            'changes.*.new_text' => 'bunyi menjadi',
        ];
    }
}
