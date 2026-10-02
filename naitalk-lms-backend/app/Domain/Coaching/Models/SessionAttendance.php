<?php

namespace App\Domain\Coaching\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendance extends Model
{

    // Eloquent's default snake_case-plural inference would guess
    // `session_attendances` — the migration created `session_attendance`.
    protected $table = 'session_attendance';

    protected $fillable = ['coaching_session_id', 'user_id', 'attended', 'joined_at', 'notes'];

    protected $casts = [
        'attended' => 'boolean',
        'joined_at' => 'datetime',
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
