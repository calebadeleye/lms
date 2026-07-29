<?php

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Exceptions\TenantMismatchException;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned Eloquent model. Two jobs:
 *
 * 1. Every query against the model is automatically scoped to the tenant
 *    resolved for the current request — a query simply cannot see another
 *    tenant's rows, there's no "forgot the where clause" failure mode.
 * 2. `tenant_id` is force-set on creation from TenantContext and stripped
 *    from mass assignment — it is never accepted from request input.
 *
 * Queue jobs and console commands don't have an ambient TenantContext; they
 * must call TenantContext::set() themselves (see
 * App\Domain\Tenancy\Concerns\TenantAwareJob) before touching these models,
 * or explicitly opt out with `Model::withoutTenancy(fn () => ...)`.
 */
trait BelongsToTenant
{
    protected static bool $tenancyScopeDisabled = false;

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (static::$tenancyScopeDisabled) {
                return;
            }

            $context = app(TenantContext::class);

            if ($context->has()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $context->id());
            }
        });

        static::creating(function (Model $model) {
            if (static::$tenancyScopeDisabled) {
                return;
            }

            $context = app(TenantContext::class);

            if (! $context->has()) {
                throw new \RuntimeException(
                    'Cannot create a '.static::class.' without a resolved TenantContext.'
                );
            }

            // Never trust a client-supplied tenant_id — always overwrite.
            $model->tenant_id = $context->id();
        });

        static::saving(function (Model $model) {
            if (static::$tenancyScopeDisabled || ! $model->exists) {
                return;
            }

            $context = app(TenantContext::class);

            if ($context->has() && $model->getOriginal('tenant_id') !== $context->id()) {
                throw new TenantMismatchException;
            }
        });
    }

    /**
     * Escape hatch for platform-admin contexts and queue jobs that must
     * touch tenant data across tenants (e.g. export jobs) deliberately.
     */
    public static function withoutTenancy(\Closure $callback): mixed
    {
        static::$tenancyScopeDisabled = true;

        try {
            return $callback();
        } finally {
            static::$tenancyScopeDisabled = false;
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * `tenant_id` is only ever mass-assignable inside withoutTenancy() —
     * normal request-driven code always gets it overwritten by the
     * `creating` hook above regardless, so this only matters for the
     * platform/job code paths that deliberately disabled the scope and are
     * setting tenant_id themselves (e.g. platform admin creating a
     * subscription for an arbitrary tenant).
     */
    public function isFillable($key): bool
    {
        if ($key === 'tenant_id') {
            return static::$tenancyScopeDisabled;
        }

        return parent::isFillable($key);
    }

    /**
     * Model::fillableFromArray() does an array_intersect_key against
     * getFillable() BEFORE isFillable() is ever consulted, so tenant_id
     * (never listed in any model's $fillable) would be stripped here first
     * regardless of the isFillable() override above. Temporarily admit it
     * into this instance's fillable list when the scope is disabled and the
     * caller actually supplied one.
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (static::$tenancyScopeDisabled && array_key_exists('tenant_id', $attributes)
            && ! in_array('tenant_id', $this->fillable, true)) {
            $this->fillable[] = 'tenant_id';
        }

        return parent::fillableFromArray($attributes);
    }
}
