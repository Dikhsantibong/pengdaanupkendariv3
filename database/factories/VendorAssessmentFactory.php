<?php

namespace Database\Factories;

use App\Models\Procurement;
use App\Models\VendorAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorAssessment>
 */
class VendorAssessmentFactory extends Factory
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
            'project' => rtrim(fake()->sentence(4), '.'),
            'po_number' => fake()->numerify('####/PO/UPKD/####'),
            'po_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'vendor_name' => 'PT '.rtrim(fake()->company(), '.'),
            'form_number' => 'SMT-FM-DAN-02.02',
            'revision_number' => '03',
            'form_date' => now(),
            'place' => 'Kendari',
            'notes' => null,
        ];
    }
}
