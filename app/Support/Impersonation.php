<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The administrator's "Pindah Akun": working as another account and coming
 * back, without knowing that account's password.
 *
 * The rules that keep it safe live here, in one place:
 *
 * - Only an active administrator, signed in as themselves, may switch. No
 *   switching from a switched session, and never into another administrator.
 * - The administrator's own password is asked again unless it was confirmed
 *   for a switch in the last few minutes.
 * - The session is regenerated on every switch, and any password
 *   confirmation is dropped, so the borrowed account's security settings
 *   still need that account's own password.
 * - Every switch is written to `impersonation_logs`, with when it ended.
 */
class Impersonation
{
    /**
     * Where the switch is remembered in the session.
     */
    public const SESSION_KEY = 'impersonation';

    /**
     * When the administrator last confirmed their password for a switch.
     */
    public const CONFIRMED_KEY = 'impersonation_password_confirmed_at';

    /**
     * How long, in seconds, a password confirmation covers further switches.
     */
    public const CONFIRMATION_TIMEOUT = 900;

    /**
     * Whether the session is working as another account.
     */
    public static function isActive(Request $request): bool
    {
        return is_array($request->session()->get(self::SESSION_KEY));
    }

    /**
     * Whether the user may switch into another account right now.
     */
    public static function canStart(?User $user, Request $request): bool
    {
        return $user !== null
            && $user->isAdministrator()
            && $user->is_active
            && ! self::isActive($request);
    }

    /**
     * Whether the administrator has to type their password for this switch.
     */
    public static function needsPassword(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get(self::CONFIRMED_KEY, 0);

        return time() - $confirmedAt > self::CONFIRMATION_TIMEOUT;
    }

    /**
     * Note that the administrator has just confirmed their password.
     */
    public static function confirmPassword(Request $request): void
    {
        $request->session()->put(self::CONFIRMED_KEY, time());
    }

    /**
     * The accounts that may be switched into.
     *
     * @return Builder<User>
     */
    public static function targets(): Builder
    {
        return User::query()
            ->active()
            ->where('role', '!=', UserRole::Administrator->value);
    }

    /**
     * Whether the administrator may switch into this account.
     */
    public static function canTarget(User $administrator, User $target): bool
    {
        return ! $target->is($administrator)
            && self::targets()->whereKey($target->getKey())->exists();
    }

    /**
     * Switch the session into another account.
     */
    public static function start(Request $request, User $administrator, User $target): void
    {
        $log = ImpersonationLog::query()->create([
            'impersonator_id' => $administrator->id,
            'impersonated_id' => $target->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'started_at' => now(),
        ]);

        Auth::guard('web')->login($target);

        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->forget('auth.password_confirmed_at');
        $request->session()->put(self::SESSION_KEY, [
            'impersonator_id' => $administrator->id,
            'impersonated_id' => $target->id,
            'log_id' => $log->id,
            'started_at' => $log->started_at->toIso8601String(),
        ]);
    }

    /**
     * Return the session to the administrator.
     *
     * Returns the administrator, or null when they may no longer sign in, in
     * which case the caller signs the session out altogether.
     */
    public static function stop(Request $request): ?User
    {
        $administrator = self::impersonator($request);

        self::close($request);

        $request->session()->forget([self::SESSION_KEY, 'auth.password_confirmed_at']);

        if ($administrator === null) {
            return null;
        }

        Auth::guard('web')->login($administrator);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return $administrator;
    }

    /**
     * The administrator behind the switched session, if they still are one.
     */
    public static function impersonator(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_KEY.'.impersonator_id');

        if (! is_int($id)) {
            return null;
        }

        $administrator = User::query()->active()->find($id);

        return $administrator?->isAdministrator() ? $administrator : null;
    }

    /**
     * Whether the switch recorded in the session is the one signed in now.
     *
     * Anything else (a different account signed in, a broken record) means
     * the record is stale and must not be trusted.
     */
    public static function matches(Request $request): bool
    {
        return $request->session()->get(self::SESSION_KEY.'.impersonated_id') === Auth::guard('web')->id();
    }

    /**
     * Note in the audit trail that the switch has ended.
     */
    public static function close(Request $request): void
    {
        $logId = $request->session()->get(self::SESSION_KEY.'.log_id');

        if (is_int($logId)) {
            ImpersonationLog::query()->whereKey($logId)->whereNull('ended_at')->update(['ended_at' => now()]);
        }
    }

    /**
     * What the header shows while switched.
     *
     * @return array{impersonator: string, since: string|null}|null
     */
    public static function shared(Request $request): ?array
    {
        if (! self::isActive($request)) {
            return null;
        }

        $since = $request->session()->get(self::SESSION_KEY.'.started_at');

        return [
            'impersonator' => self::impersonator($request)->name ?? 'Administrator',
            'since' => is_string($since) ? $since : null,
        ];
    }
}
