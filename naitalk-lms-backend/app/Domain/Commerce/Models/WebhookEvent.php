<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately not BelongsToTenant: webhooks are resolved via
 * tenant_payment_configs.webhook_token, not TenantContext (there's no
 * hostname to resolve from — the request comes directly from the payment
 * provider's servers), and a malformed/unroutable webhook may need to be
 * recorded with tenant_id null.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'tenant_id', 'provider', 'event_type', 'provider_event_id', 'payload',
        'status', 'processed_at', 'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function markProcessed(): void
    {
        $this->update(['status' => 'processed', 'processed_at' => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error_message' => $error]);
    }
}
