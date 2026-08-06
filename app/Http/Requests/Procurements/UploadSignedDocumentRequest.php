<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadSignedDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Several files at once, because a signed berita acara is usually scanned
     * in batches or photographed page by page. Each one comes back either as a
     * scanned PDF or as an image, and the type is checked by its real contents
     * rather than by the extension the browser reports.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(20 * 1024),
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
            'files' => 'berkas dokumen bertanda tangan',
            'files.*' => 'berkas dokumen bertanda tangan',
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
            'files.required' => 'Pilih minimal satu berkas untuk diunggah.',
            'files.max' => 'Maksimal 20 berkas dalam satu kali unggah.',
            'files.*.max' => 'Ukuran setiap berkas maksimal 20 MB.',
            'files.*.mimes' => 'Setiap berkas harus berformat PDF, JPG, atau PNG.',
        ];
    }
}
