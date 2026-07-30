<?php

namespace App\Domain\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. Rows are only ever written through
 * App\Domain\Audit\Services\AuditLogger — never created directly.
 *
 * `user_id` is nullable because some audited actions are system-initiated
 * (scheduled commands, webhook fulfillment) with no acting user.
 */
class AuditLog extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'metadata', 'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
