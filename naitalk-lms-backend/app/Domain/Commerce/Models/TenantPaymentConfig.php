<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TenantPaymentConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'provider', 'mode', 'public_key', 'subaccount_code', 'environment',
        'commission_percent', 'fee_bearer', 'status', 'last_verified_at',
    ];

    protected $hidden = ['secret_key_encrypted', 'webhook_secret_encrypted'];

    protected $casts = [
        'commission_percent' => 'float',
        'last_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantPaymentConfig $config) {
            $config->webhook_token ??= (string) Str::uuid();
        });
    }

    public function setSecretKey(string $plainSecret): void
    {
        $this->secret_key_encrypted = Crypt::encryptString($plainSecret);
    }

    public function getSecretKey(): ?string
    {
        return $this->secret_key_encrypted ? Crypt::decryptString($this->secret_key_encrypted) : null;
    }

    public function setWebhookSecret(string $plainSecret): void
    {
        $this->webhook_secret_encrypted = Crypt::encryptString($plainSecret);
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhook_secret_encrypted ? Crypt::decryptString($this->webhook_secret_encrypted) : null;
    }

    /** Never the real key — safe to send to the frontend. */
    public function maskedPublicKey(): ?string
    {
        if (! $this->public_key) {
            return null;
        }

        return Str::limit($this->public_key, 8, '').'…'.substr($this->public_key, -4);
    }

    public function hasSecretConfigured(): bool
    {
        return $this->secret_key_encrypted !== null;
    }

    public function isManaged(): bool
    {
        return $this->mode === 'managed';
    }

    public function effectiveCommissionPercent(): float
    {
        return $this->commission_percent ?? (float) config('services.platform_billing.default_commission_percent');
    }
}
