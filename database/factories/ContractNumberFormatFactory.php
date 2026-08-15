<?php

namespace Database\Factories;

use App\Models\ContractNumberFormat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContractNumberFormat>
 */
class ContractNumberFormatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::upper(fake()->unique()->lexify('??'));

        return [
            'code' => $code,
            'name' => 'Format '.$code,
            'prefix' => 'KDD',
            'unit_segment' => '612/UPKD',
            'sequence_length' => 3,
            'starting_sequence' => 1,
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the format is no longer selectable.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
