<?php

namespace App\Domain\Billing\Services;

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Live usage counts for the plan-limit feature keys EntitlementService can
 * resolve — powers both the tenant's "Plan & Usage" dashboard and the
 * capacity checks that block an action once a tenant is at their plan's
 * limit. Counts live from the source tables rather than a maintained
 * running tally (the `tenant_usage` table exists but nothing writes to it)
 * — at this app's scale a live count is simpler and can never drift out of
 * sync with reality. Results are cached briefly since storage usage scans
 * the filesystem; callers that just changed a counted metric should
 * forget() it so the very next check is accurate.
 */
class TenantUsageService
{
    public function __construct(private EntitlementService $entitlements) {}

    /** Tenant-role slug => the plan feature key that role counts against. */
    private const ROLE_METRICS = [
        'tenant-owner' => 'max_administrators',
        'tenant-administrator' => 'max_administrators',
        'instructor' => 'max_instructors',
        'coach' => 'max_coaches',
    ];

    /** The feature keys this service can actually measure real usage for —
     * the rest of EntitlementService's keys are booleans/toggles, not
     * counts, so there's nothing to gate. */
    public const MEASURABLE_METRICS = [
        'max_administrators', 'max_instructors', 'max_coaches',
        'max_active_students', 'max_published_courses', 'storage_gb',
    ];

    private const LABELS = [
        'max_administrators' => 'Administrators',
        'max_instructors' => 'Instructors',
        'max_coaches' => 'Coaches',
        'max_active_students' => 'Active students',
        'max_published_courses' => 'Published courses',
        'storage_gb' => 'Storage',
    ];

    public function used(Tenant $tenant, string $metricKey): int|float
    {
        return Cache::remember(
            "tenant:{$tenant->id}:usage:{$metricKey}",
            now()->addMinutes(2),
            fn () => $this->compute($tenant, $metricKey)
        );
    }

    /** Whether adding $additional more units of $metricKey would still fit
     * under the tenant's plan limit. Always true for feature keys this
     * service doesn't measure (booleans, or anything not in
     * MEASURABLE_METRICS) — nothing to gate. */
    public function hasCapacity(Tenant $tenant, string $metricKey, int|float $additional = 1): bool
    {
        if (! in_array($metricKey, self::MEASURABLE_METRICS, true)) {
            return true;
        }

        $limit = $metricKey === 'storage_gb'
            ? (float) $this->entitlements->value($tenant, $metricKey)
            : $this->entitlements->integer($tenant, $metricKey);

        return ($this->used($tenant, $metricKey) + $additional) <= $limit;
    }

    public function forget(Tenant $tenant, string $metricKey): void
    {
        Cache::forget("tenant:{$tenant->id}:usage:{$metricKey}");
    }

    /** Which counted metric a tenant-role slug counts against, if any —
     * roles like finance-manager or student aren't capacity-limited. */
    public function metricForRole(string $roleSlug): ?string
    {
        return self::ROLE_METRICS[$roleSlug] ?? null;
    }

    /** Full plan-vs-usage breakdown for the tenant's own "Plan & Usage"
     * dashboard — every measurable metric alongside its current plan
     * limit. */
    public function summary(Tenant $tenant): array
    {
        return collect(self::MEASURABLE_METRICS)->map(function (string $key) use ($tenant) {
            $limit = $key === 'storage_gb'
                ? (float) $this->entitlements->value($tenant, $key)
                : $this->entitlements->integer($tenant, $key);
            $used = $this->used($tenant, $key);

            return [
                'key' => $key,
                'label' => self::LABELS[$key],
                'used' => $used,
                'limit' => $limit,
                'unit' => $key === 'storage_gb' ? 'GB' : null,
                'percent' => $limit > 0 ? min(100, round(($used / $limit) * 100)) : 0,
                'at_limit' => $used >= $limit,
            ];
        })->values()->all();
    }

    private function compute(Tenant $tenant, string $metricKey): int|float
    {
        return match ($metricKey) {
            'max_administrators' => $this->countByRoles($tenant, ['tenant-owner', 'tenant-administrator']),
            'max_instructors' => $this->countByRoles($tenant, ['instructor']),
            'max_coaches' => $this->countByRoles($tenant, ['coach']),
            'max_active_students' => $this->activeStudentCount($tenant),
            'max_published_courses' => Course::withoutTenancy(fn () => Course::query()
                ->where('tenant_id', $tenant->id)->where('status', 'published')->count()
            ),
            'storage_gb' => $this->storageGb($tenant),
            default => 0,
        };
    }

    /** Active members plus pending invitations for these roles — an
     * invitation reserves the seat the moment it's sent (matching the
     * capacity check happening at invite time in InvitationController),
     * so a tenant can't invite past their limit and have everyone accept
     * at once. An accepted invitation's status flips to 'accepted',
     * dropping out of this count exactly as the new TenantUser row enters
     * it, so acceptance itself never needs its own capacity check. */
    private function countByRoles(Tenant $tenant, array $roleSlugs): int
    {
        $activeMembers = TenantUser::withoutTenancy(fn () => TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $roleSlugs))
            ->count()
        );

        $pendingInvitations = Invitation::withoutTenancy(fn () => Invitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $roleSlugs))
            ->count()
        );

        return $activeMembers + $pendingInvitations;
    }

    private function activeStudentCount(Tenant $tenant): int
    {
        return Enrolment::withoutTenancy(fn () => Enrolment::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->distinct('user_id')
            ->count('user_id')
        );
    }

    /** Every tenant-owned file lives under `{tenant_id}/...` on the
     * `tenants` disk (branding assets, course thumbnails, lesson
     * materials) — see BrandingController/CourseController/
     * LessonController's own storeAs() calls. */
    private function storageGb(Tenant $tenant): float
    {
        $bytes = 0;

        foreach (Storage::disk('tenants')->allFiles((string) $tenant->id) as $file) {
            $bytes += Storage::disk('tenants')->size($file);
        }

        return round($bytes / 1_073_741_824, 3);
    }
}
