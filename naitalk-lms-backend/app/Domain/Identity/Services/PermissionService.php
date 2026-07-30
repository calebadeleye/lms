<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The single call site for "does this user have permission X". Policies and
 * the permission:{key} route middleware both delegate here — no
 * `$user->role === 'admin'` string comparisons anywhere else in the app.
 */
class PermissionService
{
    public function userHasPermission(User $user, string $key): bool
    {
        return in_array($key, $this->permissionKeys($user), true);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(User $user): array
    {
        return Cache::remember(
            "user:{$user->id}:permissions",
            now()->addMinutes(5),
            fn () => $user->role?->permissions->pluck('key')->all() ?? []
        );
    }

    public function forgetCacheFor(User $user): void
    {
        Cache::forget("user:{$user->id}:permissions");
    }
}
