<?php

use Database\Seeders\PjTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Install the RKS used by PJ contracts for services (penunjukan langsung).
 * The seeder only creates or refreshes its own PJ templates.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        (new PjTemplateSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
