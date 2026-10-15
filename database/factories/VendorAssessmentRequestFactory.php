<?php

namespace Database\Factories;

use App\Models\VendorAssessmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorAssessmentRequest>
 */
class VendorAssessmentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project' => 'Pemeliharaan Genset Unit 2',
            'vendor_name' => 'PT. Sinar Teknik',
            'contract_number' => 'KDD001.SPPL/100/UPKD/2026',
            'contract_date' => '2026-08-01',
            'term' => 'Termin 1 (100%)',
            'invoice_amount' => '15000000.00',
            'bastp_number' => 'BASTP/021/2026',
            'bastp_date' => '2026-09-15',
            'has_penalty' => false,
            'submitter_name' => 'Budi',
            'submitter_phone' => '081234567890',
        ];
    }
}
