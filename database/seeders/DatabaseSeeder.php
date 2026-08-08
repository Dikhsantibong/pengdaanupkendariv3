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
            DocumentTemplateSeeder::class,
            RksSpkTemplateSeeder::class,
            RksTenderTemplateSeeder::class,
            BeritaAcaraTemplateSeeder::class,
            KontrakTemplateSeeder::class,
            StandardDocumentTemplateSeeder::class,
            VendorAssessmentSeeder::class,
            UserSeeder::class,
        ]);
    }
}
