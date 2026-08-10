<?php

namespace App\Http\Requests\VendorAssessments;

use App\Models\VendorAssessmentInvitation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitSignedAssessmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Every level is required here, unlike the internal screen: the assessor is
     * signing the sheet, so it has to be complete. The signature is a PNG data
     * URI drawn on the page and is bounded, because this endpoint takes a body
     * from an unauthenticated caller.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessor_name' => [
                'required',
                'string',
                'max:255',
            ],
            'scores' => ['required', 'array', 'min:1', 'max:50'],
            'scores.*.aspect_id' => ['required', 'integer'],
            'scores.*.level' => ['required', 'integer', 'min:1', 'max:5'],
            'signature' => ['required', 'string', 'max:2000000', 'starts_with:data:image/png;base64,'],
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
            'assessor_name' => 'nama penilai',
            'scores.*.level' => 'nilai',
            'signature' => 'tanda tangan',
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
            'scores.*.level.required' => 'Seluruh aspek harus dinilai sebelum dikirim.',
            'signature.required' => 'Tanda tangan belum dibubuhkan.',
            'signature.starts_with' => 'Tanda tangan tidak terbaca. Silakan ulangi.',
        ];
    }

    /**
     * The link this submission belongs to.
     */
    protected function invitation(): ?VendorAssessmentInvitation
    {
        $token = $this->route('token');

        if (! is_string($token)) {
            return null;
        }

        return VendorAssessmentInvitation::query()->where('token', $token)->first();
    }
}
