<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{

    protected $fillable = ['email', 'role_id', 'token', 'invited_by', 'status', 'expires_at', 'accepted_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function acceptUrl(): string
    {
        $frontendUrl = rtrim((string) config('services.frontend.url'), '/');

        return "{$frontendUrl}/invitations/{$this->token}";
    }
}
