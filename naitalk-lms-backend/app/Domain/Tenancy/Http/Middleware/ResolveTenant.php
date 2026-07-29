<?php

namespace App\Domain\Tenancy\Http\Middleware;

use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantDomain;
use App\Domain\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for every tenant-scoped API request from the verified
 * Host header — never from a client-supplied tenant_id. See
 * ARCHITECTURE.md §1 for the full resolution order and rationale.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $hostname = $this->normalizeHost($request);

        // Local/dev convenience: the Next.js BFF calls the API server-to-server
        // and can't rely on DNS for every tenant hostname in local dev, so it
        // forwards the browser's original Host as X-Tenant-Hostname. This
        // header is only trusted from the frontend's authenticated internal
        // channel (see VerifyFrontendSecret), never from the public internet.
        if ($request->attributes->get('frontend_internal_call') && $request->hasHeader('X-Tenant-Hostname')) {
            $hostname = $this->normalizeHost($request, $request->header('X-Tenant-Hostname'));
        }

        $domain = TenantDomain::withoutTenancy(fn () => TenantDomain::query()
            ->where('hostname', $hostname)
            ->where('verification_status', 'verified')
            ->with('tenant')
            ->first()
        );

        if (! $domain || ! $domain->tenant) {
            return response()->json([
                'errors' => [['code' => 'tenant_not_found', 'message' => 'No tenant is configured for this domain.']],
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 404);
        }

        $tenant = $domain->tenant;

        if (in_array($tenant->status, ['deleted', 'deletion_scheduled'], true)) {
            return response()->json([
                'errors' => [['code' => 'tenant_unavailable', 'message' => 'This academy is no longer available.']],
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 404);
        }

        if ($tenant->status === 'suspended') {
            return response()->json([
                'errors' => [['code' => 'tenant_suspended', 'message' => 'This academy is temporarily suspended.']],
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 403);
        }

        app(TenantContext::class)->set($tenant);
        $request->attributes->set('tenant', $tenant);

        // Cross-check: an authenticated user must actually belong to THIS
        // tenant. This is what stops a session valid on tenant A's domain
        // from doing anything on tenant B's domain, even if both resolve to
        // the same physical user account.
        if ($user = $request->user()) {
            $isImpersonating = $request->attributes->get('impersonating_support_session') !== null;

            if (! $isImpersonating) {
                $membership = TenantUser::withoutTenancy(fn () => TenantUser::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->exists()
                );

                if (! $membership) {
                    return response()->json([
                        'errors' => [['code' => 'tenant_mismatch', 'message' => 'Your account does not have access to this academy.']],
                        'meta' => ['request_id' => $request->attributes->get('request_id')],
                    ], 403);
                }
            }
        }

        return $next($request);
    }

    private function normalizeHost(Request $request, ?string $raw = null): string
    {
        $host = $raw ?? $request->getHost();
        $host = strtolower(trim($host));

        // Strip a port if one slipped through (getHost() already excludes it,
        // but the X-Tenant-Hostname header is client-supplied within the
        // trusted internal channel and may include one).
        return preg_replace('/:\d+$/', '', $host);
    }
}
