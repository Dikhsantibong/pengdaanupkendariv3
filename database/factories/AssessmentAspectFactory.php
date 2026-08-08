<?php

namespace Database\Factories;

use App\Models\AssessmentAspect;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssessmentAspect>
 */
class AssessmentAspectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'ASPEK '.Str::upper(fake()->unique()->word());

        return [
            'code' => Str::slug($name),
            'name' => $name,
            'preamble' => null,
            'indicators' => [
                rtrim(fake()->sentence(4), '.'),
                rtrim(fake()->sentence(4), '.'),
            ],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the aspect is no longer used on the form.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
