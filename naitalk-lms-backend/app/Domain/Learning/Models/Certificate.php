<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'course_id', 'enrolment_id', 'certificate_number', 'verification_code',
        'recipient_name', 'course_title', 'completed_at', 'issued_at', 'revoked_at',
        'revoked_reason', 'metadata',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null;
    }
}
