<?php

namespace Database\Factories;

use App\Models\ContractType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContractType>
 */
class ContractTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::upper(rtrim(fake()->unique()->word(), '.'));

        return [
            'code' => Str::slug($name),
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
