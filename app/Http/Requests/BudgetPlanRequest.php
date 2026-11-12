<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetPlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'district' => ['nullable', 'string', 'max:50'],
            'prk_induk' => ['required', 'string', 'max:100'],
            // The bidang whose proposers may pick this PRK.
            'work_director_id' => ['required', 'integer', Rule::exists('work_directors', 'id')->whereNull('deleted_at')],
            'prk_induk_description' => ['nullable', 'string', 'max:2000'],
            'sub_program_description' => ['nullable', 'string', 'max:2000'],
            'ai_plan_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'aki_plan_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'wo_number' => ['nullable', 'string', 'max:255'],
            'pr_number' => ['nullable', 'string', 'max:255'],
            // Kontrak.
            'po_stock_type' => ['nullable', 'string', Rule::in(['Stock', 'Non Stock'])],
            'po_number' => ['nullable', 'string', 'max:2000'],
            'allocation' => ['nullable', 'string', Rule::in(['UNIT', 'PJB2'])],
            'po_item' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'po_date' => ['nullable', 'date'],
            'po_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'delivery_date' => ['nullable', 'date'],
            'contract_progress' => ['nullable', 'string', 'max:5000'],
            // Fisik / BA.
            'physical_progress' => ['nullable', 'string', 'max:5000'],
            // Pembayaran.
            'invoice_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'invoice_number' => ['nullable', 'string', 'max:2000'],
            'payment_dates' => ['nullable', 'string', 'max:1000'],
            'payment_proof_number' => ['nullable', 'string', 'max:2000'],
            'payment_progress' => ['nullable', 'string', 'max:2000'],
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
            'district' => 'distrik',
            'prk_induk' => 'PRK induk',
            'work_director_id' => 'bidang (direksi pekerjaan)',
            'prk_induk_description' => 'deskripsi PRK induk',
            'sub_program_description' => 'deskripsi sub program',
            'ai_plan_value' => 'rencana AI / nilai RAB',
            'aki_plan_value' => 'rencana AKI',
            'wo_number' => 'nomor WO',
            'pr_number' => 'nomor PR',
            'po_stock_type' => 'keterangan PO stock / non stock',
            'po_number' => 'nomor PO Ellipse / surat penunjukan / DO / surkes UMRO',
            'allocation' => 'alokasi PJB2 / unit',
            'po_item' => 'item PO Ellipse',
            'supplier' => 'supplier',
            'po_date' => 'tanggal PO',
            'po_value' => 'nilai PO',
            'delivery_date' => 'tanggal levering',
            'contract_progress' => 'keterangan progres kontrak',
            'physical_progress' => 'keterangan progres fisik / BA',
            'invoice_value' => 'nilai invoice',
            'invoice_number' => 'nomor invoice',
            'payment_dates' => 'tanggal bayar',
            'payment_proof_number' => 'nomor bukti bayar',
            'payment_progress' => 'keterangan progres bayar',
        ];
    }
}
