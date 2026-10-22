<?php

namespace App\Http\Requests\Procurements;

use App\Models\Procurement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractNumberRequest extends FormRequest
{
    /**
     * Who may give the number is decided by the policy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $procurement = $this->route('procurement');

        return [
            'number' => self::numberRules($procurement instanceof Procurement ? $procurement->id : null),
        ];
    }

    /**
     * The rules of a contract number: given, unique among the procurements,
     * and never one of the internal registration numbers.
     *
     * @return array<int, mixed>
     */
    public static function numberRules(?int $procurementId): array
    {
        return [
            'required', 'string', 'max:100',
            'not_regex:#^(PGD|USL)/\d{4}/\d{2}/\d+$#',
            Rule::unique('procurements', 'number')->ignore($procurementId)->whereNull('deleted_at'),
        ];
    }

    /**
     * Get the human readable attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['number' => 'nomor kontrak'];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.not_regex' => 'Isi dengan nomor kontrak, bukan nomor registrasi pengadaan.',
            'number.unique' => 'Nomor kontrak ini sudah dipakai pengadaan lain.',
        ];
    }
}
