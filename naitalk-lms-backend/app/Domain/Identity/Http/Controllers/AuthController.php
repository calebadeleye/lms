<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Http\Requests\LoginRequest;
use App\Domain\Identity\Http\Requests\RegisterRequest;
use App\Domain\Identity\Models\MembershipApplication;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\UserSession;
use App\Domain\Identity\Services\AuthService;
use App\Domain\Identity\Services\MfaService;
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
    ) {}

    /**
     * Self-registration. Always assigns the default "student" role and
     * lands the account in `pending` status — a submitted membership
     * application (the 4 acknowledgements + optional welcome photo,
     * replacing the client's manual Google Form) is what an admin reviews
     * before EnsureMemberApproved lets the account through to anything.
     * Staff accounts are created via invitations, never self-registration.
     */
    public function register(RegisterRequest $request)
    {
        if (User::where('email', $request->string('email'))->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        $studentRole = Role::where('slug', 'student')->firstOrFail();

        $user = DB::transaction(function () use ($request, $studentRole) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'password' => $request->string('password'),
                'role_id' => $studentRole->id,
                'status' => 'pending',
                'joined_at' => now(),
            ]);

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $extension = $file->extension() ?: $file->getClientOriginalExtension();
                $directory = "membership-applications/{$user->id}";
                $file->storeAs($directory, "photo.{$extension}", ['disk' => 'uploads']);
                $photoPath = "{$directory}/photo.{$extension}";
            }

            MembershipApplication::create([
                'user_id' => $user->id,
                'ack_impact_beyond_earning' => $request->boolean('ack_impact_beyond_earning'),
                'ack_growth_mindset' => $request->boolean('ack_growth_mindset'),
                'ack_interest_in_coaching' => $request->boolean('ack_interest_in_coaching'),
                'ack_positive_impact' => $request->boolean('ack_positive_impact'),
                'motivation' => $request->string('motivation')->isEmpty() ? null : $request->string('motivation'),
                'photo_path' => $photoPath,
                'status' => 'pending',
            ]);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        $issued = $this->auth->issueToken($user, $request);

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email']),
                'membership_status' => 'pending',
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ],
        ], 201);
    }

    /** The caller's own membership application — lets the frontend show a
     * pending/rejected holding page without needing admin permissions. */
    public function application(Request $request)
    {
        $application = MembershipApplication::where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $application ? [
            'status' => $application->status,
            'review_note' => $application->review_note,
            'submitted_at' => $application->created_at,
        ] : null]);
    }

    public function login(LoginRequest $request)
    {
        $rateLimitKey = 'login:'.$request->ip().'|'.$request->string('email');

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }

        $user = $this->auth->verifyCredentials($request->string('email'), $request->string('password'));

        // Only a deactivated account is rejected outright — `pending` and
        // `rejected` members can still log in (so the frontend can show
        // their application status); EnsureMemberApproved is what actually
        // gates course/community routes.
        if (! $user || $user->status === 'inactive') {
            RateLimiter::hit($rateLimitKey, 60);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        if ($user->hasMfaEnabled()) {
            $challenge = Str::random(40);

            Cache::put("mfa_challenge:{$challenge}", [
                'user_id' => $user->id,
                'device_label' => $request->input('device_label'),
            ], now()->addMinutes(5));

            return response()->json([
                'data' => ['mfa_required' => true, 'mfa_token' => $challenge],
            ]);
        }

        $issued = $this->auth->issueToken($user, $request);

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

        $issued = $this->auth->issueToken($user, $request);

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
        $user = $request->user()->loadMissing('role.permissions');

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email', 'email_verified_at']),
                'membership_status' => $user->status,
                'role' => $user->role?->only(['id', 'name', 'slug']),
                'permissions' => $user->role?->permissions->pluck('key') ?? [],
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
        $frontendUrl = rtrim((string) config('services.frontend.url'), '/');

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
