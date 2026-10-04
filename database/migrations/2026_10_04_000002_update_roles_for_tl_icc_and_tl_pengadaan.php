<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Services\AccessRights;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove obsolete dummy team leader accounts if present, reassigning to Administrator
        $admin = DB::table('users')->where('role', UserRole::Administrator->value)->first();
        $dummyUsers = DB::table('users')->whereIn('email', [
            'team.leader.pengadaan@upkendari.test',
            'team.leader.icc@upkendari.test',
        ])->get();

        if ($dummyUsers->isNotEmpty() && $admin) {
            $dummyIds = $dummyUsers->pluck('id')->all();
            DB::table('procurements')->whereIn('created_by', $dummyIds)->update(['created_by' => $admin->id]);
            DB::table('procurements')->whereIn('planning_reviewed_by', $dummyIds)->update(['planning_reviewed_by' => $admin->id]);
            DB::table('procurement_activities')->whereIn('user_id', $dummyIds)->update(['user_id' => $admin->id]);
            DB::table('users')->whereIn('id', $dummyIds)->delete();
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
