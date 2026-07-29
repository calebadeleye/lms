<?php

namespace App\Domain\Platform\Models;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately not BelongsToTenant: audit entries are written for both
 * tenant-scoped actions and platform-only actions (tenant_id null), often
 * from contexts (support impersonation, platform admin) where forcing a
 * TenantContext would be wrong. Callers pass tenant_id explicitly via
 * App\Domain\Platform\Services\AuditLogger.
 */
class AuditLog extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
        'metadata', 'ip_address', 'via_impersonation',
    ];

    protected $casts = [
        'metadata' => 'array',
        'via_impersonation' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
