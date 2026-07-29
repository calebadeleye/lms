<?php

namespace App\Domain\Identity\Models;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Deliberately does NOT use BelongsToTenant: a role's tenant_id is nullable
 * by design (null = platform role), and roles are created both inside a
 * tenant context (tenant admins defining custom roles) and outside one
 * (platform role seeding). Scoping is explicit via scopeForTenant/scopePlatform
 * rather than the auto-scaffolded global scope.
 */
class Role extends Model
{
    protected $fillable = ['tenant_id', 'name', 'slug', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('tenant_id');
    }

    public function hasPermission(string $key): bool
    {
        return $this->permissions()->where('key', $key)->exists();
    }
}
