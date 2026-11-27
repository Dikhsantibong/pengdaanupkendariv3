<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hands the Request COA rights to their default roles so existing databases
 * immediately expose Request COA to Staf/TL Pengusul and Aktivasi COA to
 * Keuangan without needing manual reconfiguration.
 */
return new class extends Migration
{
    /**
     * The rights and the roles that get them.
     *
     * @var array<string, array<int, string>>
     */
    private const GRANTS = [
        'coa.request' => [
            'pengusul',
            'direksi_pekerjaan',
            'team_leader_pengadaan',
            'team_leader_icc',
        ],
        'coa.manage' => [
            'keuangan',
        ],
        'menu.coa-requests' => [
            'pengusul',
            'direksi_pekerjaan',
            'team_leader_pengadaan',
            'team_leader_icc',
            'keuangan',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::GRANTS as $permission => $roles) {
            foreach ($roles as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $role,
                    'permission' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Cache::forget('access-rights.grants');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', array_keys(self::GRANTS))->delete();
        Cache::forget('access-rights.grants');
    }
};
