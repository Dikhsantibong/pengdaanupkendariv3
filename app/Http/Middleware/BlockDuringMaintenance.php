<?php

namespace App\Http\Middleware;

use App\Support\MaintenanceMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Close the application to everyone but the administrator while the
 * maintenance mode is on (see App\Support\MaintenanceMode).
 */
class BlockDuringMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! MaintenanceMode::isActive()) {
            return $next($request);
        }

        $user = $request->user();

        if (MaintenanceMode::admits($user, $request)) {
            return $next($request);
        }

        // Anyone else still signed in is signed out, and told why.
        if ($user !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->closed($request, 'Aplikasi sedang dalam pemeliharaan, sesi Anda diakhiri. Silakan masuk kembali setelah pemeliharaan selesai.');
        }

        $route = $request->route()?->getName();

        // Only the administrator may sign in. Refused before the password
        // is checked, with the same words for every other address.
        if ($route === 'login.store' && ! MaintenanceMode::admitsLogin($request->string(Fortify::username())->value())) {
            throw ValidationException::withMessages([
                Fortify::username() => 'Aplikasi sedang dalam pemeliharaan. Untuk sementara hanya Administrator yang dapat masuk.',
            ]);
        }

        if (in_array($route, MaintenanceMode::OPEN_ROUTES, true)) {
            return $next($request);
        }

        return $this->closed($request);
    }

    /**
     * Send the visitor to the login page, which says the application is
     * under maintenance; a JSON request is answered 503.
     */
    protected function closed(Request $request, ?string $status = null): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => MaintenanceMode::DEFAULT_MESSAGE], 503);
        }

        $redirect = redirect()->route('login');

        return $status === null ? $redirect : $redirect->with('status', $status);
    }
}
