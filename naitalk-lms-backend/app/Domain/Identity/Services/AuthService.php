<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\UserSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Shared login/token machinery for AuthController. The Next.js BFF is the
 * only intended caller: it exchanges credentials here for a Sanctum bearer
 * token, then keeps that token server-side inside an encrypted HttpOnly
 * cookie (see ARCHITECTURE.md §2) — this endpoint itself does not set
 * cookies, since it has no browser-facing origin of its own.
 */
class AuthService
{
    public function verifyCredentials(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueToken(User $user, Request $request): array
    {
        $expiresAt = now()->addHours(2);

        $token = $user->createToken(
            name: 'web-session',
            abilities: ['*'],
            expiresAt: $expiresAt,
        );

        UserSession::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $token->accessToken->id,
            'device_label' => $request->input('device_label'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_used_at' => now(),
        ]);

        return ['token' => $token->plainTextToken, 'expires_at' => $expiresAt];
    }

    public function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token) {
            UserSession::where('personal_access_token_id', $token->id)
                ->update(['revoked_at' => now()]);

            $token->delete();
        }
    }
}
