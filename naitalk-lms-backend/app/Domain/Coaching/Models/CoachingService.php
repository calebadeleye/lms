<?php

namespace App\Domain\Coaching\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachingService extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'coach_id', 'title', 'description', 'session_type', 'duration_minutes',
        'is_free', 'price_cents', 'currency', 'max_participants', 'is_active',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CoachingSession::class);
    }

    public function isGroup(): bool
    {
        return $this->session_type === 'group';
    }
}
