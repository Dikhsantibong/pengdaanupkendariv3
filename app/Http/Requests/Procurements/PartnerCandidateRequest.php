<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PartnerCandidateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vendor_code' => ['nullable', 'string', 'max:100'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'quotation_number' => ['nullable', 'string', 'max:255'],
            'quotation_date' => ['nullable', 'date'],
            // The offer's total before negotiation, and its scan.
            'quotation_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'quotation_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
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
            'name' => 'nama calon mitra',
            'vendor_code' => 'kode rekanan',
            'director_name' => 'nama direktur',
            'address' => 'alamat perusahaan',
            'quotation_number' => 'nomor surat penawaran',
            'quotation_date' => 'tanggal surat penawaran',
            'quotation_value' => 'nilai total penawaran',
            'quotation_file' => 'berkas penawaran harga',
        ];
    }
}
