<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Tenancy\Models\TenantDomain;
use App\Http\Controllers\Controller;

/**
 * Read-only oversight across every tenant's domains — `domains.oversight`
 * is deliberately a lighter permission than the tenant-scoped
 * `domains.manage` (see DomainController): platform staff can see
 * verification/SSL status here, but changing a domain stays the tenant's
 * own responsibility via their own admin panel.
 */
class PlatformDomainController extends Controller
{
    public function index()
    {
        $domains = TenantDomain::withoutTenancy(fn () => TenantDomain::with('tenant:id,name,slug')
            ->orderByDesc('created_at')
            ->get()
        );

        return response()->json(['data' => $domains]);
    }
}
