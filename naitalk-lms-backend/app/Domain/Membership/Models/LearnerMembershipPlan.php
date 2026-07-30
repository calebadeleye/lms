<?php

namespace App\Domain\Membership\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearnerMembershipPlan extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'billing_period', 'price_cents', 'currency', 'benefits', 'is_active'];

    protected $casts = [
        'benefits' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(LearnerSubscription::class, 'plan_id');
    }

    public function isFree(): bool
    {
        return $this->billing_period === 'free' || $this->price_cents === 0;
    }
}
