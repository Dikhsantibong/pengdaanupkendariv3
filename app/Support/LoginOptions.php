<?php

namespace App\Support;

use Laravel\Fortify\Features;

/**
 * The optional ways in: passkey sign-in and resetting a forgotten
 * password. Each needs its Fortify feature and its switch in
 * config('fortify.login_options'), off unless turned on in .env.
 */
class LoginOptions
{
    /**
     * The routes that belong to each option.
     *
     * @var array<string, list<string>>
     */
    public const ROUTES = [
        'passkeys' => ['passkey.*', 'well-known.passkeys'],
        'password_reset' => ['password.request', 'password.email', 'password.reset', 'password.update'],
    ];

    /**
     * Whether users may sign in and confirm with a passkey.
     */
    public static function passkeys(): bool
    {
        return Features::enabled(Features::passkeys()) && (bool) config('fortify.login_options.passkeys');
    }

    /**
     * Whether a forgotten password may be reset by email.
     */
    public static function passwordReset(): bool
    {
        return Features::enabled(Features::resetPasswords()) && (bool) config('fortify.login_options.password_reset');
    }

    /**
     * Whether the option a named route belongs to is switched on; routes of
     * no option are always on.
     */
    public static function allows(?string $routeName): bool
    {
        if ($routeName === null) {
            return true;
        }

        foreach (self::ROUTES as $option => $patterns) {
            if (str($routeName)->is($patterns)) {
                return $option === 'passkeys' ? self::passkeys() : self::passwordReset();
            }
        }

        return true;
    }
}
