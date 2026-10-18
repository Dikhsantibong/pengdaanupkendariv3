<?php

namespace Database\Factories;

use App\Models\Procurement;
use App\Models\ProcurementDeliveryOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementDeliveryOrder>
 */
class ProcurementDeliveryOrderFactory extends Factory
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
            'request_memo_number' => fake()->numerify('ND-###/USL/').now()->format('Y'),
            'request_memo_date' => now()->toDateString(),
            'work_description' => 'Pengadaan material sesuai kontrak',
            'delivery_place' => 'PT PLN Nusantara Power UP Kendari',
            'delivery_days' => 90,
        ];
    }
}
