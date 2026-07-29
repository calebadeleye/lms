<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'plan_id', 'status', 'current_period_start', 'current_period_end',
        'grace_period_ends_at', 'cancel_at_period_end', 'cancelled_at',
        'complimentary_until', 'complimentary_reason', 'granted_by_platform_staff_id',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'cancelled_at' => 'datetime',
        'complimentary_until' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }

    public function isComplimentary(): bool
    {
        return $this->status === 'complimentary';
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'grace_period', 'complimentary'], true);
    }
}
