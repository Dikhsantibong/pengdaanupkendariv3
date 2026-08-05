<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string|null $position
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $planned_procurements_count
 * @property-read int|null $executed_procurements_count
 */
#[Fillable(['name', 'email', 'password', 'role', 'position', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The procurements this user plans.
     *
     * @return HasMany<Procurement, $this>
     */
    public function plannedProcurements(): HasMany
    {
        return $this->hasMany(Procurement::class, 'planner_id');
    }

    /**
     * The procurements this user executes.
     *
     * @return HasMany<Procurement, $this>
     */
    public function executedProcurements(): HasMany
    {
        return $this->hasMany(Procurement::class, 'executor_id');
    }

    /**
     * Determine whether the user manages the system configuration.
     */
    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    /**
     * Determine whether the user oversees every procurement.
     */
    public function isSupervisor(): bool
    {
        return $this->role->isSupervisor();
    }

    /**
     * Limit the query to users holding one of the given roles.
     *
     * @param  Builder<static>  $query
     * @param  array<int, UserRole>  $roles
     * @return Builder<static>
     */
    public function scopeWithRole(Builder $query, array $roles): Builder
    {
        return $query->whereIn('role', array_map(fn (UserRole $role): string => $role->value, $roles));
    }

    /**
     * Limit the query to users who may still sign in.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }
}
