<?php

namespace Database\Factories;

use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementAmendment>
 */
class ProcurementAmendmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'procurement_id' => Procurement::factory(),
            'sequence' => 1,
            'state' => 'draft',
            'amendment_date' => now()->toDateString(),
            'memo_number' => '1279/KLH.00.01/GM/'.now()->format('Y'),
            'memo_date' => now()->toDateString(),
            'request_number' => '021/SP/'.now()->format('Y'),
            'request_date' => now()->toDateString(),
        ];
    }
}
