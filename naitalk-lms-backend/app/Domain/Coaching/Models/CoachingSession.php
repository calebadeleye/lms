<?php

namespace App\Domain\Coaching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachingSession extends Model
{

    protected $fillable = [
        'coaching_service_id', 'coach_id', 'scheduled_start', 'scheduled_end',
        'meeting_url', 'status', 'notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(CoachingService::class, 'coaching_service_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(SessionAttendance::class);
    }

    public function activeBookingsCount(): int
    {
        return $this->bookings()->where('status', 'confirmed')->count();
    }
}
