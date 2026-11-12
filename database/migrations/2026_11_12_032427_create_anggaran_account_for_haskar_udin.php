<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The Anggaran account of Haskar Udin, who keeps the budget plan.
 *
 * It is only created when missing: an existing account keeps its role and
 * password. The first password is "password", to be changed after logging in.
 */
return new class extends Migration
{
    private const EMAIL = 'haskar.udin@upkendari.go.id';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tests build their own users; BudgetPlanTest runs this on purpose.
        if (app()->runningUnitTests() && ! app()->bound('migrations.force-anggaran-account')) {
            return;
        }

        if (DB::table('users')->where('email', self::EMAIL)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'Haskar Udin',
            'email' => self::EMAIL,
            'role' => 'anggaran',
            'position' => 'Staf Anggaran',
            'is_active' => true,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * The account is kept: it may have done work by then.
     */
    public function down(): void
    {
        //
    }
};
