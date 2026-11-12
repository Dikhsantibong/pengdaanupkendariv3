<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hands the Perencanaan Anggaran rights to their default roles: Anggaran
 * keeps the plan, and the team leaders read it. A right already granted is
 * left as it is.
 */
return new class extends Migration
{
    /**
     * The rights and the roles that get them.
     *
     * @var array<string, array<int, string>>
     */
    private const GRANTS = [
        'budget-plans.manage' => ['anggaran'],
        'menu.budget-plans' => ['anggaran', 'team_leader_pengadaan', 'team_leader_icc'],
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
