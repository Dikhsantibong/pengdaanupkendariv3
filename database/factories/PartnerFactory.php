<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'CV. '.fake()->unique()->company(),
            'vendor_code' => (string) fake()->numerify('10#####'),
            'director_name' => fake()->name(),
            'address' => fake()->address(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * A partner no longer offered.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
