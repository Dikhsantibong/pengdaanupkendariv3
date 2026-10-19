<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How the partner is chosen (Penunjukan Langsung, Pengadaan Langsung, Lelang
 * Terbuka ...), picked by the planning PIC and printed on the documents.
 *
 * Kept apart from the internal procurement method, which follows the
 * contract format and picks the templates and steps. Modular like the other
 * reference data, with soft deletes keeping the history of jobs using one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('selection_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('procurements', function (Blueprint $table): void {
            // Nullable: the planning PIC fills it in after being appointed.
            $table->foreignId('selection_method_id')->nullable()->after('contract_type_id')
                ->constrained('selection_methods')->nullOnDelete();
        });

        $methods = [
            ['penunjukan-langsung', 'Penunjukan Langsung'],
            ['pengadaan-langsung', 'Pengadaan Langsung'],
            ['lelang-terbuka-1-tahap-1-sampul', 'Lelang Terbuka 1 Tahap 1 Sampul'],
            ['lelang-terbuka-1-tahap-2-sampul', 'Lelang Terbuka 1 Tahap 2 Sampul'],
        ];

        foreach ($methods as $index => [$code, $name]) {
            DB::table('selection_methods')->insert([
                'code' => $code,
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selection_method_id');
        });

        Schema::dropIfExists('selection_methods');
    }
};
