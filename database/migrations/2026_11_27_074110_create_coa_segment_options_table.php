<?php

use App\Support\CoaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coa_segment_options', function (Blueprint $table) {
            $table->id();
            $table->string('segment', 32)->index();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['segment', 'code']);
            $table->index(['segment', 'sort_order']);
        });

        CoaCatalog::seedDefaults();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coa_segment_options');
    }
};
