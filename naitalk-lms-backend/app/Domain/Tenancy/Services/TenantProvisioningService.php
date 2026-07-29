<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Billing\Models\PlatformPlan;
use App\Domain\Billing\Models\TenantSubscription;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Identity\Notifications\AddedToTenantNotification;
use App\Domain\Identity\Notifications\TenantInvitationNotification;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantBranding;
use App\Domain\Tenancy\Models\TenantDomain;
use App\Models\User;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
        ?int $invitedByUserId = null,
    ): Tenant {
        return DB::transaction(function () use ($name, $ownerEmail, $ownerName, $ownerPassword, $plan, $subscriptionStatus, $invitedByUserId) {
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
                $ownerRole = Role::forTenant($tenant->id)->where('slug', 'tenant-owner')->firstOrFail();

                if ($ownerPassword) {
                    // Known-password bootstrap — only used by seeders/demo
                    // data, where the password is meant to be usable
                    // immediately. Real platform-admin provisioning never
                    // passes this (see the else branch below), because a
                    // fabricated password nobody receives is unusable.
                    // email_verified_at isn't in User::$fillable
                    // (deliberately — nothing should mass-assign it from
                    // request input), so it has to be set via forceFill
                    // rather than passed into firstOrCreate()'s attributes,
                    // which would silently drop it.
                    $owner = User::firstOrCreate(
                        ['email' => $ownerEmail],
                        ['name' => $ownerName ?? $name.' Owner', 'password' => $ownerPassword]
                    );
                    if (! $owner->email_verified_at) {
                        $owner->forceFill(['email_verified_at' => now()])->save();
                    }

                    TenantUser::create([
                        'user_id' => $owner->id,
                        'role_id' => $ownerRole->id,
                        'status' => 'active',
                        'joined_at' => now(),
                    ]);

                    $tenant->update(['owner_user_id' => $owner->id]);
                } else {
                    // Real provisioning: no password is fabricated, and no
                    // account is usable until someone actually sets one.
                    // Mirrors InvitationController::store()'s two paths —
                    // an email that already has an account anywhere is
                    // added directly (no need to set a password again);
                    // otherwise a real invitation + accept-by-token flow,
                    // the same one tenant admins use to invite members.
                    $existingUser = User::where('email', $ownerEmail)->first();

                    if ($existingUser) {
                        TenantUser::create([
                            'user_id' => $existingUser->id,
                            'role_id' => $ownerRole->id,
                            'status' => 'active',
                            'joined_at' => now(),
                        ]);

                        $tenant->update(['owner_user_id' => $existingUser->id]);

                        Notification::route('mail', $existingUser->email)
                            ->notify(new AddedToTenantNotification($tenant, $ownerRole));
                    } else {
                        $invitation = Invitation::create([
                            'email' => $ownerEmail,
                            'role_id' => $ownerRole->id,
                            'token' => Str::random(48),
                            'invited_by' => $invitedByUserId,
                            'status' => 'pending',
                            'expires_at' => now()->addDays(7),
                        ]);

                        Notification::route('mail', $ownerEmail)->notify(new TenantInvitationNotification($invitation));
                    }
                }
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
