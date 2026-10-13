<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MasterDataSeeder::class,
            // Every default template, from resources/document-templates.
            DocumentTemplateLibrarySeeder::class,
            VendorAssessmentSeeder::class,
            UserSeeder::class,
            // Proposers and Direksi Pekerjaan of every division.
            DivisionUserSeeder::class,
        ]);
    }
}
