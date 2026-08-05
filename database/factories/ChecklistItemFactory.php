<?php

namespace Database\Factories;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stage' => ProcurementStage::Perencanaan,
            'name' => Str::title(rtrim(fake()->unique()->sentence(3), '.')),
            'description' => null,
            'is_optional' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate the stage the checklist item belongs to.
     */
    public function stage(ProcurementStage $stage): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => $stage,
        ]);
    }

    /**
     * Indicate that completing the item is not mandatory.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optional' => true,
        ]);
    }
}
