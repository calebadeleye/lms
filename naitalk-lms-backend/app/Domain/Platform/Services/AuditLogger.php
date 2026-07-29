<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * The only way audit_logs rows get written. `metadata` must never contain
 * secret values (credentials, tokens, card data) — this is a contract
 * enforced by convention at call sites, not by this class, since it has no
 * way to know what a given caller considers secret.
 */
class AuditLogger
{
    public function log(
        string $action,
        ?string $tenantId = null,
        ?int $userId = null,
        array $metadata = [],
        bool $viaImpersonation = false,
        ?string $auditableType = null,
        ?int $auditableId = null,
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?? RequestFacade::user()?->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'metadata' => $metadata,
            'ip_address' => RequestFacade::ip(),
            'via_impersonation' => $viaImpersonation,
        ]);
    }
}
