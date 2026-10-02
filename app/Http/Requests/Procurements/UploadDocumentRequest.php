<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Files for a document that is uploaded rather than generated.
 *
 * Same file rules as a signed copy; only document types marked upload-only
 * are accepted, so a generated document cannot be bypassed this way.
 */
class UploadDocumentRequest extends UploadSignedDocumentRequest
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
                'integer',
                Rule::exists('document_types', 'id')
                    ->where('upload_only', true)
                    ->whereNull('deleted_at'),
            ],
            ...parent::rules(),
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
            ...parent::attributes(),
            'document_type_id' => 'jenis dokumen',
            'files' => 'berkas dokumen',
            'files.*' => 'berkas dokumen',
        ];
    }
}
