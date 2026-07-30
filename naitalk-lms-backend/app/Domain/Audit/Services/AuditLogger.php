<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
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
        ?int $userId = null,
        array $metadata = [],
        ?string $auditableType = null,
        ?int $auditableId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? RequestFacade::user()?->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'metadata' => $metadata,
            'ip_address' => RequestFacade::ip(),
        ]);
    }
}
