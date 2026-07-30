<?php

namespace App\Domain\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('verified')`. Blocks every member action
 * for a self-registered account that hasn't clicked its
 * verification link yet — matching JSON error shape used elsewhere
 * (CheckPermission) rather than Laravel's default `{"message": "..."}`
 * body, so the frontend's error handling stays consistent. Deliberately
 * NOT applied to the `auth:sanctum` group under `/auth/*` (me, logout,
 * email/resend, sessions) — an unverified user must still be able to check
 * their own status, resend the email, and log out.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            return response()->json([
                'errors' => [['code' => 'email_not_verified', 'message' => 'Please verify your email address to continue.']],
            ], 403);
        }

        return $next($request);
    }
}
