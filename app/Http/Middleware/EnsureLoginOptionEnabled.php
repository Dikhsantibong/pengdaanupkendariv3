<?php

namespace App\Http\Middleware;

use App\Support\LoginOptions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer 404 on the routes of a login option that is switched off
 * (passkeys, resetting a forgotten password).
 */
class EnsureLoginOptionEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(LoginOptions::allows($request->route()?->getName()), 404);

        return $next($request);
    }
}
