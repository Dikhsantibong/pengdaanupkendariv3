<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hand every role the menu rights it already had.
 *
 * The menus became rights after the access table was first filled, so their
 * defaults are added here; otherwise every non-administrator would lose them
 * the moment this ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Permission::cases() as $permission) {
            if (! str_starts_with($permission->value, 'menu.')) {
                continue;
            }

            foreach ($permission->defaultRoles() as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $role->value,
                    'permission' => $permission->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Cache::forget('access-rights.grants');
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'like', 'menu.%')->delete();

        Cache::forget('access-rights.grants');
    }
};
