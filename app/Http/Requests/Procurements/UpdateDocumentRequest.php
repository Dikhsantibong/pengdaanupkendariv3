<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The body is deliberately unrestricted markup: it is an official document
     * authored by staff of this unit, and it is only ever rendered back to
     * them, never to the public. Trimming tags here would quietly break the
     * page breaks, tables and signature blocks the RKS depends on.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000000'],
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
            'title' => 'judul dokumen',
            'body' => 'isi dokumen',
        ];
    }
}
