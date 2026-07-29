<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Http\Requests\LoginRequest;
use App\Domain\Identity\Models\PlatformStaff;
use App\Domain\Identity\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Platform-staff authentication. Deliberately separate from AuthController:
 * it never resolves a TenantContext and never accepts a tenant hostname —
 * platform administration exists entirely outside tenant boundaries.
 */
class PlatformAuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(LoginRequest $request)
    {
        $rateLimitKey = 'platform-login:'.$request->ip().'|'.$request->string('email');

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }

        $user = $this->auth->verifyCredentials($request->string('email'), $request->string('password'));

        $staff = $user ? PlatformStaff::where('user_id', $user->id)->where('status', 'active')->first() : null;

        if (! $user || ! $staff) {
            RateLimiter::hit($rateLimitKey, 60);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        $issued = $this->auth->issueToken($user, $request, tenantId: null);

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email']),
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $this->auth->revokeCurrentToken($request->user());

        return response()->json(['data' => ['success' => true]]);
    }

    public function me(Request $request)
    {
        $staff = $request->attributes->get('platform_staff');
        $staff->loadMissing('role.permissions');

        return response()->json([
            'data' => [
                'user' => $request->user()->only(['id', 'public_id', 'name', 'email']),
                'role' => $staff->role->only(['id', 'name', 'slug']),
                'permissions' => $staff->role->permissions->pluck('key'),
            ],
        ]);
    }
}
