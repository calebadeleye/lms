<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Billing\Models\PlatformPlan;
use App\Domain\Billing\Models\TenantSubscription;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantBranding;
use App\Domain\Tenancy\Models\TenantDomain;
use App\Models\User;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place a tenant is actually created. Every new tenant gets: its own
 * copy of the eight standard roles (never shared rows — a tenant admin
 * editing "Instructor" permissions must never affect other tenants), a
 * verified default domain on the neutral platform domain (no DNS step
 * needed), a default branding row, and a subscription record. Called by the
 * platform admin "create tenant" endpoint and by the HR GEMS demo seeder.
 */
class TenantProvisioningService
{
    public function provision(
        string $name,
        ?string $ownerEmail = null,
        ?string $ownerName = null,
        ?string $ownerPassword = null,
        ?PlatformPlan $plan = null,
        string $subscriptionStatus = 'trialing',
    ): Tenant {
        return DB::transaction(function () use ($name, $ownerEmail, $ownerName, $ownerPassword, $plan, $subscriptionStatus) {
            $slug = $this->uniqueSlug($name);

            $tenant = Tenant::create([
                'id' => (string) Str::uuid(),
                'slug' => $slug,
                'name' => $name,
                'status' => 'active',
                'approved_at' => now(),
            ]);

            app(TenantContext::class)->set($tenant);

            $this->seedTenantRoles($tenant);

            TenantDomain::create([
                'hostname' => strtolower($slug).'.'.config('services.frontend.neutral_platform_domain'),
                'domain_type' => 'platform_subdomain',
                'verification_status' => 'verified',
                'ssl_status' => 'active',
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            TenantBranding::create([]);

            if ($plan) {
                $subscription = TenantSubscription::create([
                    'plan_id' => $plan->id,
                    'status' => $subscriptionStatus,
                    'current_period_start' => now(),
                    'current_period_end' => $subscriptionStatus === 'trialing'
                        ? now()->addDays($plan->trial_days ?: 14)
                        : now()->addMonth(),
                ]);
            }

            if ($ownerEmail) {
                // Provisioned directly by platform staff (or a seeder), not
                // through self-registration — the email is already known to
                // be correct, so there's no unverified-inbox risk to gate on
                // here the way there is for AuthController::register().
                // email_verified_at isn't in User::$fillable (deliberately —
                // nothing should mass-assign it from request input), so it
                // has to be set via forceFill rather than passed into
                // firstOrCreate()'s attributes, which would silently drop it.
                $owner = User::firstOrCreate(
                    ['email' => $ownerEmail],
                    ['name' => $ownerName ?? $name.' Owner', 'password' => $ownerPassword ?? Str::password(16)]
                );
                if (! $owner->email_verified_at) {
                    $owner->forceFill(['email_verified_at' => now()])->save();
                }

                $ownerRole = Role::forTenant($tenant->id)->where('slug', 'tenant-owner')->firstOrFail();

                TenantUser::create([
                    'user_id' => $owner->id,
                    'role_id' => $ownerRole->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                $tenant->update(['owner_user_id' => $owner->id]);
            }

            app(TenantContext::class)->clear();

            return $tenant->fresh();
        });
    }

    private function seedTenantRoles(Tenant $tenant): void
    {
        $allTenantPermissionIds = Permission::where('scope', 'tenant')->pluck('id');

        foreach (PermissionCatalog::TENANT_ROLE_DEFAULTS as $slug => $keys) {
            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => PermissionCatalog::TENANT_ROLE_LABELS[$slug],
                'slug' => $slug,
                'is_system' => true,
            ]);

            $permissionIds = in_array('*', $keys, true)
                ? $allTenantPermissionIds
                : Permission::whereIn('key', $keys)->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
