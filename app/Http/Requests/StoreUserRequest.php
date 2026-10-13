<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'position' => ['nullable', 'string', 'max:255'],
            'work_director_id' => [Rule::requiredIf(fn (): bool => in_array($this->input('role'), [UserRole::Pengusul->value, UserRole::DireksiPekerjaan->value], true)), 'nullable', 'integer', Rule::exists('work_directors', 'id')->whereNull('deleted_at')],
            'team_leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
            'password' => $this->passwordRules(),
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
            'name' => 'nama',
            'email' => 'alamat email',
            'role' => 'peran',
            'position' => 'jabatan',
            'work_director_id' => 'bidang',
            'team_leader_id' => 'TL / atasan langsung',
            'is_active' => 'status aktif',
            'password' => 'kata sandi',
        ];
    }
}
