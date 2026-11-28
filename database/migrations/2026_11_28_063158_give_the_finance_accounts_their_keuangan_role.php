<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The finance accounts (TL Keuangan and Staf Keuangan) were first created as
 * Pengusul. DivisionUserSeeder gives them their Keuangan role, but a seeder
 * is not run on deploy, so a database created before still has them as
 * Pengusul: TL Keuangan then sees "Request COA" instead of the activation
 * queue, and no request reaches it.
 *
 * Only an account still at the old Pengusul role moves; one an administrator
 * set to another role is left alone.
 */
return new class extends Migration
{
    /** The seeded finance accounts. */
    private const EMAILS = [
        'nova.sang.jaya@upkendari.go.id',
        'muhammad.jaya.junaid@upkendari.go.id',
        'inggrid.novela.pitaloka@upkendari.go.id',
    ];

    /** The finance positions. */
    private const POSITIONS = ['TL Keuangan', 'Staf Keuangan'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'pengusul')
            ->where(fn ($query) => $query->whereIn('email', self::EMAILS)->orWhereIn('position', self::POSITIONS))
            ->update(['role' => 'keuangan', 'updated_at' => now()]);

        // The staff answer to their TL, who approves their payments first.
        $teamLeader = DB::table('users')->where('email', self::EMAILS[0])->value('id');

        if ($teamLeader !== null) {
            DB::table('users')
                ->whereIn('email', array_slice(self::EMAILS, 1))
                ->whereNull('team_leader_id')
                ->update(['team_leader_id' => $teamLeader, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Which accounts were Pengusul before is not kept, so nothing is undone.
     */
    public function down(): void
    {
        //
    }
};
