<?php

namespace App\Models;

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
 * @property string $prk_induk
 * @property int|null $work_director_id
 * @property string|null $prk_induk_description
 * @property string|null $sub_program_description
 * @property string|null $ai_plan_value
 * @property string|null $aki_plan_value
 * @property string|null $wo_number
 * @property string|null $pr_number
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WorkDirector|null $workDirector
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
#[Fillable([
    'prk_induk',
    'work_director_id',
    'prk_induk_description',
    'sub_program_description',
    'ai_plan_value',
    'aki_plan_value',
    'wo_number',
    'pr_number',
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
     * @return array<int, array{value: string, label: string, description: string|null, workDirectorId: int|null, woNumber: string|null, prNumber: string|null}>
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
        ];
    }
}
