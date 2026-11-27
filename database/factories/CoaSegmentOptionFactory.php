<?php

namespace Database\Factories;

use App\Enums\CoaSegment;
use App\Models\CoaSegmentOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoaSegmentOption>
 */
class CoaSegmentOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'segment' => CoaSegment::Segment3,
            'code' => strtoupper(fake()->unique()->bothify('##?')),
            'name' => strtoupper(fake()->words(2, true)),
            'description' => null,
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
