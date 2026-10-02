<?php

namespace App\Domain\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MembershipApplication extends Model
{
    protected $fillable = [
        'user_id',
        'ack_impact_beyond_earning', 'ack_growth_mindset', 'ack_interest_in_coaching', 'ack_positive_impact',
        'photo_path', 'motivation',
        'status', 'payment_status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'ack_impact_beyond_earning' => 'boolean',
        'ack_growth_mindset' => 'boolean',
        'ack_interest_in_coaching' => 'boolean',
        'ack_positive_impact' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MembershipApplication $application) {
            $application->payment_token ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
