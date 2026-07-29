<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SubscriptionOverride extends Model
{
    use BelongsToTenant;

    protected $fillable = ['feature_key', 'value', 'expires_at', 'granted_by_platform_staff_id'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
