<?php

namespace Database\Factories;

use App\Enums\StatusCategory;
use App\Models\ProgressStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgressStatus>
 */
class ProgressStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(2), '.');

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'category' => StatusCategory::Berjalan,
            'sort_order' => 0,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the status is applied to new procurements.
     */
    public function asDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate the lifecycle category of the status.
     */
    public function category(StatusCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }
}
