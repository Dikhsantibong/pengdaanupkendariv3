<?php

namespace App\Http\Requests\VendorAssessments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorAssessmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'procurement_id' => ['nullable', Rule::exists('procurements', 'id')->whereNull('deleted_at')],
            'project' => ['required', 'string', 'max:255'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'po_date' => ['nullable', 'date'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'form_number' => ['required', 'string', 'max:100'],
            'revision_number' => ['required', 'string', 'max:20'],
            'form_date' => ['nullable', 'date'],
            'place' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'procurement_id' => 'pengadaan',
            'project' => 'project/pekerjaan',
            'po_number' => 'no kontrak',
            'po_date' => 'tanggal kontrak',
            'vendor_name' => 'penyedia barang/jasa',
            'form_number' => 'nomor formulir',
            'revision_number' => 'nomor revisi',
            'form_date' => 'tanggal formulir',
            'place' => 'tempat',
        ];
    }
}
