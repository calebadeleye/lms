<?php

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;

/**
 * Queue jobs have no ambient hostname to resolve a tenant from. A job that
 * touches tenant-scoped models must use this trait, store the tenant's id
 * (never the model — always re-fetch, the tenant may have changed state),
 * and call `establishTenantContext()` as the first line of `handle()`.
 */
trait TenantAwareJob
{
    public string $tenantId;

    protected function establishTenantContext(): Tenant
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        app(TenantContext::class)->set($tenant);

        return $tenant;
    }
}
