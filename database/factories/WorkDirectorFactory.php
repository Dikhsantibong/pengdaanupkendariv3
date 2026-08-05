<?php

namespace Database\Factories;

use App\Models\WorkDirector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkDirector>
 */
class WorkDirectorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Asman '.fake()->unique()->word(),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the record is no longer selectable.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
