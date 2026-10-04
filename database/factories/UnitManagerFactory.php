<?php

namespace Database\Factories;

use App\Models\UnitManager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitManager>
 */
class UnitManagerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => 'MANAGER',
            'description' => 'Manager PT PLN Nusantara Power UP Kendari',
            'sort_order' => 1,
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
