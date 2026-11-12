<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BudgetPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row of the budget plan (Perencanaan Anggaran) kept by the Anggaran
 * role: a PRK with its planned AI (Nilai RAB) and AKI, and its WO and PR.
 *
 * @property int $id
 * @property string|null $district
 * @property string $prk_induk
 * @property int|null $work_director_id
 * @property string|null $prk_induk_description
 * @property string|null $sub_program_description
 * @property string|null $ai_plan_value
 * @property string|null $aki_plan_value
 * @property string|null $wo_number
 * @property string|null $pr_number
 * @property string|null $po_stock_type
 * @property string|null $po_number
 * @property string|null $allocation
 * @property string|null $po_item
 * @property string|null $supplier
 * @property CarbonImmutable|null $po_date
 * @property string|null $po_value
 * @property CarbonImmutable|null $delivery_date
 * @property string|null $contract_progress
 * @property string|null $physical_progress
 * @property string|null $invoice_value
 * @property string|null $invoice_number
 * @property string|null $payment_dates
 * @property string|null $payment_proof_number
 * @property string|null $payment_progress
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WorkDirector|null $workDirector
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
#[Fillable([
    'district',
    'prk_induk',
    'work_director_id',
    'prk_induk_description',
    'sub_program_description',
    'ai_plan_value',
    'aki_plan_value',
    'wo_number',
    'pr_number',
    'po_stock_type',
    'po_number',
    'allocation',
    'po_item',
    'supplier',
    'po_date',
    'po_value',
    'delivery_date',
    'contract_progress',
    'physical_progress',
    'invoice_value',
    'invoice_number',
    'payment_dates',
    'payment_proof_number',
    'payment_progress',
    'created_by',
    'updated_by',
])]
class BudgetPlan extends Model
{
    /** @use HasFactory<BudgetPlanFactory> */
    use HasFactory;

    /**
     * The bidang (Direksi Pekerjaan) the PRK is meant for.
     *
     * @return BelongsTo<WorkDirector, $this>
     */
    public function workDirector(): BelongsTo
    {
        return $this->belongsTo(WorkDirector::class);
    }

    /**
     * The PRK offered on the work proposal form, each with the bidang it is
     * meant for and the WO and PR it carries.
     *
     * @return array<int, array{value: string, label: string, description: string|null, workDirectorId: int|null, woNumber: string|null, prNumber: string|null, programName: string|null, subProgram: string|null, aiValue: float|null}>
     */
    public static function proposalOptions(): array
    {
        return static::query()
            ->orderBy('prk_induk')
            ->orderBy('id')
            ->get()
            ->map(fn (BudgetPlan $plan): array => [
                'value' => $plan->prk_induk,
                'label' => $plan->prk_induk,
                'description' => $plan->sub_program_description ?: $plan->prk_induk_description,
                'workDirectorId' => $plan->work_director_id,
                'woNumber' => $plan->wo_number,
                'prNumber' => $plan->pr_number,
                // Filled into the proposal when the PRK is picked.
                'programName' => $plan->prk_induk_description,
                'subProgram' => $plan->sub_program_description,
                'aiValue' => $plan->ai_plan_value === null ? null : (float) $plan->ai_plan_value,
            ])
            ->all();
    }

    /**
     * Who entered the row.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who changed the row last.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_plan_value' => 'decimal:2',
            'aki_plan_value' => 'decimal:2',
            'po_value' => 'decimal:2',
            'invoice_value' => 'decimal:2',
            'po_date' => 'date',
            'delivery_date' => 'date',
        ];
    }
}
