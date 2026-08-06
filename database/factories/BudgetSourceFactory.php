<?php

namespace Database\Factories;

use App\Models\BudgetSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BudgetSource>
 */
class BudgetSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Two letters would collide with the seeded AO/AI codes whenever a test
        // seeds the master data and then makes a budget source of its own, so
        // the generated code carries a run-unique suffix.
        $name = Str::upper(fake()->unique()->lexify('??').fake()->unique()->numerify('###'));

        return [
            'code' => $name,
            'name' => $name,
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
