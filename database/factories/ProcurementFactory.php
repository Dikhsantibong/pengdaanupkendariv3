<?php

namespace Database\Factories;

use App\Enums\PlanningApprovalState;
use App\Models\BudgetSource;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procurement>
 */
class ProcurementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PGD/'.fake()->unique()->numerify('######'),
            'name' => 'Pengadaan '.rtrim(fake()->sentence(3), '.'),
            'work_director_id' => WorkDirector::factory(),
            'target_unit_id' => TargetUnit::factory(),
            'procurement_method_id' => ProcurementMethod::factory(),
            'budget_source_id' => BudgetSource::factory(),
            'pr_ro_number_id' => null,
            'prk_number' => fake()->numerify('ND-###/PRK/####'),
            'hpe_value' => fake()->numberBetween(10_000_000, 5_000_000_000),
            'progress_status_id' => ProgressStatus::factory(),
            'planner_id' => null,
            'executor_id' => null,
            'planning_approval_state' => PlanningApprovalState::BelumDiajukan,
            'notes' => null,
            'created_by' => null,
        ];
    }

    /**
     * Assign the planning PIC of the procurement.
     */
    public function plannedBy(User $planner): static
    {
        return $this->state(fn (array $attributes) => [
            'planner_id' => $planner->id,
        ]);
    }

    /**
     * Assign the execution PIC of the procurement.
     */
    public function executedBy(User $executor): static
    {
        return $this->state(fn (array $attributes) => [
            'executor_id' => $executor->id,
        ]);
    }

    /**
     * Indicate that the planning documents have been approved.
     */
    public function planningApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'planning_submitted_at' => now()->subDay(),
            'planning_reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the planning documents await team leader review.
     */
    public function planningSubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'planning_approval_state' => PlanningApprovalState::MenungguPersetujuan,
            'planning_submitted_at' => now(),
        ]);
    }
}
