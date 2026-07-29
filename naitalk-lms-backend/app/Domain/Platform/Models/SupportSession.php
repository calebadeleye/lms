<?php

namespace App\Domain\Platform\Models;

use App\Domain\Identity\Models\PlatformStaff;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSession extends Model
{
    protected $fillable = [
        'platform_staff_id', 'tenant_id', 'target_user_id', 'reason',
        'started_at', 'expires_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(PlatformStaff::class, 'platform_staff_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }
}
