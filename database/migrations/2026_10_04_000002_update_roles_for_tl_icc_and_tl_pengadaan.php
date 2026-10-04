<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Services\AccessRights;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update any existing users with role 'team_leader' to 'team_leader_pengadaan'
        DB::table('users')
            ->where('role', 'team_leader')
            ->update([
                'role' => UserRole::TeamLeaderPengadaan->value,
            ]);

        // 2. Update the default team leader pengadaan user info if matching email
        DB::table('users')
            ->where('email', 'team.leader.pengadaan@upkendari.test')
            ->update([
                'name' => 'Team Leader Pengadaan',
                'position' => 'Team Leader Pengadaan',
                'role' => UserRole::TeamLeaderPengadaan->value,
            ]);

        // 3. Create Team Leader ICC account if it does not exist yet
        if (! DB::table('users')->where('email', 'team.leader.icc@upkendari.test')->exists()) {
            DB::table('users')->insert([
                'name' => 'Team Leader ICC',
                'email' => 'team.leader.icc@upkendari.test',
                'role' => UserRole::TeamLeaderIcc->value,
                'position' => 'Team Leader ICC',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Update role_permissions: remove legacy 'team_leader' entries if table exists
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('role', 'team_leader')->delete();

            // Populate default grants for the two new TL roles
            foreach (Permission::cases() as $permission) {
                foreach ($permission->defaultRoles() as $role) {
                    if (in_array($role, [UserRole::TeamLeaderPengadaan, UserRole::TeamLeaderIcc], true)) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role' => $role->value,
                            'permission' => $permission->value,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            AccessRights::forget();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('role', [
                UserRole::TeamLeaderPengadaan->value,
                UserRole::TeamLeaderIcc->value,
            ])->delete();
        }

        DB::table('users')
            ->where('role', UserRole::TeamLeaderPengadaan->value)
            ->update(['role' => 'team_leader']);
    }
};
