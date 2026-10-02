<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Answers "may this role do that?" from the rights set on the access screen.
 */
class AccessRights
{
    private const CACHE_KEY = 'access-rights.grants';

    /**
     * Whether a user holds a right. The administrator holds every right.
     */
    public static function allows(User $user, Permission $permission): bool
    {
        if ($user->role === UserRole::Administrator) {
            return true;
        }

        return in_array($permission->value, self::grants()[$user->role->value] ?? [], true);
    }

    /**
     * Every right held, keyed by role.
     *
     * Read on almost every request, so it is cached and dropped whenever a
     * right changes.
     *
     * @return array<string, array<int, string>>
     */
    public static function grants(): array
    {
        /** @var array<string, array<int, string>> */
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $grants = [];

            foreach (RolePermission::query()->get() as $row) {
                $grants[$row->role->value][] = $row->permission->value;
            }

            return $grants;
        });
    }

    /**
     * Replace the rights of every configurable role in one go.
     *
     * The administrator is never stored or changed: it always holds every
     * right. Unknown roles and rights are ignored.
     *
     * @param  array<string, array<int, string>>  $grants
     */
    public static function replace(array $grants): void
    {
        DB::transaction(function () use ($grants): void {
            foreach (self::configurableRoles() as $role) {
                $wanted = array_values(array_unique(array_filter(
                    $grants[$role->value] ?? [],
                    fn (string $value): bool => Permission::tryFrom($value) !== null,
                )));

                RolePermission::query()
                    ->where('role', $role->value)
                    ->whereNotIn('permission', $wanted)
                    ->get()
                    ->each->delete();

                foreach ($wanted as $permission) {
                    RolePermission::query()->firstOrCreate([
                        'role' => $role->value,
                        'permission' => $permission,
                    ]);
                }
            }
        });

        self::forget();
    }

    /**
     * The roles whose rights can be changed.
     *
     * @return array<int, UserRole>
     */
    public static function configurableRoles(): array
    {
        return array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => $role !== UserRole::Administrator,
        ));
    }

    /**
     * Drop the cached grants.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
