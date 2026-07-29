<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Http\Requests\LoginRequest;
use App\Domain\Identity\Http\Requests\RegisterRequest;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Identity\Models\UserSession;
use App\Domain\Identity\Services\AuthService;
use App\Domain\Identity\Services\MfaService;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private MfaService $mfa,
        private TenantContext $tenantContext,
    ) {}

    /**
     * Self-registration on a tenant domain. Always assigns the tenant's
     * default "Student" role — staff accounts are created via invitations,
     * never self-registration.
     */
    public function register(RegisterRequest $request)
    {
        $tenant = $this->tenantContext->tenant();

        if (User::where('email', $request->string('email'))->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        $studentRole = Role::forTenant($tenant->id)->where('slug', 'student')->firstOrFail();

        $user = DB::transaction(function () use ($request, $tenant, $studentRole) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'password' => $request->string('password'),
            ]);

            TenantUser::create([
                'user_id' => $user->id,
                'role_id' => $studentRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        $issued = $this->auth->issueToken($user, $request, $tenant->id);

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email']),
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $tenant = $this->tenantContext->tenant();

        $rateLimitKey = 'login:'.$request->ip().'|'.$request->string('email');

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }

        $user = $this->auth->verifyCredentials($request->string('email'), $request->string('password'));

        if (! $user) {
            RateLimiter::hit($rateLimitKey, 60);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $membership = TenantUser::withoutTenancy(fn () => TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists()
        );

        if (! $membership) {
            RateLimiter::hit($rateLimitKey, 60);

            throw ValidationException::withMessages([
                'email' => ['Your account does not have access to this academy.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        if ($user->hasMfaEnabled()) {
            $challenge = Str::random(40);

            Cache::put("mfa_challenge:{$challenge}", [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'device_label' => $request->input('device_label'),
            ], now()->addMinutes(5));

            return response()->json([
                'data' => ['mfa_required' => true, 'mfa_token' => $challenge],
            ]);
        }

        $issued = $this->auth->issueToken($user, $request, $tenant->id);

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email']),
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ],
        ]);
    }

    public function verifyMfa(Request $request)
    {
        $request->validate(['mfa_token' => ['required', 'string'], 'code' => ['required', 'string']]);

        $challenge = Cache::get("mfa_challenge:{$request->string('mfa_token')}");

        if (! $challenge) {
            throw ValidationException::withMessages(['mfa_token' => ['This challenge has expired.']]);
        }

        $user = User::findOrFail($challenge['user_id']);

        if (! $this->mfa->verifyForUser($user, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => ['Invalid authentication code.']]);
        }

        Cache::forget("mfa_challenge:{$request->string('mfa_token')}");

        $issued = $this->auth->issueToken($user, $request, $challenge['tenant_id']);

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email']),
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $tenant = $this->tenantContext->tenant();

        $membership = TenantUser::query()
            ->where('user_id', $user->id)
            ->with('role.permissions')
            ->first();

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email', 'email_verified_at']),
                'tenant_id' => $tenant->id,
                'role' => $membership?->role?->only(['id', 'name', 'slug']),
                'permissions' => $membership?->role?->permissions->pluck('key') ?? [],
            ],
        ]);
    }

    /**
     * Signed link target from the verification email. Named `verification.verify`
     * to match Laravel's built-in VerifyEmail notification, which generates
     * the signed URL via `route('verification.verify', ...)`. Since this API
     * has no browser UI of its own, it verifies then redirects into the
     * Next.js frontend rather than rendering anything itself.
     */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);
        $frontendUrl = $user->frontendUrl();

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->away("{$frontendUrl}/verify-email?status=invalid");
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->away("{$frontendUrl}/verify-email?status=verified");
    }

    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['data' => ['already_verified' => true]]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['data' => ['success' => true]]);
    }

    public function logout(Request $request)
    {
        $this->auth->revokeCurrentToken($request->user());

        return response()->json(['data' => ['success' => true]]);
    }

    public function sessions(Request $request)
    {
        $sessions = UserSession::where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_used_at')
            ->get(['id', 'device_label', 'ip_address', 'user_agent', 'last_used_at', 'created_at']);

        return response()->json(['data' => $sessions]);
    }

    public function revokeSession(Request $request, int $sessionId)
    {
        $session = UserSession::where('user_id', $request->user()->id)->findOrFail($sessionId);
        $session->update(['revoked_at' => now()]);

        if ($session->personal_access_token_id) {
            DB::table('personal_access_tokens')->where('id', $session->personal_access_token_id)->delete();
        }

        return response()->json(['data' => ['success' => true]]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Always a generic success response — never reveal whether the
        // email exists.
        return response()->json(['data' => ['success' => true]]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['data' => ['success' => true]]);
    }
}
