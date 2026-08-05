<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared by the procurement create and update forms.
 */
trait ProcurementValidationRules
{
    /**
     * Get the rules for the initial procurement input form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function procurementRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'work_director_id' => ['required', Rule::exists('work_directors', 'id')->whereNull('deleted_at')],
            'target_unit_id' => ['required', Rule::exists('target_units', 'id')->whereNull('deleted_at')],
            'procurement_method_id' => ['required', Rule::exists('procurement_methods', 'id')->whereNull('deleted_at')],
            'budget_source_id' => ['required', Rule::exists('budget_sources', 'id')->whereNull('deleted_at')],
            'pr_ro_number_id' => ['nullable', Rule::exists('pr_ro_numbers', 'id')->whereNull('deleted_at')],
            'prk_number' => ['nullable', 'string', 'max:255'],
            'hpe_value' => ['required', 'numeric', 'min:0', 'max:999999999999999999'],
            'progress_status_id' => ['required', Rule::exists('progress_statuses', 'id')->whereNull('deleted_at')],
            'target_completion_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get the human readable attribute names for procurement fields.
     *
     * @return array<string, string>
     */
    protected function procurementAttributes(): array
    {
        return [
            'name' => 'nama pengadaan',
            'work_director_id' => 'direksi pekerjaan',
            'target_unit_id' => 'unit tujuan',
            'procurement_method_id' => 'metode pengadaan',
            'budget_source_id' => 'sumber anggaran',
            'pr_ro_number_id' => 'nomor PR/RO',
            'prk_number' => 'nomor PRK',
            'hpe_value' => 'nilai HPE/anggaran',
            'progress_status_id' => 'status progres',
            'target_completion_date' => 'target penyelesaian',
            'notes' => 'catatan',
        ];
    }
}
