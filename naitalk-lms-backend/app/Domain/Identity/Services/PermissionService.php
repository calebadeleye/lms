<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\PlatformStaff;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The single call site for "does this user have permission X". Policies and
 * the permission:{key} route middleware both delegate here — no
 * `$user->role === 'admin'` string comparisons anywhere else in the app.
 */
class PermissionService
{
    public function userHasTenantPermission(User $user, string $tenantId, string $key): bool
    {
        $keys = $this->tenantPermissionKeys($user, $tenantId);

        return in_array($key, $keys, true);
    }

    public function userHasPlatformPermission(User $user, string $key): bool
    {
        $keys = $this->platformPermissionKeys($user);

        return in_array($key, $keys, true);
    }

    /**
     * @return list<string>
     */
    public function tenantPermissionKeys(User $user, string $tenantId): array
    {
        return Cache::remember(
            "tenant:{$tenantId}:user:{$user->id}:permissions",
            now()->addMinutes(5),
            function () use ($user, $tenantId) {
                $membership = TenantUser::withoutTenancy(fn () => TenantUser::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->with('role.permissions')
                    ->first()
                );

                return $membership?->role?->permissions->pluck('key')->all() ?? [];
            }
        );
    }

    /**
     * @return list<string>
     */
    public function platformPermissionKeys(User $user): array
    {
        return Cache::remember(
            "platform:user:{$user->id}:permissions",
            now()->addMinutes(5),
            function () use ($user) {
                $staff = PlatformStaff::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->with('role.permissions')
                    ->first();

                return $staff?->role?->permissions->pluck('key')->all() ?? [];
            }
        );
    }

    public function forgetCacheFor(User $user, ?string $tenantId = null): void
    {
        if ($tenantId) {
            Cache::forget("tenant:{$tenantId}:user:{$user->id}:permissions");
        }

        Cache::forget("platform:user:{$user->id}:permissions");
    }
}
