<?php

namespace App\Http\Requests\Procurements;

use App\Concerns\ProcurementValidationRules;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcurementRequest extends FormRequest
{
    use ProcurementValidationRules;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normaliseTargetUnits();
        $this->normaliseWorkCategory();
        $this->normaliseReferenceNumbers();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The planning PIC may be appointed straight from the create form. It stays
     * optional so a procurement can still be registered before it is known who
     * will plan it, and is only accepted here: later changes go through the
     * appointment screen so the handover is notified and logged.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->procurementRules(),
            // The two documents a proposal files: the signed TOR (drafted
            // from its template) and the Nota Dinas Usulan. Both can also be
            // uploaded afterwards on the proposal page.
            'tor_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'memo_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            // Supporting files from callers that still send them.
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:20480'],
            'planner_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->whereIn('role', [
                        UserRole::PicPerencana->value,
                        UserRole::TeamLeaderIcc->value,
                    ])
                    ->where('is_active', true)
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
            ...$this->procurementAttributes(),
            'planner_id' => 'PIC perencana',
            'tor_file' => 'TOR',
            'memo_file' => 'nota dinas usulan',
            'attachments' => 'lampiran usulan',
            'attachments.*' => 'lampiran usulan',
        ];
    }
}
