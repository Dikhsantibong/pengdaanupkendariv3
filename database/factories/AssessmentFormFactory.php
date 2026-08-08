<?php

namespace Database\Factories;

use App\Models\AssessmentForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssessmentForm>
 */
class AssessmentFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(rtrim(fake()->unique()->sentence(2), '.'));

        return [
            'code' => Str::slug($name),
            'name' => $name,
            'assessor_title' => Str::upper($name),
            'assessor_name' => fake()->name(),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the sheet is no longer part of the assessment.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
