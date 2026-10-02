<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Services\AccessRights;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One right held by one role.
 *
 * @property int $id
 * @property UserRole $role
 * @property Permission $permission
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['role', 'permission'])]
class RolePermission extends Model
{
    /**
     * Drop the cached grants whenever a right is given or taken away, so the
     * change applies on the very next request.
     */
    protected static function booted(): void
    {
        static::saved(fn () => AccessRights::forget());
        static::deleted(fn () => AccessRights::forget());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'permission' => Permission::class,
        ];
    }
}
