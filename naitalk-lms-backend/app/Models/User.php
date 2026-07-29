<?php

namespace App\Models;

use App\Domain\Identity\Models\PlatformStaff;
use App\Domain\Identity\Models\TenantUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->public_id ??= (string) \Illuminate\Support\Str::uuid();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function platformStaff(): HasOne
    {
        return $this->hasOne(PlatformStaff::class);
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null;
    }

    /**
     * Every page in the frontend requires a resolvable tenant hostname to
     * render anything at all (see `getTenantConfig()`) — so an email link
     * (verification, password reset) that points at the bare neutral
     * platform domain 404s the moment it's clicked, since that domain isn't
     * itself a tenant. These links have no request-time tenant context to
     * fall back on (they're clicked from an inbox, possibly days later), so
     * this resolves the user's own tenant membership instead. A user
     * self-registers into exactly one tenant, so the "first active
     * membership" is unambiguous for the common case this actually serves.
     */
    public function primaryTenantHostname(): ?string
    {
        $membership = TenantUser::withoutTenancy(fn () => TenantUser::where('user_id', $this->id)
            ->where('status', 'active')
            ->with('tenant.primaryDomain')
            ->first()
        );

        return $membership?->tenant?->primaryDomain?->hostname;
    }

    /**
     * The frontend URL email links (verification, password reset) should
     * point at for this specific user — their own tenant subdomain when
     * they have one, reusing `services.frontend.url`'s configured
     * scheme/port (relevant in local dev, where the frontend runs on a
     * non-default port), falling back to that config value outright only
     * for the edge case of a user with no active tenant membership.
     */
    public function frontendUrl(): string
    {
        $configured = rtrim((string) config('services.frontend.url'), '/');
        $hostname = $this->primaryTenantHostname();

        if (! $hostname) {
            return $configured;
        }

        $parts = parse_url($configured);
        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return "{$scheme}://{$hostname}{$port}";
    }
}
