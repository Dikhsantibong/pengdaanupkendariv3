<?php

namespace Database\Factories;

use App\Models\BudgetPlan;
use App\Models\WorkDirector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetPlan>
 */
class BudgetPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ai = fake()->numberBetween(10, 900) * 1_000_000;

        return [
            'prk_induk' => fake()->unique()->numerify('2026.KD.#.##.###'),
            'work_director_id' => WorkDirector::factory(),
            'prk_induk_description' => 'Pemeliharaan '.fake()->words(3, true),
            'sub_program_description' => 'Sub program '.fake()->words(2, true),
            'ai_plan_value' => $ai,
            'aki_plan_value' => (int) round($ai * 0.8),
            'wo_number' => fake()->numerify('WO-######'),
            'pr_number' => fake()->numerify('PR-######'),
        ];
    }
}
