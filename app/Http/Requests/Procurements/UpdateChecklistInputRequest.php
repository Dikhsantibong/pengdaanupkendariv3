<?php

namespace App\Http\Requests\Procurements;

use App\Enums\ChecklistInput;
use App\Models\ProcurementChecklist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The data a checklist step asks for.
 *
 * Only the fields of the step's own input kind are accepted, so saving one
 * step can never overwrite the data of another.
 */
class UpdateChecklistInputRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $checklist = $this->route('checklist');
        $kind = $checklist instanceof ProcurementChecklist ? $checklist->checklistItem->input_kind : null;

        return match ($kind) {
            ChecklistInput::ContractPeriod => [
                'execution_start_date' => ['required', 'date'],
                'execution_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            ],
            ChecklistInput::Warranty => [
                'warranty_months' => ['required', 'integer', 'min:0', 'max:120'],
            ],
            ChecklistInput::BankAccount => [
                // One of this procurement's own candidates.
                'partner_candidate_id' => [
                    'required', 'integer',
                    Rule::exists('procurement_partner_candidates', 'id')->where('procurement_id', $checklist->procurement_id),
                ],
                'bank_account_number' => ['required', 'string', 'max:50', 'regex:/^[0-9 .\-]+$/'],
                'bank_name' => ['required', 'string', 'max:100'],
                'bank_account_holder' => ['required', 'string', 'max:255'],
            ],
            ChecklistInput::ProcurementMemo => [
                'icc_memo_number' => ['required', 'string', 'max:255'],
                'icc_memo_date' => ['required', 'date'],
            ],
            // Given in the identity now; kept here for a step still asking.
            ChecklistInput::ContractNumber => [
                'number' => UpdateContractNumberRequest::numberRules($checklist->procurement_id),
            ],
            ChecklistInput::RksNumber => ['rks_number' => ['required', 'string', 'max:255']],
            ChecklistInput::HpeNumber => ['hpe_number' => ['required', 'string', 'max:255']],
            ChecklistInput::UpbNumber => ['upb_number' => ['required', 'string', 'max:255']],
            // Filled through the calon mitra themselves.
            ChecklistInput::PartnerCandidates, null => [],
        };
    }

    /**
     * Get the human readable attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'execution_start_date' => 'tanggal mulai',
            'execution_duration_days' => 'jumlah hari',
            'warranty_months' => 'masa garansi',
            'partner_candidate_id' => 'pelaksana',
            'bank_account_number' => 'nomor rekening',
            'bank_name' => 'nama bank',
            'bank_account_holder' => 'nama pemilik rekening',
            'icc_memo_number' => 'nomor nota dinas ke pengadaan',
            'icc_memo_date' => 'tanggal nota dinas ke pengadaan',
            'number' => 'nomor kontrak',
            'rks_number' => 'nomor RKS',
            'hpe_number' => 'nomor HPE',
            'upb_number' => 'nomor UPB',
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
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
            'number.not_regex' => 'Isi dengan nomor kontrak, bukan nomor registrasi pengadaan.',
        ];
    }
}
