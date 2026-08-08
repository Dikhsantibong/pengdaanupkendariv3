<?php

namespace App\Http\Requests\VendorAssessments;

use App\Models\AssessmentForm;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorAssessmentScoresRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The level is nullable so an assessor can leave an aspect unscored, or
     * clear one entered by mistake, and bounded 1-5 because that is the scale
     * printed on the official form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessor_name' => [
                'nullable',
                'string',
                'max:255',
                // A sheet with a fixed list of signatories only accepts a name
                // from that list; the rest stay free text.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $form = $this->route('form');
                    $options = $form instanceof AssessmentForm ? $form->assessor_options : null;

                    if ($options !== null && $options !== [] && ! in_array($value, $options, true)) {
                        $fail('Nama penilai harus dipilih dari daftar yang tersedia.');
                    }
                },
            ],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.aspect_id' => ['required', 'integer'],
            'scores.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'scores.*.note' => ['nullable', 'string', 'max:255'],
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
            'scores.*.note' => 'catatan',
        ];
    }
}
