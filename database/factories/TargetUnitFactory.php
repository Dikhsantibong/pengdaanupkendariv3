<?php

namespace Database\Factories;

use App\Models\TargetUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetUnit>
 */
class TargetUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'PLTD '.fake()->unique()->city(),
            'system_name' => null,
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
