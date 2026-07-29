<?php

namespace App\Domain\Identity\Http\Middleware;

use App\Domain\Identity\Models\PlatformStaff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /api/v1/platform/* routes. These never resolve a TenantContext —
 * platform administration is deliberately outside tenant boundaries.
 */
class PlatformStaffOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'errors' => [['code' => 'unauthenticated', 'message' => 'Authentication required.']],
            ], 401);
        }

        $staff = PlatformStaff::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with('role.permissions')
            ->first();

        if (! $staff) {
            return response()->json([
                'errors' => [['code' => 'forbidden', 'message' => 'Platform administration access required.']],
            ], 403);
        }

        $request->attributes->set('platform_staff', $staff);

        return $next($request);
    }
}
