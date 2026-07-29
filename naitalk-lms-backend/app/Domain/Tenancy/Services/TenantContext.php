<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Tenancy\Models\Tenant;

/**
 * Request-scoped holder for the resolved tenant. Bound as a singleton by
 * TenantResolutionMiddleware — nothing else is allowed to set the tenant,
 * and in particular nothing derives it from request input.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): Tenant
    {
        if (! $this->tenant) {
            throw new \RuntimeException('No tenant resolved in the current request context.');
        }

        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }
}
