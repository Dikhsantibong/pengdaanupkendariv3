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
    protected function procurementRules(?int $ignoreId = null): array
    {
        return [
            // Both stay optional so a procurement can still be registered from
            // a caller that does not number it; the service then falls back to
            // the internal number it always used.
            'contract_number_format_id' => [
                'nullable',
                Rule::exists('contract_number_formats', 'id')->whereNull('deleted_at'),
            ],
            'number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('procurements', 'number')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'work_director_id' => ['required', Rule::exists('work_directors', 'id')->whereNull('deleted_at')],
            // A procurement may serve several units; at least one is required.
            'target_unit_ids' => ['required', 'array', 'min:1'],
            'target_unit_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('target_units', 'id')->whereNull('deleted_at'),
            ],
            'procurement_method_id' => ['required', Rule::exists('procurement_methods', 'id')->whereNull('deleted_at')],
            'budget_source_id' => ['required', Rule::exists('budget_sources', 'id')->whereNull('deleted_at')],
            // Usulan pekerjaan: reference numbers typed by hand, all optional.
            'prk_number' => ['nullable', 'string', 'max:255'],
            'proposal_memo_number' => ['nullable', 'string', 'max:255'],
            'proposal_memo_date' => ['nullable', 'date'],
            'icc_memo_number' => ['nullable', 'string', 'max:255'],
            'icc_memo_date' => ['nullable', 'date'],
            'pr_po_number' => ['nullable', 'string', 'max:255'],
            'coa_number' => ['nullable', 'string', 'max:255'],
            'wo_number' => ['nullable', 'string', 'max:255'],
            'quotation_number' => ['nullable', 'string', 'max:255'],
            'quotation_date' => ['nullable', 'date'],
            'hpe_value' => ['required', 'numeric', 'min:0', 'max:999999999999999999'],
            'value_after_negotiation' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'progress_status_id' => ['required', Rule::exists('progress_statuses', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Accept a single `target_unit_id` from callers that predate the unit list.
     *
     * The form sends `target_unit_ids`; an older caller sending one unit is
     * treated as a list of one rather than rejected.
     */
    protected function normaliseTargetUnits(): void
    {
        if ($this->has('target_unit_ids') || ! $this->filled('target_unit_id')) {
            return;
        }

        $this->merge(['target_unit_ids' => [$this->input('target_unit_id')]]);
    }

    /**
     * Get the human readable attribute names for procurement fields.
     *
     * @return array<string, string>
     */
    protected function procurementAttributes(): array
    {
        return [
            'contract_number_format_id' => 'jenis no kontrak',
            'number' => 'no kontrak',
            'name' => 'nama pengadaan',
            'partner_name' => 'nama mitra/pelaksana',
            'work_director_id' => 'direksi pekerjaan',
            'target_unit_ids' => 'unit tujuan',
            'target_unit_ids.*' => 'unit tujuan',
            'procurement_method_id' => 'metode pengadaan',
            'budget_source_id' => 'sumber anggaran',
            'prk_number' => 'nomor PRK',
            'proposal_memo_number' => 'nomor nota dinas usulan',
            'proposal_memo_date' => 'tanggal nota dinas usulan',
            'icc_memo_number' => 'nomor nota dinas ke manager',
            'icc_memo_date' => 'tanggal nota dinas ke manager',
            'pr_po_number' => 'nomor PR/PO',
            'coa_number' => 'nomor COA',
            'wo_number' => 'nomor WO',
            'quotation_number' => 'nomor surat penawaran',
            'quotation_date' => 'tanggal surat penawaran',
            'hpe_value' => 'nilai sebelum nego',
            'value_after_negotiation' => 'nilai setelah nego',
            'progress_status_id' => 'status progres',
            'notes' => 'catatan',
        ];
    }
}
