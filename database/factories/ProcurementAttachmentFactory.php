<?php

namespace Database\Factories;

use App\Models\Procurement;
use App\Models\ProcurementAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementAttachment>
 */
class ProcurementAttachmentFactory extends Factory
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
            'path' => 'proposal-attachments/'.fake()->uuid().'.pdf',
            'file_name' => 'Nota Dinas Usulan.pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
            'uploaded_by' => null,
        ];
    }
}
