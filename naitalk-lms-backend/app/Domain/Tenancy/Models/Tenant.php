<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Billing\Models\TenantSubscription;
use App\Domain\Identity\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'legal_name', 'support_email', 'status',
        'approved_at', 'suspended_at', 'suspension_reason',
        'deletion_scheduled_at', 'deleted_permanently_at', 'owner_user_id', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
        'deleted_permanently_at' => 'datetime',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(TenantDomain::class)->where('is_primary', true);
    }

    public function branding(): HasOne
    {
        return $this->hasOne(TenantBranding::class);
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Same reasoning as User::frontendUrl() (see that method for the full
     * explanation): every tenant-facing page requires a resolvable tenant
     * hostname, so an emailed link must point at this tenant's own
     * subdomain rather than the bare neutral platform domain, while still
     * reusing services.frontend.url's configured scheme/port for local dev.
     * This variant is for links generated before any User row exists yet
     * (e.g. a staff invitation), so it can't go through User::frontendUrl().
     */
    public function frontendUrl(): string
    {
        $configured = rtrim((string) config('services.frontend.url'), '/');
        $hostname = $this->primaryDomain?->hostname;

        if (! $hostname) {
            return $configured;
        }

        $parts = parse_url($configured);
        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return "{$scheme}://{$hostname}{$port}";
    }
}
