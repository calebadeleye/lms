<?php

namespace App\Domain\Identity\Http\Middleware;

use App\Domain\Identity\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: `->middleware('permission:courses.publish')`. Must run after `auth:sanctum`. */
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

        if (! $this->permissions->userHasPermission($user, $key)) {
            return response()->json([
                'errors' => [['code' => 'forbidden', 'message' => "Missing required permission: {$key}."]],
            ], 403);
        }

        return $next($request);
    }
}
