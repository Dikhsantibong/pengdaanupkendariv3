<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a "Pindah Akun" session to the account's everyday work.
 *
 * While the administrator works as someone else, that person's credentials
 * and security settings, their profile, and the administration of users and
 * rights stay closed. A switch record that does not match the account signed
 * in is dropped rather than trusted.
 */
class RestrictImpersonatedSession
{
    /**
     * The routes closed while working as another account.
     *
     * @var array<int, string>
     */
    private const BLOCKED = [
        'profile.update',
        'profile.destroy',
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.enable',
        'two-factor.disable',
        'two-factor.confirm',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'passkey.store',
        'passkey.destroy',
        'passkey.registration-options',
        'passkey.confirm',
        'passkey.confirm-options',
        'users.*',
        'access-rights.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! Impersonation::isActive($request)) {
            return $next($request);
        }

        if (! Impersonation::matches($request)) {
            Impersonation::close($request);
            $request->session()->forget(Impersonation::SESSION_KEY);

            return $next($request);
        }

        $route = $request->route()?->getName();

        if ($route !== null && Str::is(self::BLOCKED, $route)) {
            abort(403, 'Tidak tersedia saat memakai akun lain melalui Pindah Akun. Kembali ke akun Administrator terlebih dahulu.');
        }

        return $next($request);
    }
}
