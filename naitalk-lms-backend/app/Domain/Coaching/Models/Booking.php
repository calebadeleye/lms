<?php

namespace App\Domain\Coaching\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'coaching_session_id', 'user_id', 'status', 'booked_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'booked_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoachingSession::class, 'coaching_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
