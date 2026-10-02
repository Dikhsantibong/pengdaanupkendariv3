<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Services\AccessRights;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccessRightsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grants' => ['present', 'array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['string', Rule::enum(Permission::class)],
        ];
    }

    /**
     * Reject any role the screen never sends, the administrator above all:
     * it always holds every right and is never stored.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = array_map(fn ($role): string => $role->value, AccessRights::configurableRoles());

                foreach (array_keys((array) $this->input('grants', [])) as $role) {
                    if (! in_array($role, $allowed, true)) {
                        $validator->errors()->add('grants', 'Hak akses administrator tidak dapat diubah.');
                    }
                }
            },
        ];
    }
}
