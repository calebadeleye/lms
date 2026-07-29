<?php

namespace App\Domain\Identity\Http\Middleware;

use App\Domain\Identity\Services\PermissionService;
use App\Domain\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('permission:courses.publish')`.
 * Must run after `auth:sanctum` and after either `tenant` (ResolveTenant) or
 * `platform` (PlatformStaffOnly) so it knows which scope to check.
 */
class CheckPermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'errors' => [['code' => 'unauthenticated', 'message' => 'Authentication required.']],
            ], 401);
        }

        $isPlatformRoute = $request->attributes->get('platform_staff') !== null;

        $allowed = $isPlatformRoute
            ? $this->permissions->userHasPlatformPermission($user, $key)
            : $this->permissions->userHasTenantPermission($user, app(TenantContext::class)->id() ?? '', $key);

        if (! $allowed) {
            return response()->json([
                'errors' => [['code' => 'forbidden', 'message' => "Missing required permission: {$key}."]],
            ], 403);
        }

        return $next($request);
    }
}
