<?php

namespace App\Models;

use App\Enums\BudgetType;
use Carbon\CarbonImmutable;
use Database\Factories\BudgetPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One row of the budget plan (Perencanaan Anggaran) kept by the Anggaran
 * role: a PRK with its planned AI (Nilai RAB) and AKI, or AO (Pagu & Realisasi),
 * and its WO and PR.
 *
 * @property int $id
 * @property string|null $district
 * @property BudgetType $budget_type
 * @property string $prk_induk
 * @property int|null $work_director_id
 * @property string|null $prk_induk_description
 * @property string|null $sub_program_description
 * @property string|null $pagu_value
 * @property string|null $remaining_pagu_value
 * @property string|null $disburse_plan
 * @property string|null $realization_value
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
 * @property string|null $notes
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
    'budget_type',
    'prk_induk',
    'work_director_id',
    'prk_induk_description',
    'sub_program_description',
    'pagu_value',
    'remaining_pagu_value',
    'disburse_plan',
    'realization_value',
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
    'notes',
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

    protected static function booted(): void
    {
        static::saving(function (BudgetPlan $plan): void {
            if ($plan->budget_type !== BudgetType::Investasi) {
                if ($plan->pagu_value !== null || $plan->realization_value !== null) {
                    $pagu = (float) ($plan->pagu_value ?? 0);
                    $realisasi = (float) ($plan->realization_value ?? 0);
                    $plan->remaining_pagu_value = (string) ($pagu - $realisasi);
                }
            }
        });
    }

    /**
     * The PRK offered on the work proposal form, each with the bidang it is
     * meant for and the WO and PR it carries.
     *
     * @return array<int, array{value: string, label: string, description: string|null, workDirectorId: int|null, woNumber: string|null, prNumber: string|null, programName: string|null, subProgram: string|null, aiValue: float|null, budgetType: string}>
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
                'aiValue' => $plan->ai_plan_value !== null
                    ? (float) $plan->ai_plan_value
                    : ($plan->pagu_value !== null ? (float) $plan->pagu_value : null),
                'budgetType' => $plan->budget_type->value,
            ])
            ->all();
    }

    /**
     * The PRK of a division that no active procurement or proposal has
     * taken up yet: waiting on the division's TL and staff to follow up
     * and propose.
     *
     * @return Collection<int, self>
     */
    public static function unproposedFor(int $workDirectorId): Collection
    {
        $usedPrks = Procurement::query()
            ->where('work_director_id', $workDirectorId)
            ->whereNotNull('prk_number')
            ->whereDoesntHave('progressStatus', fn ($status) => $status->where('system_key', 'batal'))
            ->pluck('prk_number')
            ->flatMap(fn (string $numbers): array => preg_split('/[,;\n]+/', $numbers) ?: [])
            ->map(fn (string $number): string => mb_strtolower(trim($number)))
            ->filter()
            ->unique()
            ->flip();

        return static::query()
            ->where('work_director_id', $workDirectorId)
            ->with('workDirector:id,name')
            ->orderBy('prk_induk')
            ->orderBy('id')
            ->get()
            ->reject(fn (self $plan): bool => $usedPrks->has(mb_strtolower(trim($plan->prk_induk))))
            ->values();
    }

    /**
     * Format the unproposed PRK of a division for the dashboard.
     *
     * @return list<array{id: int, prk_induk: string, budget_type: string, budget_type_label: string, description: string, division: string|null, planned_value: float, wo_number: string|null, pr_number: string|null}>
     */
    public static function dashboardPayload(int $workDirectorId): array
    {
        return static::unproposedFor($workDirectorId)
            ->map(fn (self $plan): array => [
                'id' => $plan->id,
                'prk_induk' => $plan->prk_induk,
                'budget_type' => $plan->budget_type->value,
                'budget_type_label' => $plan->budget_type->label(),
                'description' => $plan->description(),
                'division' => $plan->workDirector?->name,
                'planned_value' => $plan->plannedValue(),
                'wo_number' => $plan->wo_number,
                'pr_number' => $plan->pr_number,
            ])
            ->values()
            ->all();
    }

    /**
     * Human-readable program / sub-program description of the PRK.
     */
    public function description(): string
    {
        $parts = array_values(array_filter(
            array_map(fn (?string $part): string => trim((string) $part), [$this->prk_induk_description, $this->sub_program_description]),
            fn (string $part): bool => $part !== '',
        ));

        return $parts !== [] ? implode(' — ', $parts) : $this->prk_induk;
    }

    /**
     * Planned value (AI / RAB for Investasi, or Pagu for Operasi / Energi Primer).
     */
    public function plannedValue(): float
    {
        if ($this->ai_plan_value !== null) {
            return (float) $this->ai_plan_value;
        }

        return $this->pagu_value !== null ? (float) $this->pagu_value : 0.0;
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
            'budget_type' => BudgetType::class,
            'pagu_value' => 'decimal:2',
            'remaining_pagu_value' => 'decimal:2',
            'realization_value' => 'decimal:2',
            'ai_plan_value' => 'decimal:2',
            'aki_plan_value' => 'decimal:2',
            'po_value' => 'decimal:2',
            'invoice_value' => 'decimal:2',
            'po_date' => 'date',
            'delivery_date' => 'date',
        ];
    }
}
