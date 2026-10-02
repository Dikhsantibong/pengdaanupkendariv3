<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type_id' => [
                'required',
                // Upload-only documents are filed, never generated. Written as
                // 0, not false: the rule stringifies its values and false would
                // become an empty string that matches nothing.
                Rule::exists('document_types', 'id')
                    ->where('is_active', true)
                    ->where('upload_only', 0)
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
            'document_type_id' => 'jenis dokumen',
        ];
    }
}
