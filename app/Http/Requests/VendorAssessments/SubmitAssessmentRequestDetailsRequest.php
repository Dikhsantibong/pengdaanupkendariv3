<?php

namespace App\Http\Requests\VendorAssessments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAssessmentRequestDetailsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project' => ['required', 'string', 'max:255'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'contract_number' => ['required', 'string', 'max:255'],
            'contract_date' => ['required', 'date'],
            'term' => ['required', 'string', 'max:255'],
            'invoice_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'bastp_number' => ['required', 'string', 'max:255'],
            'bastp_date' => ['required', 'date'],
            'has_penalty' => ['required', 'boolean'],
            'submitter_name' => ['required', 'string', 'max:255'],
            'submitter_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
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
            'project' => 'pekerjaan',
            'vendor_name' => 'pelaksana',
            'contract_number' => 'no kontrak/DO',
            'contract_date' => 'tanggal kontrak',
            'term' => 'termin',
            'invoice_amount' => 'nilai tagihan',
            'bastp_number' => 'no BAPP/BASTP',
            'bastp_date' => 'tanggal BAPP/BASTP',
            'has_penalty' => 'denda',
            'submitter_name' => 'nama pengisi',
            'submitter_phone' => 'nomor telepon',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'submitter_phone.regex' => 'Nomor telepon hanya boleh berisi angka.',
        ];
    }
}
