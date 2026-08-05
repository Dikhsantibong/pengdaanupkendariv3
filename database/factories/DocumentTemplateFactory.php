<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type_id' => DocumentType::factory(),
            'name' => 'Template '.rtrim(fake()->unique()->sentence(2), '.'),
            'version' => 1,
            'body' => '<h1>{{nama_pengadaan}}</h1><p>{{unit_tujuan}} - {{nilai_hpe}}</p>',
            'placeholders' => ['nama_pengadaan', 'unit_tujuan', 'nilai_hpe'],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the template is no longer used for generation.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
