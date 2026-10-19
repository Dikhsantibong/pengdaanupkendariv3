<?php

use App\Enums\TeamLeaderField;
use App\Enums\UnitManagerLevel;
use Database\Seeders\UnitLayananManagerSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every official on Data Master → Manager Unit a rank (Manager,
     * Manager UL, TL, TL OPHAR), a bidang for a TL, and the units they hold.
     */
    public function up(): void
    {
        Schema::table('unit_managers', function (Blueprint $table) {
            $table->string('level', 20)->default(UnitManagerLevel::Manager->value)->after('position');
            $table->string('field', 30)->nullable()->after('level');
        });

        Schema::create('target_unit_unit_manager', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_manager_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['unit_manager_id', 'target_unit_id']);
        });

        // The rank and bidang were written into the jabatan until now.
        DB::table('unit_managers')->orderBy('id')->get(['id', 'position'])->each(function (object $row): void {
            $level = UnitManagerLevel::fromPosition((string) $row->position);

            DB::table('unit_managers')->where('id', $row->id)->update([
                'level' => $level->value,
                'field' => $level === UnitManagerLevel::Tl ? TeamLeaderField::fromPosition((string) $row->position)?->value : null,
            ]);
        });

        // The units held by the Unit Layanan officials already listed.
        (new UnitLayananManagerSeeder)->arrange();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_unit_unit_manager');

        Schema::table('unit_managers', function (Blueprint $table) {
            $table->dropColumn(['level', 'field']);
        });
    }
};
