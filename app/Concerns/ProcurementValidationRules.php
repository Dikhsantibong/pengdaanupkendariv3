<?php

namespace App\Concerns;

use App\Enums\CsmsRiskLevel;
use App\Enums\Permission;
use App\Enums\WorkCategory;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared by the procurement create and update forms.
 */
trait ProcurementValidationRules
{
    /**
     * The fields that may hold several reference numbers.
     *
     * @var array<int, string>
     */
    protected const REFERENCE_NUMBER_FIELDS = ['prk_number', 'pr_po_number', 'coa_number', 'sr_number', 'wo_number'];

    /**
     * Get the rules for the initial procurement input form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function procurementRules(?int $ignoreId = null): array
    {
        // A work proposal asks only what the division knows; the method and
        // the value are filled in by procurement once it is approved.
        $proposal = $this->isProposalRequest();
        $proposalRequired = $proposal ? 'required' : 'nullable';

        return [
            // SPK, PJ or SPPL is said by whoever registers or proposes the job
            // (the user). Optional, so a caller that does not name one still
            // registers the job; planning then waits for it.
            'contract_number_format_id' => [
                'nullable',
                // The amendment numbering is not a contract format.
                Rule::exists('contract_number_formats', 'id')->whereNull('deleted_at')->where('for_amendment', 0)->where('for_delivery_order', 0),
            ],
            // The category of the formats set up to need it (SPK and PJ),
            // from the Jenis Kontrak master data. Given by the executor with
            // the contract number, so no longer asked when registering.
            'contract_type_id' => [
                'nullable',
                Rule::exists('contract_types', 'id')->whereNull('deleted_at'),
            ],
            // Stated by the user who proposes or registers the job.
            'csms_risk_level' => ['nullable', Rule::enum(CsmsRiskLevel::class)],
            // The TOR's Tanggal Terbit; empty, the TOR takes its generating day.
            'tor_issue_date' => ['nullable', 'date'],
            // Derived from the jenis kontrak: it picks the barang or jasa
            // template, and is never typed in.
            'work_category' => ['nullable', Rule::enum(WorkCategory::class)],
            'number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('procurements', 'number')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'partner_director_name' => ['nullable', 'string', 'max:255'],
            'partner_address' => ['nullable', 'string', 'max:1000'],
            'work_director_id' => ['required', Rule::exists('work_directors', 'id')->whereNull('deleted_at')],
            // A procurement may serve several units; at least one is required.
            'target_unit_ids' => ['required', 'array', 'min:1'],
            'target_unit_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('target_units', 'id')->whereNull('deleted_at'),
            ],
            // No longer on the form: the method follows the contract format
            // (see Procurement::booted). Still accepted from older callers.
            'procurement_method_id' => ['nullable', Rule::exists('procurement_methods', 'id')->whereNull('deleted_at')],
            'budget_source_id' => ['required', Rule::exists('budget_sources', 'id')->whereNull('deleted_at')],
            // Usulan pekerjaan: reference numbers typed by hand. PRK, PR, COA,
            // SR and WO may each hold several numbers, kept as one list.
            'prk_number' => [$proposalRequired, 'string', 'max:2000'],
            'proposal_memo_number' => [$proposalRequired, 'string', 'max:255'],
            'proposal_memo_date' => [$proposalRequired, 'date'],
            'icc_memo_number' => ['nullable', 'string', 'max:255'],
            'icc_memo_date' => ['nullable', 'date'],
            'pr_po_number' => [$proposalRequired, 'string', 'max:2000'],
            'coa_number' => [$proposalRequired, 'string', 'max:2000'],
            'sr_number' => ['nullable', 'string', 'max:2000'],
            'wo_number' => ['nullable', 'string', 'max:2000'],
            'quotation_number' => ['nullable', 'string', 'max:255'],
            'quotation_date' => ['nullable', 'date'],
            'hpe_value' => ['required', 'numeric', 'min:0', 'max:999999999999999999'],
            'value_after_negotiation' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Set by procurement (or by the planning PIC on the detail page),
            // never by the division proposing the work.
            'target_completion_date' => [Rule::excludeIf($proposal), 'sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Whether the author may only propose work rather than register it.
     */
    protected function isProposalRequest(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ! $user->hasPermission(Permission::CreateProcurement)
            && $user->hasPermission(Permission::ProposeWork);
    }

    /**
     * Tidy the reference number lists, and default the value of a proposal.
     *
     * PRK, PR, COA, SR and WO arrive as a list or as text; either way they are
     * stored as one comma separated list without blanks or repeats.
     */
    protected function normaliseReferenceNumbers(): void
    {
        $normalised = [];

        foreach (self::REFERENCE_NUMBER_FIELDS as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            $parts = is_array($value) ? $value : (preg_split('/[,;\n]+/', (string) $value) ?: []);

            $numbers = array_values(array_unique(array_filter(
                array_map(fn (mixed $part): string => trim((string) $part), $parts),
                fn (string $part): bool => $part !== '',
            )));

            $normalised[$field] = $numbers === [] ? null : implode(', ', $numbers);
        }

        // The value is left to procurement, so a proposal may come without it.
        if ($this->isProposalRequest() && $this->input('hpe_value') === null) {
            $normalised['hpe_value'] = 0;
        }

        $this->merge($normalised);
    }

    /**
     * Whether the chosen contract number format asks for barang or jasa.
     */
    protected function formatUsesWorkCategory(): bool
    {
        $formatId = $this->input('contract_number_format_id');

        return $formatId !== null && ContractNumberFormat::query()
            ->whereKey($formatId)
            ->where('uses_work_category', true)
            ->exists();
    }

    /**
     * Derive the document category from the chosen jenis kontrak.
     *
     * A format that does not ask for a category has none. A caller that still
     * sends a bare `work_category` gets the jenis kontrak carrying it.
     */
    protected function normaliseWorkCategory(): void
    {
        if (! $this->formatUsesWorkCategory()) {
            $this->merge(['work_category' => null]);

            return;
        }

        // The form no longer sends the category: the one the executor gave
        // stays as it is.
        if (! $this->has('contract_type_id') && ! $this->filled('work_category')) {
            return;
        }

        if (! $this->filled('contract_type_id') && $this->filled('work_category')) {
            $this->merge([
                'contract_type_id' => ContractType::query()
                    ->active()
                    ->where('work_category', (string) $this->input('work_category'))
                    ->ordered()
                    ->value('id'),
            ]);
        }

        $contractType = $this->filled('contract_type_id')
            ? ContractType::query()->find($this->integer('contract_type_id'))
            : null;

        $this->merge(['work_category' => $contractType?->work_category?->value]);
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
            'contract_type_id' => 'kategori kontrak',
            'csms_risk_level' => 'level risiko CSMS',
            'tor_issue_date' => 'tanggal terbit TOR',
            'work_category' => 'kategori barang/jasa',
            'name' => 'nama pengadaan',
            'partner_name' => 'nama calon mitra',
            'partner_director_name' => 'nama direktur',
            'partner_address' => 'alamat perusahaan',
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
            'pr_po_number' => 'nomor PR',
            'coa_number' => 'nomor COA',
            'sr_number' => 'nomor SR',
            'wo_number' => 'nomor WO',
            'quotation_number' => 'nomor surat penawaran',
            'quotation_date' => 'tanggal surat penawaran',
            'hpe_value' => 'nilai sebelum nego',
            'value_after_negotiation' => 'nilai setelah nego',
            'notes' => 'catatan',
        ];
    }
}
