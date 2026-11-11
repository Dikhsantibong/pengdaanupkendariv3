<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The administrator's maintenance mode: while it is on, the application is
 * closed to everyone but the administrator.
 *
 * The login page says so, with the administrator's message and when the
 * work should be over. Only an administrator can sign in; anyone else still
 * signed in is signed out at their next click, and the public pages close
 * too. The administrator keeps working as usual, also through "Pindah Akun",
 * which only an administrator can start.
 *
 * Kept in the database (app_settings), so a deploy that clears the cache
 * never opens the application by accident.
 */
class MaintenanceMode
{
    /**
     * The setting's key in app_settings.
     */
    public const KEY = 'maintenance';

    /**
     * What the login page says when no message is given.
     */
    public const DEFAULT_MESSAGE = 'Aplikasi sedang dalam pemeliharaan dan belum dapat digunakan. Silakan coba kembali beberapa saat lagi.';

    /**
     * The routes open to everyone while the mode is on: signing in (the
     * administrator must be able to), and signing out.
     *
     * @var list<string>
     */
    public const OPEN_ROUTES = [
        'login',
        'login.store',
        'logout',
        'two-factor.login',
        'two-factor.login.store',
    ];

    /**
     * The mode as it stands.
     *
     * @return array{active: bool, message: string, until: string|null, updated_by: string|null, updated_at: string|null}
     */
    public static function state(): array
    {
        $setting = AppSetting::query()->with('updater:id,name')->where('key', self::KEY)->first();
        $value = $setting->value ?? [];
        $message = trim((string) ($value['message'] ?? ''));
        $until = $value['until'] ?? null;

        return [
            'active' => (bool) ($value['active'] ?? false),
            'message' => $message === '' ? self::DEFAULT_MESSAGE : $message,
            'until' => is_string($until) && $until !== '' ? $until : null,
            'updated_by' => $setting?->updater?->name,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Whether the application is closed for maintenance.
     */
    public static function isActive(): bool
    {
        // Read on every request: only the flag, nothing else.
        $setting = AppSetting::query()->where('key', self::KEY)->first(['value']);

        return (bool) ($setting?->value['active'] ?? false);
    }

    /**
     * Switch the mode on or off, with what the login page should say.
     */
    public static function update(bool $active, ?string $message, ?CarbonImmutable $until, User $by): void
    {
        AppSetting::query()->updateOrCreate(['key' => self::KEY], [
            'value' => [
                'active' => $active,
                'message' => $message === null || trim($message) === '' ? null : trim($message),
                'until' => $until?->toIso8601String(),
            ],
            'updated_by' => $by->id,
        ]);
    }

    /**
     * Whether the signed-in account may keep using the application while
     * the mode is on: the administrator, also while switched into another
     * account through "Pindah Akun" (which only an administrator starts).
     */
    public static function admits(?User $user, Request $request): bool
    {
        return $user !== null && ($user->isAdministrator() || Impersonation::isActive($request));
    }

    /**
     * Whether the email signing in belongs to an active administrator, the
     * only one let in while the mode is on.
     */
    public static function admitsLogin(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        $user = User::query()->where('email', trim($email))->first();

        return $user !== null && $user->is_active && $user->isAdministrator();
    }
}
