<?php

namespace Database\Seeders;

use App\Support\CoaCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CoaSegmentOptionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the default UP Kendari COA segment options.
     */
    public function run(): void
    {
        CoaCatalog::seedDefaults();
    }
}
