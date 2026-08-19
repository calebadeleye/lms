<?php

namespace App\Domain\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A pre-account membership fee payment — see the migration's docblock. Not
 * an Order/Payment (those always belong to a real User); this is its own
 * small, standalone record.
 */
class MembershipFeePayment extends Model
{
    protected $fillable = [
        'name', 'email', 'amount_cents', 'currency', 'provider',
        'provider_reference', 'status', 'paid_at', 'raw_response',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'raw_response' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (MembershipFeePayment $payment) {
            $payment->idempotency_key ??= (string) Str::uuid();
        });
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
