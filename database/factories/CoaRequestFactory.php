<?php

namespace Database\Factories;

use App\Enums\CoaRequestStatus;
use App\Enums\UserRole;
use App\Models\CoaRequest;
use App\Models\User;
use App\Support\CoaCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoaRequest>
 */
class CoaRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $segment1 = 'A';
        $segment2 = 'KD';
        $segment3 = '21';
        $segment4 = '372';
        $segment5 = '000';
        $segment6 = '23';
        $segment7 = '20';
        $expenseElement = 'F102/JBJSLN';

        return [
            'requested_by' => User::factory()->role(UserRole::Pengusul),
            'work_director_id' => null,
            'segment_1' => $segment1,
            'segment_2' => $segment2,
            'segment_3' => $segment3,
            'segment_4' => $segment4,
            'segment_5' => $segment5,
            'segment_6' => $segment6,
            'segment_7' => $segment7,
            'expense_element' => $expenseElement,
            'coa_code' => CoaCatalog::composeCode(
                $segment1,
                $segment2,
                $segment3,
                $segment4,
                $segment5,
                $segment6,
                $segment7,
                $expenseElement,
            ),
            'description' => fake()->sentence(5),
            'notes' => null,
            'status' => CoaRequestStatus::Draft,
            'requested_at' => null,
            'activated_by' => null,
            'activated_at' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Mark the COA request as submitted for Finance activation.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => CoaRequestStatus::Pending,
            'requested_at' => now(),
        ]);
    }

    /**
     * Mark the COA request as activated by Finance.
     */
    public function active(?User $activator = null): static
    {
        return $this->state(fn (): array => [
            'status' => CoaRequestStatus::Active,
            'requested_at' => now()->subHour(),
            'activated_by' => $activator?->id ?? User::factory()->role(UserRole::Keuangan),
            'activated_at' => now(),
        ]);
    }
}
