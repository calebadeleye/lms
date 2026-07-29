<?php

namespace App\Support\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For routes that are public but personalize their response when the
 * visitor happens to be logged in (e.g. GET /courses/{slug} marking
 * already-enrolled lessons unlocked) — `$request->user()` only checks
 * Sanctum bearer tokens once something has called `Auth::shouldUse('sanctum')`,
 * which normally only happens inside the enforcing `auth:sanctum` middleware.
 * A route with no auth middleware at all (by design, so guests can browse)
 * therefore always sees `$request->user()` as null even with a valid
 * Authorization header — this switches the default guard without ever
 * rejecting the request, unlike `auth:sanctum`.
 */
class TryAuthenticateSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            Auth::shouldUse('sanctum');
        }

        return $next($request);
    }
}
