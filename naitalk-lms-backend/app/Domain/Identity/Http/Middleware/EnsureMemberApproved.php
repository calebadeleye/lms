<?php

namespace App\Domain\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('approved')`. Blocks every member action
 * for an account whose membership application hasn't been approved yet
 * (`status` is `pending` or `rejected`) — matching JSON error shape used
 * elsewhere (CheckPermission) rather than a bespoke one. Deliberately NOT
 * applied to `/auth/*` (me, logout, application status, email/resend) or
 * `/admin/*` (staff accounts are provisioned pre-approved) — same placement
 * logic as EnsureEmailIsVerified.
 */
class EnsureMemberApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === 'active') {
            return $next($request);
        }

        if ($user && $user->status === 'rejected') {
            return response()->json([
                'errors' => [['code' => 'membership_rejected', 'message' => 'Your membership application was not approved.']],
            ], 403);
        }

        return response()->json([
            'errors' => [['code' => 'membership_pending', 'message' => 'Your membership application is still pending approval.']],
        ], 403);
    }
}
