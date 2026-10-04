<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('unit_managers')) {
            Schema::create('unit_managers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('position')->default('MANAGER');
                $table->string('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });

            DB::table('unit_managers')->insert([
                'name' => 'MUHAMMAD RUSLI',
                'position' => 'MANAGER',
                'description' => 'Manager PT PLN Nusantara Power UP Kendari',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('procurements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurements', 'quotation_number')) {
                $table->string('quotation_number')->nullable()->after('wo_number');
            }
            if (! Schema::hasColumn('procurements', 'quotation_date')) {
                $table->date('quotation_date')->nullable()->after('quotation_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            if (Schema::hasColumn('procurements', 'quotation_date')) {
                $table->dropColumn('quotation_date');
            }
            if (Schema::hasColumn('procurements', 'quotation_number')) {
                $table->dropColumn('quotation_number');
            }
        });

        Schema::dropIfExists('unit_managers');
    }
};
