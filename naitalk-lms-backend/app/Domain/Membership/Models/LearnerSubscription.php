<?php

namespace App\Domain\Membership\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerSubscription extends Model
{

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'current_period_start', 'current_period_end',
        'cancel_at_period_end', 'cancelled_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LearnerMembershipPlan::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
