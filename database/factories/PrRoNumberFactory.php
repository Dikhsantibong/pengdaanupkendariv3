<?php

namespace Database\Factories;

use App\Models\PrRoNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrRoNumber>
 */
class PrRoNumberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PR-'.fake()->unique()->numerify('##########'),
            'description' => fake()->sentence(4),
            'source' => 'Smart SCM',
            'is_active' => true,
        ];
    }
}
